<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920094355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop leftover DC2Type column comments on task_status_changes.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        // DBAL 4 no longer writes or reads these comments, they only keep the schema out of sync.
        $this->addSql("COMMENT ON COLUMN task_status_changes.from_status IS ''");
        $this->addSql("COMMENT ON COLUMN task_status_changes.to_status IS ''");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql("COMMENT ON COLUMN task_status_changes.from_status IS '(DC2Type:App\\Task\\Enum\\TaskStatus)'");
        $this->addSql("COMMENT ON COLUMN task_status_changes.to_status IS '(DC2Type:App\\Task\\Enum\\TaskStatus)'");
    }
}
