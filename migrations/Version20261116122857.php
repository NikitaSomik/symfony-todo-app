<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116122857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Key the subscriptions by their Stripe id and give them a status; a workspace has at most one that is not canceled.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_pkey');
        $this->addSql('ALTER TABLE subscriptions ADD stripe_subscription_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD PRIMARY KEY (stripe_subscription_id)');
        $this->addSql('CREATE INDEX idx_subscriptions_workspace_id ON subscriptions (workspace_id)');
        $this->addSql("CREATE UNIQUE INDEX uniq_subscriptions_workspace_id_not_canceled ON subscriptions (workspace_id) WHERE status <> 'canceled'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX uniq_subscriptions_workspace_id_not_canceled');
        $this->addSql('DROP INDEX idx_subscriptions_workspace_id');
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_pkey');
        $this->addSql('ALTER TABLE subscriptions DROP stripe_subscription_id');
        $this->addSql('ALTER TABLE subscriptions DROP status');
        $this->addSql('ALTER TABLE subscriptions ADD PRIMARY KEY (workspace_id)');
    }
}
