<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107201137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the payers of workspaces: the Stripe customer each workspace pays as.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE TABLE payers (workspace_id UUID NOT NULL, stripe_customer_id VARCHAR(255) NOT NULL, PRIMARY KEY (workspace_id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_929C2FB0708DC647 ON payers (stripe_customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP TABLE payers');
    }
}
