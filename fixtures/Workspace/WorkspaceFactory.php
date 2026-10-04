<?php

declare(strict_types=1);

namespace App\Fixtures\Workspace;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<Workspace>
 */
final class WorkspaceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Workspace::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => ucfirst(self::faker()->word()).' team',
            // Created at once, not together with the workspace: the workspace names its owner by id.
            'owner' => lazy(static fn (): User => UserFactory::createOne()),
            'members' => [],
        ];
    }

    /**
     * @param array<int, WorkspaceRole> $roles role by user id
     */
    public function withMembers(array $roles): static
    {
        return $this->with(['members' => $roles]);
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(static function (array $attributes): Workspace {
            $owner = $attributes['owner'];
            \assert($owner instanceof User);
            $at = new \DateTimeImmutable();

            $workspace = new Workspace(Uuid::v7(), $attributes['name'], $owner->id(), $at);

            foreach ($attributes['members'] as $userId => $role) {
                $workspace->addMember($userId, $role, $at);
            }

            return $workspace;
        });
    }
}
