<?php

namespace App\Controller;

use App\Service\QuizHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/quizzes/history')]
class QuizHistoryController extends AbstractController
{
    public function __construct(
        private QuizHistoryService $quizHistoryService
    ) {
    }

    /**
     * Récupérer l'historique des tentatives de l'utilisateur
     */
    #[Route('', name: 'quiz_history_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyAttempts(): JsonResponse
    {
        $user = $this->getUser();
        $attempts = $this->quizHistoryService->getUserAttempts($user);

        return new JsonResponse($attempts);
    }

    /**
     * Récupérer les détails d'une tentative spécifique
     */
    #[Route('/{attemptId}', name: 'quiz_history_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAttemptDetails(int $attemptId): JsonResponse
    {
        $user = $this->getUser();
        $details = $this->quizHistoryService->getAttemptDetails($attemptId, $user);

        if (!$details) {
            return new JsonResponse(['error' => 'Tentative non trouvée ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($details);
    }

    /**
     * Récupérer les statistiques de l'utilisateur
     */
    #[Route('/stats', name: 'quiz_history_stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyStats(): JsonResponse
    {
        $user = $this->getUser();
        $stats = $this->quizHistoryService->getUserStats($user);

        return new JsonResponse($stats);
    }
}