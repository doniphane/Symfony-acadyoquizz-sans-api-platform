<?php

namespace App\Repository;

use App\Entity\TentativeQuestionnaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TentativeQuestionnaire>
 *
 * @method TentativeQuestionnaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method TentativeQuestionnaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method TentativeQuestionnaire[]    findAll()
 * @method TentativeQuestionnaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TentativeQuestionnaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TentativeQuestionnaire::class);
    }

    public function save(TentativeQuestionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TentativeQuestionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les tentatives d'un questionnaire spécifique
     */
    public function findByQuestionnaire(int $questionnaireId): array
    {
        return $this->findBy(['questionnaire' => $questionnaireId]);
    }

    /**
     * Trouve toutes les tentatives d'un utilisateur spécifique
     */
    public function findByUtilisateur(int $utilisateurId): array
    {
        return $this->findBy(['utilisateur' => $utilisateurId]);
    }

    /**
     * Trouve les tentatives avec leurs réponses utilisateur
     */
    public function findWithReponsesUtilisateur(): array
    {
        return $this->createQueryBuilder('tq')
            ->leftJoin('tq.reponsesUtilisateur', 'ru')
            ->addSelect('ru')
            ->orderBy('tq.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tentatives d'un questionnaire avec leurs réponses
     */
    public function findByQuestionnaireWithReponses(int $questionnaireId): array
    {
        return $this->createQueryBuilder('tq')
            ->leftJoin('tq.reponsesUtilisateur', 'ru')
            ->addSelect('ru')
            ->where('tq.questionnaire = :questionnaireId')
            ->setParameter('questionnaireId', $questionnaireId)
            ->orderBy('tq.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithFiltersAndUser(?int $questionnaireId = null, $user = null, bool $isAdmin = false): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.questionnaire', 'q')
            ->leftJoin('t.utilisateur', 'u');

        if ($questionnaireId) {
            $queryBuilder->where('q.id = :questionnaireId')
                ->setParameter('questionnaireId', $questionnaireId);
        }

        if (!$isAdmin && $user) {
            $queryBuilder->andWhere('q.creePar = :user')
                ->setParameter('user', $user);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Trouve les tentatives d'un utilisateur avec les détails du questionnaire
     */
    public function findByUserWithQuestionnaire($user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.questionnaire', 'q')
            ->where('t.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une tentative avec vérification d'appartenance à l'utilisateur
     */
    public function findOneByIdAndUser(int $id, $user): ?TentativeQuestionnaire
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->andWhere('t.utilisateur = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}