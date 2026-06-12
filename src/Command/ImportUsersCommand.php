<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Imports legacy uzivatel rows into the new User table.
 *
 * - Passwords stay as MD5; on first login Symfony's migrate_from rehashes to bcrypt.
 * - Duplicate emails are merged into a single User: canonical row chosen by rule
 *   (oldest id, except vlkondra@email.cz where id 248 has the only crossing → kept).
 *   Dropped rows' data is preserved in legacyMergedIds + legacyDataSnapshot.
 * - Disabled (enabled=0) users are imported with isActive=false.
 */
#[AsCommand(
    name: 'app:import:users',
    description: 'Imports users from the legacy MySQL database into the new Postgres user table',
)]
final class ImportUsersCommand extends Command
{
    /**
     * Hard-coded canonical winners for known duplicate-email cases.
     * Map: lowercased email → uzivatel.id that should be kept.
     * For all other duplicates we fall back to "oldest id wins".
     */
    private const CANONICAL_OVERRIDES = [
        'vlkondra@email.cz' => 248, // has 1 crossing from 2014, see docs/migration.md
    ];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.old_connection')]
        private readonly Connection $oldConnection,
        private readonly UserRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Drop existing imported rows before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($input->getOption('truncate') && !$dryRun) {
            $io->warning('Truncating existing user rows (legacy-imported only)');
            $this->em->createQuery('DELETE FROM ' . User::class . ' u WHERE u.legacyId IS NOT NULL')->execute();
        }

        $rows = $this->oldConnection->fetchAllAssociative('
            SELECT id, nick, heslo, jmeno, prijmeni, rok_nar, email, telefon, mesto, pohlavi, role, enabled
            FROM uzivatel
            ORDER BY id ASC
        ');
        $io->info(sprintf('Found %d users in legacy DB', count($rows)));

        // Group rows by lowercased email
        $byEmail = [];
        foreach ($rows as $row) {
            $email = strtolower(trim($row['email'] ?? ''));
            if ($email === '') {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> uzivatel#%d "%s" — empty email', $row['id'], $row['nick'] ?? '?'));
                continue;
            }
            $byEmail[$email][] = $row;
        }

        $imported = 0;
        $merged = 0;
        $skipped = 0;
        $usedNicks = [];

        foreach ($byEmail as $email => $group) {
            $canonical = $this->pickCanonical($email, $group);
            $dropped = array_values(array_filter($group, fn ($r) => $r['id'] !== $canonical['id']));

            // Skip if this canonical was already imported (idempotency without --truncate)
            $alreadyImported = $this->repository->findOneBy(['legacyId' => (int) $canonical['id']]);
            if ($alreadyImported !== null) {
                $skipped++;
                continue;
            }

            $nick = $this->resolveNick($canonical['nick'] ?? null, $usedNicks, $io, (int) $canonical['id']);

            $user = new User();
            $user->setLegacyId((int) $canonical['id']);
            $user->setEmail((string) $canonical['email']);
            $user->setPassword((string) $canonical['heslo']);
            $user->setNick($nick);
            $user->setFirstName($this->trimOrNull($canonical['jmeno'] ?? null));
            $user->setLastName($this->trimOrNull($canonical['prijmeni'] ?? null));
            $user->setCity($this->trimOrNull($canonical['mesto'] ?? null));
            $user->setBirthYear($canonical['rok_nar'] !== null ? (int) $canonical['rok_nar'] : null);
            $user->setPhone($this->trimOrNull($canonical['telefon'] ?? null));
            $user->setRoles($this->mapRoles((string) ($canonical['role'] ?? 'guest')));
            $user->setIsActive((int) $canonical['enabled'] === 1);
            $user->setIsVerified(true); // legacy users are considered verified

            $user->setLegacyMergedIds(array_map(fn ($r) => (int) $r['id'], $dropped));
            $user->setLegacyDataSnapshot([
                'canonical' => $this->scrubRow($canonical),
                'merged' => array_map(fn ($r) => $this->scrubRow($r), $dropped),
            ]);

            if (count($dropped) > 0) {
                $io->writeln(sprintf(
                    '  <fg=yellow>[MERGE]</> "%s" → keep uzivatel#%d (%s); drop: %s',
                    $email,
                    $canonical['id'],
                    $canonical['nick'] ?? '?',
                    implode(', ', array_map(fn ($r) => sprintf('#%d (%s)', $r['id'], $r['nick'] ?? '?'), $dropped))
                ));
                $merged += count($dropped);
            }

            if (!$dryRun) {
                $this->em->persist($user);
            }

            $imported++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s %d users (%d legacy rows merged into kept rows, %d skipped)',
            $dryRun ? 'Would import' : 'Imported',
            $imported,
            $merged,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $group
     * @return array<string, mixed>
     */
    private function pickCanonical(string $email, array $group): array
    {
        if (count($group) === 1) {
            return $group[0];
        }
        if (isset(self::CANONICAL_OVERRIDES[$email])) {
            $wantedId = self::CANONICAL_OVERRIDES[$email];
            foreach ($group as $row) {
                if ((int) $row['id'] === $wantedId) {
                    return $row;
                }
            }
        }
        // Default: oldest id wins
        usort($group, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        return $group[0];
    }

    /**
     * Returns a unique nick. If conflict, suffix with legacy id (e.g. "Tomáš#183").
     *
     * @param array<string, true> $usedNicks (mutated)
     */
    private function resolveNick(?string $nick, array &$usedNicks, SymfonyStyle $io, int $legacyId): ?string
    {
        $nick = $this->trimOrNull($nick);
        if ($nick === null) {
            return null;
        }
        if (!isset($usedNicks[$nick])) {
            $usedNicks[$nick] = true;
            return $nick;
        }
        $suffixed = $nick . '#' . $legacyId;
        $usedNicks[$suffixed] = true;
        $io->writeln(sprintf(
            '  <fg=yellow>[NICK-COLLISION]</> "%s" already taken; uzivatel#%d nick set to "%s"',
            $nick,
            $legacyId,
            $suffixed,
        ));
        return $suffixed;
    }

    /**
     * @return list<string>
     */
    private function mapRoles(string $legacyRole): array
    {
        return match (strtolower(trim($legacyRole))) {
            'admin' => ['ROLE_ADMIN'],
            default => ['ROLE_USER'],
        };
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Strip the password hash from snapshot — we already store it in `password`.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrubRow(array $row): array
    {
        unset($row['heslo']);
        return $row;
    }
}
