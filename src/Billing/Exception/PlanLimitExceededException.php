<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Billing\Enum\Plan;
use App\Shared\Http\ActionableException;

final class PlanLimitExceededException extends \DomainException implements ActionableException
{
    /** @param array<string, int|string> $meta */
    private function __construct(
        string $message,
        private readonly string $title,
        private readonly array $meta,
    ) {
        parent::__construct($message);
    }

    public static function forMembers(Plan $plan, int $used): self
    {
        $meta = ['plan' => $plan->value, 'limit' => $plan->memberLimit(), 'used' => $used];
        $next = $plan->next();

        if (null === $next) {
            return new self(
                sprintf('The %s plan includes %d members. Contact us to add more.', $plan->label(), $plan->memberLimit()),
                'Member limit reached',
                $meta,
            );
        }

        return new self(
            sprintf('The %s plan includes %d members. Move to %s to add up to %d.', $plan->label(), $plan->memberLimit(), $next->label(), $next->memberLimit()),
            'Member limit reached',
            $meta + ['next_plan' => $next->value],
        );
    }

    public static function forTeamWorkspaces(int $used): self
    {
        return new self(
            'The Free plan includes one team workspace. Move it to a paid plan to create another.',
            'Team workspace limit reached',
            ['plan' => Plan::FREE->value, 'limit' => Plan::TEAM_WORKSPACES_ON_FREE, 'used' => $used],
        );
    }

    public function errorCode(): string
    {
        return 'plan_limit_exceeded';
    }

    public function errorTitle(): string
    {
        return $this->title;
    }

    public function errorMeta(): array
    {
        return $this->meta;
    }
}
