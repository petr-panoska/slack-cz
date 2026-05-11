<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:list',
    description: 'List users (id, email, nick, verified, active)',
)]
final class UserListCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Substring filter on email or nick')
            ->addOption('unverified', null, InputOption::VALUE_NONE, 'Only users with isVerified=false');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $qb = $this->users->createQueryBuilder('u')->orderBy('u.id', 'ASC');

        if ($search = $input->getOption('search')) {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.nick) LIKE :q')
                ->setParameter('q', '%'.strtolower((string) $search).'%');
        }
        if ($input->getOption('unverified')) {
            $qb->andWhere('u.isVerified = false');
        }

        /** @var \App\Entity\User[] $list */
        $list = $qb->getQuery()->getResult();

        if ($list === []) {
            $io->warning('No users matched.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($list as $u) {
            $rows[] = [
                $u->getId(),
                $u->getEmail(),
                $u->getNick() ?? '—',
                $u->isVerified() ? 'yes' : 'no',
                $u->isActive() ? 'yes' : 'no',
            ];
        }
        $io->table(['id', 'email', 'nick', 'verified', 'active'], $rows);
        $io->writeln(sprintf('<info>%d user(s)</info>', count($list)));

        return Command::SUCCESS;
    }
}
