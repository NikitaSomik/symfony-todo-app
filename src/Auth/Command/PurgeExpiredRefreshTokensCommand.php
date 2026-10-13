<?php

declare(strict_types=1);

namespace App\Auth\Command;

use App\Auth\Repository\RefreshTokenFamilyRepository;
use App\Auth\Repository\RefreshTokenRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth:purge-expired-refresh-tokens',
    description: 'Delete ended sessions and expired refresh tokens from the database.',
)]
final class PurgeExpiredRefreshTokensCommand extends Command
{
    public function __construct(
        private readonly RefreshTokenFamilyRepository $familyRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = $this->clock->now();
        $families = $this->familyRepository->deleteEnded($now);
        $tokens = $this->refreshTokenRepository->deleteExpired($now);

        $io->success(sprintf('Deleted %d ended session(s) and %d expired refresh token(s).', $families, $tokens));

        return self::SUCCESS;
    }
}
