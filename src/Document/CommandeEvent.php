<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "commande_events")]
#[MongoDB\Index(keys: ['createdAt' => 'desc'])]
#[MongoDB\Index(keys: ['menuId' => 'asc'])]
#[MongoDB\Index(keys: ['type' => 'asc'])]
class CommandeEvent
{
    #[MongoDB\Id]
    private $id;

    #[MongoDB\Field(type: 'int')]
    private int $commandeId;

    #[MongoDB\Field(type: 'string')]
    private string $type; 

    #[MongoDB\Field(type: 'string')]
    private string $statut;

    #[MongoDB\Field(type: 'int', nullable: true)]
    private ?int $menuId = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $menuTitre = null;

    #[MongoDB\Field(type: 'float', nullable: true)]
    private ?float $prixTotal = null;

    #[MongoDB\Field(type: 'int', nullable: true)]
    private ?int $userId = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $details = null;

    #[MongoDB\Field(type: 'date')]
    private \DateTime $createdAt;

    public function __construct(
        int $commandeId,
        string $type,
        string $statut,
        ?int $menuId = null,
        ?string $menuTitre = null,
        ?float $prixTotal = null,
        ?int $userId = null,
        ?string $details = null
    ) {
        $this->commandeId = $commandeId;
        $this->type = $type;
        $this->statut = $statut;
        $this->menuId = $menuId;
        $this->menuTitre = $menuTitre;
        $this->prixTotal = $prixTotal;
        $this->userId = $userId;
        $this->details = $details;
        $this->createdAt = new \DateTime();
    }

    public function getCommandeId(): int { return $this->commandeId; }
    public function getType(): string { return $this->type; }
    public function getStatut(): string { return $this->statut; }
    public function getMenuId(): ?int { return $this->menuId; }
    public function getMenuTitre(): ?string { return $this->menuTitre; }
    public function getPrixTotal(): ?float { return $this->prixTotal; }
    public function getUserId(): ?int { return $this->userId; }
    public function getDetails(): ?string { return $this->details; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
