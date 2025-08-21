<?php

namespace App\Repository;

use App\Entity\Questionnaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Questionnaire>
 *
 * @method Questionnaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Questionnaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Questionnaire[]    findAll()
 * @method Questionnaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuestionnaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Questionnaire::class);
    }

    public function save(Questionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Questionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve un questionnaire par son code d'accès
     */
    public function findByCodeAcces(string $codeAcces): ?Questionnaire
    {
        return $this->findOneBy(['codeAcces' => $codeAcces]);
    }

    /**
     * Trouve tous les questionnaires actifs
     */
    public function findActifs(): array
    {
        return $this->findBy(['estActif' => true]);
    }

    /**
     * Trouve tous les questionnaires démarrés
     */
    public function findDemarres(): array
    {
        return $this->findBy(['estDemarre' => true]);
    }

    /**
     * Trouve tous les questionnaires créés par un utilisateur spécifique
     */
    public function findByUtilisateur(int $utilisateurId): array
    {
        return $this->findBy(['creePar' => $utilisateurId]);
    }

    /**
     * Trouve les questionnaires avec leurs questions
     */
    public function findWithQuestions(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.questions', 'questions')
            ->addSelect('questions')
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}