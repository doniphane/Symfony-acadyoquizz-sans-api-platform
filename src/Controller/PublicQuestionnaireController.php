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

#[Route('/api/public/questionnaires')]
class PublicQuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Récupérer tous les questionnaires publics actifs
     */
    #[Route('', name: 'public_questionnaire_collection', methods: ['GET'])]
    public function getCollection(): JsonResponse
    {
        $questionnaires = $this->entityManager->getRepository(Questionnaire::class)
            ->findBy(['estActif' => true], ['dateCreation' => 'DESC']);

        $data = [];
        foreach ($questionnaires as $questionnaire) {
            $data[] = $this->serializePublicQuestionnaire($questionnaire);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer un questionnaire public par son ID
     */
    #[Route('/{id}', name: 'public_questionnaire_item', methods: ['GET'])]
    public function getItem(int $id): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if (!$questionnaire->isActive()) {
            return new JsonResponse(['error' => 'Ce questionnaire n\'est pas actif'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializePublicQuestionnaire($questionnaire, true));
    }

    /**
     * Récupérer un questionnaire par son code d'accès
     */
    #[Route('/code/{code}', name: 'public_questionnaire_by_code', methods: ['GET'])]
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

        return new JsonResponse($this->serializePublicQuestionnaire($questionnaire, true));
    }


    #[Route('/{id}/submit', name: 'public_questionnaire_submit', methods: ['POST'])]
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

        // Vérifier que les informations du participant sont présentes
        if (!isset($data['prenomParticipant']) || !isset($data['nomParticipant'])) {
            return new JsonResponse(['error' => 'Les informations du participant (prénom et nom) sont requises'], Response::HTTP_BAD_REQUEST);
        }

        if (empty(trim($data['prenomParticipant'])) || empty(trim($data['nomParticipant']))) {
            return new JsonResponse(['error' => 'Le prénom et le nom ne peuvent pas être vides'], Response::HTTP_BAD_REQUEST);
        }

        // Créer une tentative de questionnaire
        $tentative = new TentativeQuestionnaire();
        $tentative->setQuestionnaire($questionnaire);
        $tentative->setDateDebut(new \DateTimeImmutable());
        $tentative->setDateFin(new \DateTimeImmutable());

        // Récupérer les informations du participant
        if (isset($data['prenomParticipant'])) {
            $tentative->setPrenomParticipant($data['prenomParticipant']);
        }
        if (isset($data['nomParticipant'])) {
            $tentative->setNomParticipant($data['nomParticipant']);
        }


        if ($this->getUser()) {
            $tentative->setUtilisateur($this->getUser());
        }

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
     * Récupérer les résultats d'une tentative
     */
    #[Route('/tentative/{id}', name: 'public_tentative_result', methods: ['GET'])]
    public function getTentativeResult(int $id): JsonResponse
    {
        $tentative = $this->entityManager->getRepository(TentativeQuestionnaire::class)->find($id);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
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
            'questionnaire' => $this->serializePublicQuestionnaire($tentative->getQuestionnaire()),
            'resultats' => $resultats
        ]);
    }

    /**
     * Sérialise un questionnaire public
     */
    private function serializePublicQuestionnaire(Questionnaire $questionnaire, bool $includeQuestions = false): array
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


    private function getCorrectAnswerForQuestion(Question $question): ?array
    {
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                return [
                    'id' => $reponse->getId(),
                    'texte' => $reponse->getTexte(),
                    'estCorrecte' => true
                ];
            }
        }

        return null;
    }

    /**
     * Vérifie si une question est répondue correctement
     * Pour les questions à choix multiple, toutes les bonnes réponses doivent être sélectionnées
     * et aucune mauvaise réponse ne doit être sélectionnée
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