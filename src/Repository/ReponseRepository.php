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

    /**
     * Trouve les réponses avec filtres pour question et utilisateur (pour admin/non-admin)
     */
    public function findWithFilters(?int $questionId = null, $user = null, bool $isAdmin = false): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->leftJoin('r.question', 'q')
            ->leftJoin('q.questionnaire', 'qt');

        if ($questionId) {
            $queryBuilder->where('q.id = :questionId')
                ->setParameter('questionId', $questionId);
        }

        // Si l'utilisateur n'est pas admin, ne voir que les réponses de ses questionnaires
        if (!$isAdmin && $user) {
            $queryBuilder->andWhere('qt.creePar = :user')
                ->setParameter('user', $user);
        }

        return $queryBuilder->orderBy('r.numeroOrdre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le numéro d'ordre maximum pour une question donnée
     */
    public function findMaxOrderByQuestion($question): ?int
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.numeroOrdre)')
            ->where('r.question = :question')
            ->setParameter('question', $question)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : null;
    }
}