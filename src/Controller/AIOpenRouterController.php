<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/ai')]
#[IsGranted('ROLE_USER')]
class AIOpenRouterController extends AbstractController
{
    private const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {
        if (!$this->httpClient) {
            throw new \RuntimeException('HttpClient not available');
        }
    }

    #[Route('/generate-questions', name: 'ai_generate_questions', methods: ['POST'])]
    public function generateQuestions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['text']) || !isset($data['numberOfQuestions'])) {
                return $this->json(['error' => 'Texte et nombre de questions requis'], 400);
            }

            $text = $data['text'];
            $numberOfQuestions = (int) $data['numberOfQuestions'];
            $prompt = $this->buildPrompt($text, $numberOfQuestions);

            $openRouterApiKey = $_ENV['VITE_OPENROUTER_API_KEY'] ?? null;
            if (!$openRouterApiKey) {
                return $this->json(['error' => 'Clé API OpenRouter non configurée'], 500);
            }

            // Appel à l'API avec timeout
            $response = $this->httpClient->request('POST', self::OPENROUTER_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $openRouterApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'deepseek/deepseek-chat-v3-0324:free',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                    'stream' => false
                ],
                'timeout' => 30 // Timeout de 30 secondes pour arreter la requette pour que php plante 
            ]);

            $responseData = $response->toArray();

            if ($response->getStatusCode() !== 200) {
                $errorMessage = $this->getErrorMessage($response->getStatusCode(), $responseData);
                return $this->json(['error' => $errorMessage], $response->getStatusCode());
            }

            $aiResponse = $responseData['choices'][0]['message']['content'] ?? null;
            if (!$aiResponse) {
                return $this->json(['error' => 'Réponse invalide de l\'IA'], 500);
            }

            $parsedQuestions = $this->parseAIResponse($aiResponse);

            return $this->json([
                'questions' => $parsedQuestions,
                'message' => 'Génération réussie de ' . count($parsedQuestions) . ' questions'
            ]);

        } catch (\Exception $e) {

            $errorMessage = $this->handleException($e);
            return $this->json(['error' => $errorMessage], 500);
        }
    }

    private function getErrorMessage(int $statusCode, array $responseData): string
    {
        return match ($statusCode) {
            401 => 'Clé API OpenRouter invalide',
            403 => 'Accès refusé - Vérifiez votre quota',
            429 => 'Trop de requêtes. Attendez quelques minutes',
            500, 502, 503, 504 => 'Service IA temporairement indisponible',
            default => 'Erreur OpenRouter: ' . ($responseData['error']['message'] ?? 'Erreur inconnue')
        };
    }

    private function handleException(\Exception $e): string
    {
        $message = $e->getMessage();


        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'L\'IA met trop de temps à répondre. Réessayez avec un texte plus court';
        }


        if (str_contains($message, 'connect') || str_contains($message, 'network') || str_contains($message, 'curl')) {
            return 'Service IA temporairement indisponible. Réessayez dans quelques minutes';
        }

        return 'Erreur lors de la génération: ' . $message;
    }

    private function buildPrompt(string $text, int $numberOfQuestions): string
    {
        return "Tu es un expert en création de questions de quiz. Analyse le texte suivant et génère {$numberOfQuestions} questions de quiz avec leurs réponses.

TEXTE À ANALYSER:
{$text}

INSTRUCTIONS:
- Crée {$numberOfQuestions} questions pertinentes basées sur le contenu du texte
- Chaque question doit avoir 4 réponses possibles
- Une seule réponse doit être correcte par question
- Les questions doivent être variées (vrai/faux, choix multiples, etc.)
- Les réponses doivent être claires et précises

FORMAT DE RÉPONSE ATTENDU (JSON):
{
  \"questions\": [
    {
      \"question\": \"Question 1?\",
      \"answers\": [
        {\"text\": \"Réponse 1\", \"correct\": false},
        {\"text\": \"Réponse 2\", \"correct\": true},
        {\"text\": \"Réponse 3\", \"correct\": false},
        {\"text\": \"Réponse 4\", \"correct\": false}
      ]
    }
  ]
}

Réponds uniquement avec le JSON, sans texte supplémentaire.";
    }

    private function parseAIResponse(string $aiResponse): array
    {
        try {
            $cleanedResponse = trim($aiResponse);
            $cleanedResponse = preg_replace('/```json\s*/', '', $cleanedResponse);
            $cleanedResponse = preg_replace('/```\s*$/', '', $cleanedResponse);

            $parsed = json_decode($cleanedResponse, true);

            if (isset($parsed['questions']) && is_array($parsed['questions'])) {
                return $parsed['questions'];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }
}