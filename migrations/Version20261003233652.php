<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003233652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the actor of an audit record as a plain id, without a foreign key to users.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT fk_activity_logs_user');
        $this->addSql('DROP INDEX idx_audit_logs_user_id');
        $this->addSql('ALTER TABLE audit_logs RENAME COLUMN user_id TO actor_id');
        $this->addSql('CREATE INDEX idx_audit_logs_actor_id ON audit_logs (actor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX idx_audit_logs_actor_id');
        $this->addSql('ALTER TABLE audit_logs RENAME COLUMN actor_id TO user_id');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id)');
    }
}
