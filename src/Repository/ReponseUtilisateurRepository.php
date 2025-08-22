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

    /**
     * Trouve les réponses d'une tentative avec les détails des questions et réponses, ordonnées par numéro d'ordre
     */
    public function findByTentativeWithDetailsOrderedByQuestionOrder($tentative): array
    {
        return $this->createQueryBuilder('ru')
            ->leftJoin('ru.question', 'qu')
            ->leftJoin('ru.reponse', 'r')
            ->where('ru.tentativeQuestionnaire = :attempt')
            ->setParameter('attempt', $tentative)
            ->orderBy('qu.numeroOrdre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réponses avec filtres pour tentative, question et utilisateur (pour admin/non-admin)
     */
    public function findWithFilters(?int $tentativeId = null, ?int $questionId = null, $user = null, bool $isAdmin = false): array
    {
        $queryBuilder = $this->createQueryBuilder('ru')
            ->leftJoin('ru.tentativeQuestionnaire', 't')
            ->leftJoin('t.questionnaire', 'q')
            ->leftJoin('ru.question', 'qu')
            ->leftJoin('ru.reponse', 'r');

        if ($tentativeId) {
            $queryBuilder->andWhere('t.id = :tentativeId')
                ->setParameter('tentativeId', $tentativeId);
        }

        if ($questionId) {
            $queryBuilder->andWhere('qu.id = :questionId')
                ->setParameter('questionId', $questionId);
        }

        // Si l'utilisateur n'est pas admin, ne voir que les réponses de ses questionnaires
        if (!$isAdmin && $user) {
            $queryBuilder->andWhere('q.creePar = :user')
                ->setParameter('user', $user);
        }

        return $queryBuilder->orderBy('ru.dateReponse', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une réponse utilisateur avec vérification d'accès utilisateur
     */
    public function findOneWithAccessCheck(int $id, $user = null, bool $isAdmin = false): ?ReponseUtilisateur
    {
        $queryBuilder = $this->createQueryBuilder('ru')
            ->leftJoin('ru.tentativeQuestionnaire', 't')
            ->leftJoin('t.questionnaire', 'q')
            ->where('ru.id = :id')
            ->setParameter('id', $id);

        if (!$isAdmin && $user) {
            $queryBuilder->andWhere('q.creePar = :user')
                ->setParameter('user', $user);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}