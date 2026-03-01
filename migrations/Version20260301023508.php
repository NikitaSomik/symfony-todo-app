<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260301023508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD user_id INT NOT NULL');
        $this->addSql('
            ALTER TABLE tasks
                ADD CONSTRAINT fk_tasks_user_id
                FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE
        ');
        $this->addSql('CREATE INDEX idx_tasks_user_id ON tasks (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT fk_tasks_user_id');
        $this->addSql('DROP INDEX idx_tasks_user_id');
        $this->addSql('ALTER TABLE tasks DROP user_id');
    }
}
