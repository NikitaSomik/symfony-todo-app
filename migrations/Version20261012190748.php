<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012190748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attach every refresh token to a family and keep a used one marked instead of deleting it.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        // A token without a family has no place in the new model: everyone signs in again once.
        $this->addSql('TRUNCATE refresh_tokens');

        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT fk_9bace7e1a76ed395');
        $this->addSql('DROP INDEX idx_9bace7e1a76ed395');
        $this->addSql('ALTER TABLE refresh_tokens DROP user_id');
        $this->addSql('ALTER TABLE refresh_tokens ADD family_id UUID NOT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_refresh_tokens_family_id ON refresh_tokens (family_id)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT fk_refresh_tokens_family FOREIGN KEY (family_id) REFERENCES refresh_token_families (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('TRUNCATE refresh_tokens');

        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT fk_refresh_tokens_family');
        $this->addSql('DROP INDEX idx_refresh_tokens_family_id');
        $this->addSql('ALTER TABLE refresh_tokens DROP used_at');
        $this->addSql('ALTER TABLE refresh_tokens DROP family_id');
        $this->addSql('ALTER TABLE refresh_tokens ADD user_id INT NOT NULL');
        $this->addSql('CREATE INDEX idx_9bace7e1a76ed395 ON refresh_tokens (user_id)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT fk_9bace7e1a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
