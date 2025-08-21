<?php

namespace App\Repository;

use App\Entity\ReponseUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReponseUtilisateur>
 *
 * @method ReponseUtilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReponseUtilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReponseUtilisateur[]    findAll()
 * @method ReponseUtilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReponseUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReponseUtilisateur::class);
    }

    public function save(ReponseUtilisateur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReponseUtilisateur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les réponses d'une tentative spécifique
     */
    public function findByTentativeQuestionnaire(int $tentativeQuestionnaireId): array
    {
        return $this->findBy(['tentativeQuestionnaire' => $tentativeQuestionnaireId]);
    }

    /**
     * Trouve toutes les réponses d'une question spécifique
     */
    public function findByQuestion(int $questionId): array
    {
        return $this->findBy(['question' => $questionId]);
    }

    /**
     * Trouve toutes les réponses correctes d'une tentative
     */
    public function findCorrectesByTentativeQuestionnaire(int $tentativeQuestionnaireId): array
    {
        return $this->createQueryBuilder('ru')
            ->leftJoin('ru.reponse', 'r')
            ->where('ru.tentativeQuestionnaire = :tentativeQuestionnaireId')
            ->andWhere('r.isCorrect = :isCorrect')
            ->setParameter('tentativeQuestionnaireId', $tentativeQuestionnaireId)
            ->setParameter('isCorrect', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les réponses incorrectes d'une tentative
     */
    public function findIncorrectesByTentativeQuestionnaire(int $tentativeQuestionnaireId): array
    {
        return $this->createQueryBuilder('ru')
            ->leftJoin('ru.reponse', 'r')
            ->where('ru.tentativeQuestionnaire = :tentativeQuestionnaireId')
            ->andWhere('r.isCorrect = :isCorrect')
            ->setParameter('tentativeQuestionnaireId', $tentativeQuestionnaireId)
            ->setParameter('isCorrect', false)
            ->getQuery()
            ->getResult();
    }
}