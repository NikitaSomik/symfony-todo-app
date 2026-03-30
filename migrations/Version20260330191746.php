<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330191746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cancellation_reason to tasks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD cancellation_reason VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks DROP cancellation_reason');
    }
}
