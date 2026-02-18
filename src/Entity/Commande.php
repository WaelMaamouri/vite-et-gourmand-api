<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTE = 'accepte';
    public const STATUT_PREPARATION = 'preparation';
    public const STATUT_LIVRAISON = 'livraison';
    public const STATUT_LIVRE = 'livre';
    public const STATUT_ATTENTE_MATERIEL = 'attente_materiel';
    public const STATUT_TERMINEE = 'terminee';
    public const STATUT_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $datePrestation = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $heureLivraison = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $adressePrestation = null;

    #[ORM\Column(length: 120)]
    private ?string $villePrestation = null;

    #[ORM\Column]
    private ?float $kmParcourus = null;

    #[ORM\Column]
    private ?int $nbPersonnes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixTotal = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'commande')]
    private Collection $avis;

    /**
     * @var Collection<int, CommandeStatutHistorique>
     */
    #[ORM\OneToMany(targetEntity: CommandeStatutHistorique::class, mappedBy: 'commande')]
    private Collection $commandeStatutHistoriques;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifAnnulation = null;

    public function __construct()
    {
        $this->avis = new ArrayCollection();
        $this->commandeStatutHistoriques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getDatePrestation(): ?\DateTime
    {
        return $this->datePrestation;
    }

    public function setDatePrestation(\DateTime $datePrestation): static
    {
        $this->datePrestation = $datePrestation;

        return $this;
    }

    public function getHeureLivraison(): ?\DateTime
    {
        return $this->heureLivraison;
    }

    public function setHeureLivraison(\DateTime $heureLivraison): static
    {
        $this->heureLivraison = $heureLivraison;

        return $this;
    }

    public function getAdressePrestation(): ?string
    {
        return $this->adressePrestation;
    }

    public function setAdressePrestation(string $adressePrestation): static
    {
        $this->adressePrestation = $adressePrestation;

        return $this;
    }

    public function getVillePrestation(): ?string
    {
        return $this->villePrestation;
    }

    public function setVillePrestation(string $villePrestation): static
    {
        $this->villePrestation = $villePrestation;

        return $this;
    }

    public function getKmParcourus(): ?float
    {
        return $this->kmParcourus;
    }

    public function setKmParcourus(float $kmParcourus): static
    {
        $this->kmParcourus = $kmParcourus;

        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nbPersonnes;
    }

    public function setNbPersonnes(int $nbPersonnes): static
    {
        $this->nbPersonnes = $nbPersonnes;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(string $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        return $this->avis;
    }

    public function addAvi(Avis $avi): static
    {
        if (!$this->avis->contains($avi)) {
            $this->avis->add($avi);
            $avi->setCommande($this);
        }

        return $this;
    }

    public function removeAvi(Avis $avi): static
    {
        if ($this->avis->removeElement($avi)) {
            // set the owning side to null (unless already changed)
            if ($avi->getCommande() === $this) {
                $avi->setCommande(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CommandeStatutHistorique>
     */
    public function getCommandeStatutHistoriques(): Collection
    {
        return $this->commandeStatutHistoriques;
    }

    public function addCommandeStatutHistorique(CommandeStatutHistorique $commandeStatutHistorique): static
    {
        if (!$this->commandeStatutHistoriques->contains($commandeStatutHistorique)) {
            $this->commandeStatutHistoriques->add($commandeStatutHistorique);
            $commandeStatutHistorique->setCommande($this);
        }

        return $this;
    }

    public function removeCommandeStatutHistorique(CommandeStatutHistorique $commandeStatutHistorique): static
    {
        if ($this->commandeStatutHistoriques->removeElement($commandeStatutHistorique)) {
            // set the owning side to null (unless already changed)
            if ($commandeStatutHistorique->getCommande() === $this) {
                $commandeStatutHistorique->setCommande(null);
            }
        }

        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?string $motifAnnulation): static
    {
        $this->motifAnnulation = $motifAnnulation;

        return $this;
    }
}
