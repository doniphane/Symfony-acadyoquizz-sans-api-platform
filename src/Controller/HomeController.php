<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'api_home', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Acadyoquizz API',
            'version' => '3.0',
            'status' => 'active',
            'endpoints' => [
                'auth' => '/api/auth',
                'quizzes' => '/api/quizzes',
                'questions' => '/api/questions',
                'users' => '/api/users'
            ]
        ]);
    }
}