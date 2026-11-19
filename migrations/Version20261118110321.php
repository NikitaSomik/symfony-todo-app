<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118110321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tie each subscription to its payer: a foreign key from subscriptions.workspace_id to payers.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE INDEX IDX_4778A0182D40A1F ON subscriptions (workspace_id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A0182D40A1F FOREIGN KEY (workspace_id) REFERENCES payers (workspace_id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A0182D40A1F');
        $this->addSql('DROP INDEX IDX_4778A0182D40A1F');
    }
}
