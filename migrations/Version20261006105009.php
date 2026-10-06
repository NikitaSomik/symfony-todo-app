<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006105009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the creator of a task as a plain id, without a foreign key to users.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT fk_tasks_user_id');
        $this->addSql('DROP INDEX idx_tasks_user_id');
        $this->addSql('ALTER TABLE tasks RENAME COLUMN user_id TO creator_id');
        $this->addSql('CREATE INDEX idx_tasks_creator_id ON tasks (creator_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX idx_tasks_creator_id');
        $this->addSql('ALTER TABLE tasks RENAME COLUMN creator_id TO user_id');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_user_id FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_tasks_user_id ON tasks (user_id)');
    }
}
