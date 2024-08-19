<?php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Contact;

class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function findAllEmails(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.email')
            ->getQuery();

        return $qb->getArrayResult();
    }
}
