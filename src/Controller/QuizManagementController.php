<?php

namespace App\Controller;

use App\Service\QuizManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/quizzes')]
class QuizManagementController extends AbstractController
{
    public function __construct(
        private QuizManagementService $quizManagementService
    ) {
    }

    /**
     * Récupérer tous les questionnaires créés par l'utilisateur connecté
     */
    #[Route('', name: 'quiz_management_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(): JsonResponse
    {
        $user = $this->getUser();
        $quizzes = $this->quizManagementService->getUserQuizzes($user);

        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = $this->quizManagementService->serializeQuiz($quiz);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer un questionnaire spécifique pour modification
     */
    #[Route('/{id}', name: 'quiz_management_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->quizManagementService->serializeQuiz($quiz, true));
    }

    /**
     * Créer un nouveau questionnaire
     */
    #[Route('', name: 'quiz_management_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $result = $this->quizManagementService->createQuiz($data, $user);

        if (!$result['success']) {
            return new JsonResponse(['errors' => $result['errors']], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(
            $this->quizManagementService->serializeQuiz($result['quiz']),
            Response::HTTP_CREATED
        );
    }

    /**
     * Mettre à jour un questionnaire existant
     */
    #[Route('/{id}', name: 'quiz_management_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->quizManagementService->updateQuiz($quiz, $data);

        if (!$result['success']) {
            return new JsonResponse(['errors' => $result['errors']], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->quizManagementService->serializeQuiz($result['quiz']));
    }

    /**
     * Supprimer un questionnaire
     */
    #[Route('/{id}', name: 'quiz_management_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        $this->quizManagementService->deleteQuiz($quiz);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Basculer le statut actif/inactif d'un questionnaire
     */
    #[Route('/{id}/toggle-status', name: 'quiz_management_toggle_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function toggleStatus(int $id): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        $updatedQuiz = $this->quizManagementService->toggleQuizStatus($quiz);

        return new JsonResponse([
            'message' => 'Statut mis à jour avec succès',
            'quiz' => $this->quizManagementService->serializeQuiz($updatedQuiz)
        ]);
    }

    /**
     * Récupérer toutes les tentatives d'un quiz (pour les créateurs)
     */
    #[Route('/{id}/attempts', name: 'quiz_management_attempts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuizAttempts(int $id): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        $attempts = $this->quizManagementService->getQuizAttempts($quiz);

        return new JsonResponse($attempts);
    }

    /**
     * Récupérer les détails d'une tentative spécifique (pour les créateurs)
     */
    #[Route('/{id}/attempts/{attemptId}', name: 'quiz_management_attempt_details', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAttemptDetails(int $id, int $attemptId): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizManagementService->getUserQuiz($id, $user);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        $details = $this->quizManagementService->getAttemptDetails($attemptId, $quiz);

        if (!$details) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($details);
    }
}