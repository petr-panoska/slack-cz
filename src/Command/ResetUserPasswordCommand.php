<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Generate a password-reset URL for a user (workaround when mailer is disabled)',
)]
final class ResetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::REQUIRED, 'User email or id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $needle = trim((string) $input->getArgument('user'));

        $user = ctype_digit($needle)
            ? $this->users->find((int) $needle)
            : $this->users->findOneBy(['email' => $needle]);

        if ($user === null) {
            $io->error("User not found: {$needle}");
            return Command::FAILURE;
        }

        $token = $this->resetPasswordHelper->generateResetToken($user);
        $url = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $token->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $io->success(sprintf('User #%d  %s', $user->getId(), $user->getEmail()));
        $io->writeln($url);

        return Command::SUCCESS;
    }
}
