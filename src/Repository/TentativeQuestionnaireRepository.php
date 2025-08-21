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
}