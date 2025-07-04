<?php
// src/Repository/CaptchaRepository.php

namespace App\Repository;

use App\Entity\Captcha;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CaptchaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Captcha::class);
    }

    // add custom query methods here if you like
}
