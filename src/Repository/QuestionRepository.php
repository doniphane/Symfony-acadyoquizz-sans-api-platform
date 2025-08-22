<?php

namespace App\Repository;

use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * Trouve le numéro d'ordre maximum pour un questionnaire donné
     */
    public function findMaxOrderByQuestionnaire($questionnaire): ?int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.numeroOrdre)')
            ->where('q.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : null;
    }
}
