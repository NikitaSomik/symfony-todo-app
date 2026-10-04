<?php

declare(strict_types=1);

namespace App\Workspace\Persistence;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * A membership names its user by id, not by a relation to another module's entity, so Doctrine
 * knows of no foreign key. A membership of a user that is gone means nothing, though: the key is
 * added to the schema here, and migrations and schema validation see it like any other.
 *
 * It listens to the whole schema, not to the table: Doctrine attaches the keys of real
 * associations after the per-table event, to the table object it created, and a table replaced
 * in that event loses them.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class MembershipUserForeignKey
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $args->setSchema(
            $args->getSchema()->edit()
                ->modifyTableByUnquotedName('workspace_members', static function (TableEditor $table): void {
                    $table->addForeignKeyConstraint(
                        ForeignKeyConstraint::editor()
                            ->setUnquotedName('fk_workspace_members_user')
                            ->setUnquotedReferencingColumnNames('user_id')
                            ->setUnquotedReferencedTableName('users')
                            ->setUnquotedReferencedColumnNames('id')
                            ->setOnDeleteAction(ReferentialAction::CASCADE)
                            ->create(),
                    );
                })
                ->create(),
        );
    }
}
