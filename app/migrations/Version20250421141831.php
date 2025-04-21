<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250421141831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE episode (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, imdb VARCHAR(255) DEFAULT NULL, plex_guid VARCHAR(255) NOT NULL, year INTEGER DEFAULT NULL)');
        $this->addSql('CREATE TABLE event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, movie_id INTEGER DEFAULT NULL, episode_id INTEGER DEFAULT NULL, rating DOUBLE PRECISION DEFAULT NULL, date DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , status_trakt VARCHAR(255) DEFAULT NULL, event VARCHAR(255) NOT NULL, plex_user VARCHAR(255) NOT NULL, status_letterboxd VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_3BAE0AA78F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3BAE0AA7362B62A0 FOREIGN KEY (episode_id) REFERENCES episode (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA78F93B6FC ON event (movie_id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7362B62A0 ON event (episode_id)');
        $this->addSql('CREATE TABLE movie (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, original_title VARCHAR(255) DEFAULT NULL, imdb VARCHAR(255) NOT NULL, plex_guid VARCHAR(255) NOT NULL, year INTEGER DEFAULT NULL, letterboxd_id INTEGER DEFAULT NULL)');
        $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, setting_key VARCHAR(255) NOT NULL, setting_value CLOB NOT NULL --(DC2Type:json)
        )');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE episode');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE movie');
        $this->addSql('DROP TABLE setting');
    }
}
