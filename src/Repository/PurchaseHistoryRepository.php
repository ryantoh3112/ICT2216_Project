<?php
// src/Repository/PurchaseHistoryRepository.php

namespace App\Repository;

use App\Entity\PurchaseHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PurchaseHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseHistory::class);
    }

    // add custom methods if you need them…
}
