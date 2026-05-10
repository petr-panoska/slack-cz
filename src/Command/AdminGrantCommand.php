<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin:grant',
    description: 'Grant or revoke ROLE_ADMIN on a user identified by email',
)]
final class AdminGrantCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Remove ROLE_ADMIN instead of granting it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $revoke = (bool) $input->getOption('revoke');

        $user = $this->users->findOneBy(['email' => $email]);
        if ($user === null) {
            $io->error("User not found: {$email}");
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if ($revoke) {
            $roles = array_values(array_filter($roles, fn (string $r): bool => $r !== 'ROLE_ADMIN'));
        } elseif (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }

        // Strip the auto-injected ROLE_USER before persisting — User::getRoles() re-adds it.
        $persistRoles = array_values(array_filter($roles, fn (string $r): bool => $r !== 'ROLE_USER'));
        $user->setRoles($persistRoles);
        $this->em->flush();

        $io->success(sprintf(
            '%s ROLE_ADMIN %s %s. Current roles: %s',
            $revoke ? 'Revoked' : 'Granted',
            $revoke ? 'from' : 'to',
            $email,
            implode(', ', $user->getRoles()),
        ));

        return Command::SUCCESS;
    }
}
