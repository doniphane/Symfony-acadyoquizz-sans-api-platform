<?php

namespace App\Repository;

use App\Entity\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 *
 * @method Reponse|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reponse|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reponse[]    findAll()
 * @method Reponse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    public function save(Reponse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reponse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les réponses d'une question spécifique
     */
    public function findByQuestion(int $questionId): array
    {
        return $this->findBy(['question' => $questionId], ['numeroOrdre' => 'ASC']);
    }

    /**
     * Trouve toutes les réponses correctes d'une question
     */
    public function findCorrectesByQuestion(int $questionId): array
    {
        return $this->findBy(['question' => $questionId, 'estCorrecte' => true]);
    }

    /**
     * Trouve toutes les réponses incorrectes d'une question
     */
    public function findIncorrectesByQuestion(int $questionId): array
    {
        return $this->findBy(['question' => $questionId, 'estCorrecte' => false]);
    }

    /**
     * Trouve les réponses ordonnées par numéro d'ordre
     */
    public function findOrderedByQuestion(int $questionId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.question = :questionId')
            ->setParameter('questionId', $questionId)
            ->orderBy('r.numeroOrdre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}