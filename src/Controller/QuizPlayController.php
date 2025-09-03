<?php

namespace App\Controller;

use App\Service\QuizPlayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/quizzes/play')]
class QuizPlayController extends AbstractController
{
    public function __construct(
        private QuizPlayService $quizPlayService
    ) {
    }

    /**
     * Récupérer tous les questionnaires disponibles pour jouer
     */
    #[Route('', name: 'quiz_play_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailableQuizzes(): JsonResponse
    {
        $quizzes = $this->quizPlayService->getAvailableQuizzes();

        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitre(),
                'description' => $quiz->getDescription(),
                'accessCode' => $quiz->getCodeAcces(),
                'scorePassage' => $quiz->getScorePassage(),
                'createdAt' => $quiz->getDateCreation()?->format('c')
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer un questionnaire par son ID (avec questions)
     */
    #[Route('/{id}', name: 'quiz_play_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuiz(int $id): JsonResponse
    {
        $quiz = $this->quizPlayService->getPlayableQuiz($id);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou inactif'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->quizPlayService->serializePlayableQuiz($quiz));
    }

    /**
     * Récupérer un questionnaire par son code d'accès
     */
    #[Route('/code/{code}', name: 'quiz_play_by_code', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuizByCode(string $code): JsonResponse
    {
        $quiz = $this->quizPlayService->getQuizByCode($code);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé avec ce code ou inactif'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->quizPlayService->serializePlayableQuiz($quiz));
    }

    /**
     * Soumettre les réponses d'un quiz
     */
    #[Route('/{id}/submit', name: 'quiz_play_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitQuiz(int $id, Request $request): JsonResponse
    {
        $quiz = $this->quizPlayService->getPlayableQuiz($id);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou inactif'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['reponses'])) {
            return new JsonResponse(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();

        try {
            $result = $this->quizPlayService->submitQuiz(
                $quiz,
                $data['reponses'],
                $user,
                $data['prenomParticipant'] ?? null,
                $data['nomParticipant'] ?? null
            );

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de la soumission du quiz'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Récupérer les résultats d'une tentative
     */
    #[Route('/results/{tentativeId}', name: 'quiz_play_results', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getResults(int $tentativeId): JsonResponse
    {
        $user = $this->getUser();
        $results = $this->quizPlayService->getTentativeResults($tentativeId, $user);

        if (!$results) {
            return new JsonResponse(['error' => 'Résultats non trouvés ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($results);
    }
}