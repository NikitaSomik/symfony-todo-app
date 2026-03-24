<?php

declare(strict_types=1);

namespace App\Auth\Command;

use App\Auth\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth:purge-expired-refresh-tokens',
    description: 'Delete expired refresh tokens from the database.',
)]
final class PurgeExpiredRefreshTokensCommand extends Command
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->refreshTokenRepository->deleteExpired();

        $io->success(sprintf('Deleted %d expired refresh token(s).', $count));

        return self::SUCCESS;
    }
}
