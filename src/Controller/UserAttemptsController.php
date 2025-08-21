<?php

namespace App\Controller;

use App\Entity\TentativeQuestionnaire;
use App\Entity\Question;
use App\Entity\ReponseUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/my-attempts')]
class UserAttemptsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'user_my_attempts_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMyAttempts(): JsonResponse
    {
        $user = $this->getUser();

        $attempts = $this->entityManager->getRepository(TentativeQuestionnaire::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.questionnaire', 'q')
            ->where('t.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($attempts as $attempt) {
            $data[] = $this->serializeAttemptForUser($attempt);
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'user_my_attempts_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyAttemptDetails(int $id): JsonResponse
    {
        $attempt = $this->entityManager->getRepository(TentativeQuestionnaire::class)->find($id);

        if (!$attempt) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tentative appartient à l'utilisateur connecté
        if ($attempt->getUtilisateur() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $reponsesUtilisateur = $this->entityManager->getRepository(ReponseUtilisateur::class)
            ->createQueryBuilder('ru')
            ->leftJoin('ru.question', 'qu')
            ->leftJoin('ru.reponse', 'r')
            ->where('ru.tentativeQuestionnaire = :attempt')
            ->setParameter('attempt', $attempt)
            ->orderBy('qu.numeroOrdre', 'ASC')
            ->getQuery()
            ->getResult();

        $details = [];
        foreach ($reponsesUtilisateur as $ru) {
            $question = $ru->getQuestion();
            $selectedAnswer = $ru->getReponse();
            $correctAnswerText = $this->getCorrectAnswerText($question);

            $details[] = [
                'questionId' => (string) $question->getId(),
                'questionTexte' => $question->getTexte(),
                'reponseUtilisateurTexte' => $selectedAnswer?->getTexte() ?? '',
                'reponseCorrecteTexte' => $correctAnswerText ?? '',
                'estCorrecte' => $selectedAnswer?->isCorrect() ?? false,
            ];
        }

        return new JsonResponse([
            'reponsesDetails' => $details,
        ]);
    }

    private function serializeAttemptForUser(TentativeQuestionnaire $t): array
    {
        $questionnaire = $t->getQuestionnaire();
        $dateDebut = $t->getDateDebut();


        $pourcentage = method_exists($t, 'getPourcentage')
            ? $t->getPourcentage()
            : ($t->getNombreTotalQuestions() > 0
                ? round(($t->getScore() / $t->getNombreTotalQuestions()) * 100, 2)
                : 0);

        return [
            'id' => $t->getId(),
            'questionnaireTitre' => $questionnaire?->getTitre() ?? '',
            'questionnaireCode' => $questionnaire?->getCodeAcces() ?? '',
            'date' => $dateDebut?->format('Y-m-d') ?? '',
            'heure' => $dateDebut?->format('H:i') ?? '',
            'score' => $t->getScore() ?? 0,
            'nombreTotalQuestions' => $t->getNombreTotalQuestions() ?? 0,
            'pourcentage' => $pourcentage,
            'estReussi' => $questionnaire ? ($pourcentage >= $questionnaire->getScorePassage()) : false,
        ];
    }

    private function getCorrectAnswerText(Question $question): ?string
    {
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                return $reponse->getTexte();
            }
        }
        return null;
    }
}