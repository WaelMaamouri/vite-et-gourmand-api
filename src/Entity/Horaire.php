<?php

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HoraireRepository::class)]
class Horaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $jour = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $ouverture = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $fermeture = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(string $jour): static
    {
        $this->jour = $jour;

        return $this;
    }

    public function getOuverture(): ?\DateTime
    {
        return $this->ouverture;
    }

    public function setOuverture(\DateTime $ouverture): static
    {
        $this->ouverture = $ouverture;

        return $this;
    }

    public function getFermeture(): ?\DateTime
    {
        return $this->fermeture;
    }

    public function setFermeture(\DateTime $fermeture): static
    {
        $this->fermeture = $fermeture;

        return $this;
    }
}
