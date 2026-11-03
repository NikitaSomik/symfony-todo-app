<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\LimitMode;
use App\Billing\Exception\PlanLimitExceededException;
use App\Workspace\Contract\PlanLimits;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Uid\Uuid;

/**
 * Releases the limits apart from the rules: off checks nothing, shadow records who would be
 * refused and refuses nobody, on refuses. Deleted with BILLING_LIMITS once on has held.
 */
#[AsDecorator(decorates: PlanLimits::class)]
#[WithMonologChannel('billing')]
final readonly class SwitchablePlanLimits implements PlanLimits
{
    public function __construct(
        #[AutowireDecorated]
        private PlanLimits $limits,
        #[Autowire(env: 'enum:App\Billing\Enum\LimitMode:BILLING_LIMITS')]
        private LimitMode $mode,
        private LoggerInterface $logger,
    ) {
    }

    public function ensureCanAddMember(Uuid $workspaceId, int $currentMembers): void
    {
        $this->apply(fn () => $this->limits->ensureCanAddMember($workspaceId, $currentMembers), ['workspace_id' => $workspaceId->toRfc4122()]);
    }

    public function ensureCanCreateTeamWorkspace(array $ownedTeamWorkspaceIds): void
    {
        $this->apply(fn () => $this->limits->ensureCanCreateTeamWorkspace($ownedTeamWorkspaceIds), []);
    }

    /**
     * @param callable(): void          $check
     * @param array<string, int|string> $context
     */
    private function apply(callable $check, array $context): void
    {
        if (LimitMode::OFF === $this->mode) {
            return;
        }

        if (LimitMode::ON === $this->mode) {
            $check();

            return;
        }

        try {
            $check();
        } catch (PlanLimitExceededException $limitReached) {
            $this->logger->info('Plan limit reached in shadow mode.', $context + $limitReached->errorMeta());
        }
    }
}
