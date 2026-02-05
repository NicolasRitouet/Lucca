<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204135631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lucca_minute_control DROP FOREIGN KEY `FK_3C19C5C3162CB942`');
        $this->addSql('DROP INDEX UNIQ_3C19C5C3162CB942 ON lucca_minute_control');
        $this->addSql('ALTER TABLE lucca_minute_control DROP folder_id');
        $this->addSql('ALTER TABLE lucca_minute_folder DROP FOREIGN KEY `FK_952C706932BEC70E`');
        $this->addSql('ALTER TABLE lucca_minute_folder ADD CONSTRAINT FK_952C706932BEC70E FOREIGN KEY (control_id) REFERENCES lucca_minute_control (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lucca_minute_control ADD folder_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lucca_minute_control ADD CONSTRAINT `FK_3C19C5C3162CB942` FOREIGN KEY (folder_id) REFERENCES lucca_minute_folder (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3C19C5C3162CB942 ON lucca_minute_control (folder_id)');
        $this->addSql('ALTER TABLE lucca_minute_folder DROP FOREIGN KEY FK_952C706932BEC70E');
        $this->addSql('ALTER TABLE lucca_minute_folder ADD CONSTRAINT `FK_952C706932BEC70E` FOREIGN KEY (control_id) REFERENCES lucca_minute_control (id)');
    }
}
