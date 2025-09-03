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

    /**
     * Trouve les questionnaires créés par un utilisateur
     */
    public function findByCreator($user): array
    {
        return $this->findBy(['creePar' => $user], ['dateCreation' => 'DESC']);
    }

    /**
     * Trouve un questionnaire spécifique appartenant à un créateur
     */
    public function findOneByIdAndCreator(int $id, $user): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.id = :id')
            ->andWhere('q.creePar = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les questionnaires actifs disponibles pour jouer
     */
    public function findActiveQuizzes(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.estActif = true')
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un questionnaire actif par ID
     */
    public function findActiveQuizById(int $id): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.id = :id')
            ->andWhere('q.estActif = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un questionnaire actif par code d'accès
     */
    public function findActiveQuizByCode(string $code): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.codeAcces = :code')
            ->andWhere('q.estActif = true')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }
}