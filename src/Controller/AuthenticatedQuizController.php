<?php

namespace App\Controller;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\TentativeQuestionnaire;
use App\Entity\ReponseUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/authenticated/questionnaires')]
class AuthenticatedQuizController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Récupérer tous les questionnaires actifs (pour utilisateurs authentifiés)
     */
    #[Route('', name: 'authenticated_questionnaire_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(): JsonResponse
    {
        $questionnaires = $this->entityManager->getRepository(Questionnaire::class)
            ->findBy(['estActif' => true], ['dateCreation' => 'DESC']);

        $data = [];
        foreach ($questionnaires as $questionnaire) {
            $data[] = $this->serializeQuestionnaire($questionnaire);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer un questionnaire par son ID (pour utilisateurs authentifiés)
     */
    #[Route('/{id}', name: 'authenticated_questionnaire_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if (!$questionnaire->isActive()) {
            return new JsonResponse(['error' => 'Ce questionnaire n\'est pas actif'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeQuestionnaire($questionnaire, true));
    }

    /**
     * Récupérer un questionnaire par son code d'accès (pour utilisateurs authentifiés)
     */
    #[Route('/code/{code}', name: 'authenticated_questionnaire_by_code', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getByCode(string $code): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)
            ->findOneBy(['codeAcces' => strtoupper($code)]);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé avec ce code d\'accès'], Response::HTTP_NOT_FOUND);
        }

        if (!$questionnaire->isActive()) {
            return new JsonResponse(['error' => 'Ce questionnaire n\'est pas actif'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeQuestionnaire($questionnaire, true));
    }

    /**
     * Soumettre les réponses d'un quiz (OBLIGATOIREMENT authentifié)
     */
    #[Route('/{id}/submit', name: 'authenticated_questionnaire_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(int $id, Request $request): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if (!$questionnaire->isActive()) {
            return new JsonResponse(['error' => 'Ce questionnaire n\'est pas actif'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['reponses'])) {
            return new JsonResponse(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        // L'utilisateur est OBLIGATOIREMENT authentifié ici
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier que les informations du participant sont présentes (optionnel maintenant)
        $prenomParticipant = $data['prenomParticipant'] ?? $currentUser->getPrenom() ?? 'Utilisateur';
        $nomParticipant = $data['nomParticipant'] ?? $currentUser->getNom() ?? 'Connecté';

        // Créer une tentative de questionnaire
        $tentative = new TentativeQuestionnaire();
        $tentative->setQuestionnaire($questionnaire);
        $tentative->setDateDebut(new \DateTimeImmutable());
        $tentative->setDateFin(new \DateTimeImmutable());
        $tentative->setPrenomParticipant($prenomParticipant);
        $tentative->setNomParticipant($nomParticipant);
        
        // ASSOCIATION OBLIGATOIRE avec l'utilisateur authentifié
        $tentative->setUtilisateur($currentUser);
        
        error_log("✅ Tentative créée pour utilisateur authentifié - ID: " . $currentUser->getId() . ", Email: " . $currentUser->getEmail());

        $this->entityManager->persist($tentative);

        $score = 0;
        $totalQuestions = 0;

        // Grouper les réponses par question pour gérer les choix multiples
        $reponsesByQuestion = [];
        foreach ($data['reponses'] as $reponseData) {
            if (!isset($reponseData['questionId']) || !isset($reponseData['reponseId'])) {
                continue;
            }
            $questionId = (int) $reponseData['questionId'];
            if (!isset($reponsesByQuestion[$questionId])) {
                $reponsesByQuestion[$questionId] = [];
            }
            $reponsesByQuestion[$questionId][] = (int) $reponseData['reponseId'];
        }

        // Traiter chaque question
        foreach ($reponsesByQuestion as $questionId => $selectedAnswerIds) {
            $question = $this->entityManager->getRepository(Question::class)->find($questionId);
            if (!$question || $question->getQuestionnaire() !== $questionnaire) {
                continue;
            }

            $totalQuestions++;

            // Créer les réponses utilisateur
            foreach ($selectedAnswerIds as $answerId) {
                $reponseSelectionnee = null;
                foreach ($question->getReponses() as $reponse) {
                    if ($reponse->getId() === $answerId) {
                        $reponseSelectionnee = $reponse;
                        break;
                    }
                }

                if ($reponseSelectionnee) {
                    $reponseUtilisateur = new ReponseUtilisateur();
                    $reponseUtilisateur->setTentativeQuestionnaire($tentative);
                    $reponseUtilisateur->setQuestion($question);
                    $reponseUtilisateur->setReponse($reponseSelectionnee);
                    $this->entityManager->persist($reponseUtilisateur);
                }
            }

            // Calculer le score pour cette question
            if ($this->isQuestionAnsweredCorrectly($question, $selectedAnswerIds)) {
                $score++;
            }
        }

        // Calculer le score final
        $scorePercentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;
        $tentative->setScore($score);
        $tentative->setNombreTotalQuestions($totalQuestions);

        $this->entityManager->flush();

        $reussie = $scorePercentage >= $questionnaire->getScorePassage();

        error_log("✅ Quiz soumis avec succès - Score: {$scorePercentage}% - Tentative ID: " . $tentative->getId());

        return new JsonResponse([
            'tentativeId' => $tentative->getId(),
            'score' => $scorePercentage,
            'scorePassage' => $questionnaire->getScorePassage(),
            'reussie' => $reussie,
            'bonnesReponses' => $score,
            'totalQuestions' => $totalQuestions
        ]);
    }

    /**
     * Récupérer les résultats d'une tentative (pour l'utilisateur authentifié)
     */
    #[Route('/tentative/{id}', name: 'authenticated_tentative_result', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTentativeResult(int $id): JsonResponse
    {
        $tentative = $this->entityManager->getRepository(TentativeQuestionnaire::class)->find($id);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette tentative
        if ($tentative->getUtilisateur() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé à cette tentative'], Response::HTTP_FORBIDDEN);
        }

        $reponsesUtilisateur = $this->entityManager->getRepository(ReponseUtilisateur::class)
            ->findBy(['tentativeQuestionnaire' => $tentative]);

        // Grouper les réponses par question pour gérer les choix multiples
        $reponsesByQuestion = [];
        foreach ($reponsesUtilisateur as $reponseUtilisateur) {
            $questionId = $reponseUtilisateur->getQuestion()->getId();
            if (!isset($reponsesByQuestion[$questionId])) {
                $reponsesByQuestion[$questionId] = [
                    'question' => $reponseUtilisateur->getQuestion(),
                    'reponses' => []
                ];
            }
            $reponsesByQuestion[$questionId]['reponses'][] = $reponseUtilisateur->getReponse();
        }

        $resultats = [];
        foreach ($reponsesByQuestion as $data) {
            $question = $data['question'];
            $reponsesSelectionnees = $data['reponses'];

            // Sérialiser les réponses sélectionnées
            $reponsesSelectionneesSerialized = [];
            foreach ($reponsesSelectionnees as $reponse) {
                $reponsesSelectionneesSerialized[] = [
                    'id' => $reponse->getId(),
                    'texte' => $reponse->getTexte(),
                    'estCorrecte' => $reponse->isCorrect()
                ];
            }

            // Récupérer toutes les bonnes réponses
            $bonnesReponses = [];
            foreach ($question->getCorrectAnswers() as $reponse) {
                $bonnesReponses[] = [
                    'id' => $reponse->getId(),
                    'texte' => $reponse->getTexte(),
                    'estCorrecte' => true
                ];
            }

            // Calculer si l'utilisateur a bien répondu à cette question
            $selectedIds = array_map(fn($r) => $r->getId(), $reponsesSelectionnees);
            $isCorrect = $this->isQuestionAnsweredCorrectly($question, $selectedIds);

            $resultats[] = [
                'questionId' => $question->getId(),
                'questionTexte' => $question->getTexte(),
                'isMultipleChoice' => $question->isMultipleChoice(),
                'reponsesSelectionnees' => $reponsesSelectionneesSerialized,
                'bonnesReponses' => $bonnesReponses,
                'estCorrecte' => $isCorrect
            ];
        }

        $scorePercentage = $tentative->getPourcentage();
        $reussie = $scorePercentage >= $tentative->getQuestionnaire()->getScorePassage();

        return new JsonResponse([
            'tentativeId' => $tentative->getId(),
            'score' => $scorePercentage,
            'reussie' => $reussie,
            'dateDebut' => $tentative->getDateDebut()?->format('c'),
            'dateFin' => $tentative->getDateFin()?->format('c'),
            'questionnaire' => $this->serializeQuestionnaire($tentative->getQuestionnaire()),
            'resultats' => $resultats
        ]);
    }

    /**
     * Sérialise un questionnaire
     */
    private function serializeQuestionnaire(Questionnaire $questionnaire, bool $includeQuestions = false): array
    {
        $data = [
            'id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitre(),
            'description' => $questionnaire->getDescription(),
            'accessCode' => $questionnaire->getCodeAcces(),
            'scorePassage' => $questionnaire->getScorePassage(),
            'isActive' => $questionnaire->isActive(),
            'isStarted' => $questionnaire->isStarted(),
            'createdAt' => $questionnaire->getDateCreation()?->format('c')
        ];

        if ($includeQuestions) {
            $questions = [];
            foreach ($questionnaire->getQuestions() as $question) {
                $reponses = [];
                foreach ($question->getReponses() as $reponse) {
                    $reponses[] = [
                        'id' => $reponse->getId(),
                        'texte' => $reponse->getTexte(),
                        'numeroOrdre' => $reponse->getNumeroOrdre()
                    ];
                }

                $questions[] = [
                    'id' => $question->getId(),
                    'texte' => $question->getTexte(),
                    'numeroOrdre' => $question->getNumeroOrdre(),
                    'isMultipleChoice' => $question->isMultipleChoice(),
                    'reponses' => $reponses
                ];
            }

            // Trier les questions par numéro d'ordre
            usort($questions, fn($a, $b) => $a['numeroOrdre'] <=> $b['numeroOrdre']);

            $data['questions'] = $questions;
        }

        return $data;
    }

    /**
     * Vérifie si une question est répondue correctement
     */
    private function isQuestionAnsweredCorrectly(Question $question, array $selectedAnswerIds): bool
    {
        // Récupérer tous les IDs des bonnes réponses
        $correctAnswerIds = [];
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                $correctAnswerIds[] = $reponse->getId();
            }
        }

        // Vérifier que toutes les bonnes réponses sont sélectionnées
        foreach ($correctAnswerIds as $correctId) {
            if (!in_array($correctId, $selectedAnswerIds)) {
                return false; // Une bonne réponse manquante
            }
        }

        // Vérifier qu'aucune mauvaise réponse n'est sélectionnée
        foreach ($selectedAnswerIds as $selectedId) {
            if (!in_array($selectedId, $correctAnswerIds)) {
                return false; // Une mauvaise réponse sélectionnée
            }
        }

        return true; // Toutes les bonnes réponses sélectionnées, aucune mauvaise
    }
}