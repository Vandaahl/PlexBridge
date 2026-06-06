<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606143302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE movie ADD COLUMN lid VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__movie AS SELECT id, title, original_title, imdb, plex_guid, year, letterboxd_id FROM movie');
        $this->addSql('DROP TABLE movie');
        $this->addSql('CREATE TABLE movie (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, original_title VARCHAR(255) DEFAULT NULL, imdb VARCHAR(255) NOT NULL, plex_guid VARCHAR(255) NOT NULL, year INTEGER DEFAULT NULL, letterboxd_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO movie (id, title, original_title, imdb, plex_guid, year, letterboxd_id) SELECT id, title, original_title, imdb, plex_guid, year, letterboxd_id FROM __temp__movie');
        $this->addSql('DROP TABLE __temp__movie');
    }
}
