<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250903100811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, questionnaire_id INT NOT NULL, texte LONGTEXT NOT NULL, numero_ordre INT NOT NULL, INDEX IDX_B6F7494ECE07E8FF (questionnaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE questionnaire (id INT AUTO_INCREMENT NOT NULL, cree_par_id INT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, code_acces VARCHAR(10) NOT NULL, est_actif TINYINT(1) NOT NULL, est_demarre TINYINT(1) NOT NULL, score_passage INT NOT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_7A64DAF3CA1225C (code_acces), INDEX IDX_7A64DAFFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, texte LONGTEXT NOT NULL, est_correcte TINYINT(1) NOT NULL, numero_ordre INT NOT NULL, INDEX IDX_5FB6DEC71E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reponse_utilisateur (id INT AUTO_INCREMENT NOT NULL, tentative_questionnaire_id INT NOT NULL, question_id INT NOT NULL, reponse_id INT NOT NULL, date_reponse DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_14B756B67D542AB7 (tentative_questionnaire_id), INDEX IDX_14B756B61E27F6BF (question_id), INDEX IDX_14B756B6CF18BB82 (reponse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tentative_questionnaire (id INT AUTO_INCREMENT NOT NULL, questionnaire_id INT NOT NULL, utilisateur_id INT DEFAULT NULL, prenom_participant VARCHAR(255) NOT NULL, nom_participant VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', score INT DEFAULT NULL, nombre_total_questions INT DEFAULT NULL, INDEX IDX_A4CE5B25CE07E8FF (questionnaire_id), INDEX IDX_A4CE5B25FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, reset_token VARCHAR(255) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494ECE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('ALTER TABLE questionnaire ADD CONSTRAINT FK_7A64DAFFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC71E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE reponse_utilisateur ADD CONSTRAINT FK_14B756B67D542AB7 FOREIGN KEY (tentative_questionnaire_id) REFERENCES tentative_questionnaire (id)');
        $this->addSql('ALTER TABLE reponse_utilisateur ADD CONSTRAINT FK_14B756B61E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE reponse_utilisateur ADD CONSTRAINT FK_14B756B6CF18BB82 FOREIGN KEY (reponse_id) REFERENCES reponse (id)');
        $this->addSql('ALTER TABLE tentative_questionnaire ADD CONSTRAINT FK_A4CE5B25CE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('ALTER TABLE tentative_questionnaire ADD CONSTRAINT FK_A4CE5B25FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494ECE07E8FF');
        $this->addSql('ALTER TABLE questionnaire DROP FOREIGN KEY FK_7A64DAFFC29C013');
        $this->addSql('ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC71E27F6BF');
        $this->addSql('ALTER TABLE reponse_utilisateur DROP FOREIGN KEY FK_14B756B67D542AB7');
        $this->addSql('ALTER TABLE reponse_utilisateur DROP FOREIGN KEY FK_14B756B61E27F6BF');
        $this->addSql('ALTER TABLE reponse_utilisateur DROP FOREIGN KEY FK_14B756B6CF18BB82');
        $this->addSql('ALTER TABLE tentative_questionnaire DROP FOREIGN KEY FK_A4CE5B25CE07E8FF');
        $this->addSql('ALTER TABLE tentative_questionnaire DROP FOREIGN KEY FK_A4CE5B25FB88E14F');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql('DROP TABLE reponse');
        $this->addSql('DROP TABLE reponse_utilisateur');
        $this->addSql('DROP TABLE tentative_questionnaire');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
