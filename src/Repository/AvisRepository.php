<?php

namespace App\Repository;

use App\Entity\Avis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function findLatestValidated(int $limit = 5): array
    {
    return $this->createQueryBuilder('a')
        ->andWhere('a.valide = :ok')
        ->setParameter('ok', true)
        ->orderBy('a.createdAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
    }

}
