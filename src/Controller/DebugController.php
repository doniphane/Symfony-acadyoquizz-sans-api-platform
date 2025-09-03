<?php

namespace App\Controller;

use App\Repository\TentativeQuestionnaireRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    public function __construct(
        private TentativeQuestionnaireRepository $tentativeRepository,
        private UtilisateurRepository $userRepository
    ) {
    }

    #[Route('/my-info', name: 'debug_my_info', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyInfo(): JsonResponse
    {
        $user = $this->getUser();
        
        return new JsonResponse([
            'user_id' => $user?->getId(),
            'user_email' => $user?->getEmail(),
            'user_roles' => $user?->getRoles(),
            'user_class' => get_class($user),
        ]);
    }

    #[Route('/all-attempts', name: 'debug_all_attempts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAllAttempts(): JsonResponse
    {
        $allAttempts = $this->tentativeRepository->findAll();
        
        $data = [];
        foreach ($allAttempts as $attempt) {
            $data[] = [
                'id' => $attempt->getId(),
                'participant' => $attempt->getPrenomParticipant() . ' ' . $attempt->getNomParticipant(),
                'date' => $attempt->getDateDebut()?->format('Y-m-d H:i:s'),
                'user_id' => $attempt->getUtilisateur()?->getId(),
                'user_email' => $attempt->getUtilisateur()?->getEmail(),
                'questionnaire_id' => $attempt->getQuestionnaire()?->getId(),
                'questionnaire_title' => $attempt->getQuestionnaire()?->getTitre(),
            ];
        }
        
        return new JsonResponse([
            'total_attempts' => count($data),
            'attempts' => $data
        ]);
    }

    #[Route('/my-attempts-raw', name: 'debug_my_attempts_raw', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyAttemptsRaw(): JsonResponse
    {
        $user = $this->getUser();
        
        $attempts = $this->tentativeRepository->findByUserWithQuestionnaire($user);
        
        $data = [];
        foreach ($attempts as $attempt) {
            $data[] = [
                'attempt_id' => $attempt->getId(),
                'participant' => $attempt->getPrenomParticipant() . ' ' . $attempt->getNomParticipant(),
                'date_debut' => $attempt->getDateDebut()?->format('Y-m-d H:i:s'),
                'score' => $attempt->getScore(),
                'total_questions' => $attempt->getNombreTotalQuestions(),
                'questionnaire_id' => $attempt->getQuestionnaire()?->getId(),
                'questionnaire_title' => $attempt->getQuestionnaire()?->getTitre(),
                'questionnaire_code' => $attempt->getQuestionnaire()?->getCodeAcces(),
                'user_association' => $attempt->getUtilisateur()?->getId(),
                'current_user_id' => $user?->getId(),
                'match' => $attempt->getUtilisateur()?->getId() === $user?->getId(),
            ];
        }
        
        return new JsonResponse([
            'user_info' => [
                'id' => $user?->getId(),
                'email' => $user?->getEmail(),
            ],
            'found_attempts' => count($data),
            'attempts_details' => $data
        ]);
    }
}