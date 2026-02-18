<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209212252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande_statut_historique (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(50) NOT NULL, changed_at DATETIME NOT NULL, commande_id INT NOT NULL, INDEX IDX_634BFC7582EA2E54 (commande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commande_statut_historique ADD CONSTRAINT FK_634BFC7582EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande_statut_historique DROP FOREIGN KEY FK_634BFC7582EA2E54');
        $this->addSql('DROP TABLE commande_statut_historique');
    }
}
