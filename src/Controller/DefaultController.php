<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'AcadyoQuizz API v3',
            'status' => 'running',
            'version' => '3.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'unknown'
        ]);
    }

    #[Route('/api', name: 'api_info', methods: ['GET'])]
    public function apiInfo(): JsonResponse
    {
        return $this->json([
            'message' => 'AcadyoQuizz API v3 - Endpoints disponibles',
            'endpoints' => [
                'POST /api/login_check' => 'Authentification',
                'GET /api/users/me' => 'Profil utilisateur',
                'POST /api/users/register' => 'Inscription',
                'GET /api/questionnaires' => 'Liste des questionnaires',
                'GET /api/public/questionnaires/code/{code}' => 'Accès questionnaire par code'
            ],
            'version' => '3.0.0'
        ]);
    }
}