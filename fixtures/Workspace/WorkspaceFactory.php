<?php

declare(strict_types=1);

namespace App\Fixtures\Workspace;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
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
     * @param list<array{User, WorkspaceRole}> $members
     */
    public function withMembers(array $members): static
    {
        return $this->with(['members' => $members]);
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(static function (array $attributes): Workspace {
            $owner = $attributes['owner'];
            \assert($owner instanceof User);
            $at = new \DateTimeImmutable();

            $workspace = new Workspace(Uuid::v7(), $attributes['name'], $owner, $at);

            foreach ($attributes['members'] as [$user, $role]) {
                $workspace->addMember($user, $role, $at);
            }

            return $workspace;
        });
    }
}
