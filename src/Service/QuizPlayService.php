<?php

namespace App\Service;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\TentativeQuestionnaire;
use App\Entity\ReponseUtilisateur;
use App\Entity\Utilisateur;
use App\Repository\QuestionnaireRepository;
use App\Repository\QuestionRepository;
use App\Repository\TentativeQuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuizPlayService
{
    public function __construct(
        private QuestionnaireRepository $questionnaireRepository,
        private QuestionRepository $questionRepository,
        private TentativeQuestionnaireRepository $tentativeRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Récupère tous les questionnaires actifs disponibles pour jouer
     */
    public function getAvailableQuizzes(): array
    {
        return $this->questionnaireRepository->findActiveQuizzes();
    }

    /**
     * Récupère un questionnaire par ID (avec vérification qu'il est actif)
     */
    public function getPlayableQuiz(int $id): ?Questionnaire
    {
        return $this->questionnaireRepository->findActiveQuizById($id);
    }

    /**
     * Récupère un questionnaire par code d'accès (avec vérification qu'il est actif)
     */
    public function getQuizByCode(string $code): ?Questionnaire
    {
        return $this->questionnaireRepository->findActiveQuizByCode($code);
    }

    /**
     * Soumet un quiz et calcule les résultats
     */
    public function submitQuiz(
        Questionnaire $questionnaire, 
        array $answers, 
        Utilisateur $user,
        ?string $firstName = null,
        ?string $lastName = null
    ): array {
        // Créer la tentative
        $tentative = new TentativeQuestionnaire();
        $tentative->setQuestionnaire($questionnaire);
        $tentative->setUtilisateur($user);
        $tentative->setDateDebut(new \DateTimeImmutable());
        $tentative->setDateFin(new \DateTimeImmutable());
        
        // Utiliser les informations utilisateur ou les noms fournis
        $tentative->setPrenomParticipant($firstName ?? $user->getFirstName() ?? 'Utilisateur');
        $tentative->setNomParticipant($lastName ?? $user->getLastName() ?? 'Connecté');

        $this->entityManager->persist($tentative);

        $score = 0;
        $totalQuestions = 0;

        // Grouper les réponses par question
        $answersByQuestion = $this->groupAnswersByQuestion($answers);

        // Traiter chaque question
        foreach ($answersByQuestion as $questionId => $selectedAnswerIds) {
            $question = $this->questionRepository->findQuestionInQuestionnaire($questionId, $questionnaire->getId());
            
            if (!$question) {
                continue;
            }

            $totalQuestions++;

            // Créer les réponses utilisateur
            foreach ($selectedAnswerIds as $answerId) {
                $reponse = $this->findAnswerInQuestion($question, $answerId);
                
                if ($reponse) {
                    $reponseUtilisateur = new ReponseUtilisateur();
                    $reponseUtilisateur->setTentativeQuestionnaire($tentative);
                    $reponseUtilisateur->setQuestion($question);
                    $reponseUtilisateur->setReponse($reponse);
                    $this->entityManager->persist($reponseUtilisateur);
                }
            }

            // Calculer le score pour cette question
            if ($this->isQuestionAnsweredCorrectly($question, $selectedAnswerIds)) {
                $score++;
            }
        }

        // Finaliser la tentative
        $tentative->setScore($score);
        $tentative->setNombreTotalQuestions($totalQuestions);
        
        $this->entityManager->flush();

        // Calculer les résultats
        $scorePercentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;
        $passed = $scorePercentage >= $questionnaire->getScorePassage();

        return [
            'tentativeId' => $tentative->getId(),
            'score' => $scorePercentage,
            'scorePassage' => $questionnaire->getScorePassage(),
            'reussie' => $passed,
            'bonnesReponses' => $score,
            'totalQuestions' => $totalQuestions
        ];
    }

    /**
     * Récupère les résultats détaillés d'une tentative
     */
    public function getTentativeResults(int $tentativeId, Utilisateur $user): ?array
    {
        $tentative = $this->tentativeRepository->findOneByIdAndUser($tentativeId, $user);
        
        if (!$tentative) {
            return null;
        }

        return $this->formatTentativeResults($tentative);
    }

    /**
     * Sérialise un questionnaire pour le jeu (avec questions)
     */
    public function serializePlayableQuiz(Questionnaire $questionnaire): array
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

        // Ajouter les questions sans révéler les bonnes réponses
        $questions = [];
        foreach ($questionnaire->getQuestions() as $question) {
            $reponses = [];
            foreach ($question->getReponses() as $reponse) {
                $reponses[] = [
                    'id' => $reponse->getId(),
                    'texte' => $reponse->getTexte(),
                    'numeroOrdre' => $reponse->getNumeroOrdre()
                    // Pas de 'estCorrecte' pour ne pas révéler les réponses !
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

        usort($questions, fn($a, $b) => $a['numeroOrdre'] <=> $b['numeroOrdre']);
        $data['questions'] = $questions;

        return $data;
    }

    /**
     * Groupe les réponses par question
     */
    private function groupAnswersByQuestion(array $answers): array
    {
        $grouped = [];
        foreach ($answers as $answer) {
            if (!isset($answer['questionId']) || !isset($answer['reponseId'])) {
                continue;
            }
            $questionId = (int) $answer['questionId'];
            if (!isset($grouped[$questionId])) {
                $grouped[$questionId] = [];
            }
            $grouped[$questionId][] = (int) $answer['reponseId'];
        }
        return $grouped;
    }

    /**
     * Trouve une réponse dans une question
     */
    private function findAnswerInQuestion(Question $question, int $answerId): ?\App\Entity\Reponse
    {
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->getId() === $answerId) {
                return $reponse;
            }
        }
        return null;
    }

    /**
     * Vérifie si une question est correctement répondue
     */
    private function isQuestionAnsweredCorrectly(Question $question, array $selectedAnswerIds): bool
    {
        // Récupérer les IDs des bonnes réponses
        $correctAnswerIds = [];
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                $correctAnswerIds[] = $reponse->getId();
            }
        }

        // Vérifier que toutes les bonnes réponses sont sélectionnées
        foreach ($correctAnswerIds as $correctId) {
            if (!in_array($correctId, $selectedAnswerIds)) {
                return false;
            }
        }

        // Vérifier qu'aucune mauvaise réponse n'est sélectionnée
        foreach ($selectedAnswerIds as $selectedId) {
            if (!in_array($selectedId, $correctAnswerIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Formate les résultats d'une tentative
     */
    private function formatTentativeResults(TentativeQuestionnaire $tentative): array
    {
        $details = [];
        foreach ($tentative->getReponsesUtilisateur() as $reponseUtilisateur) {
            $question = $reponseUtilisateur->getQuestion();
            $questionId = $question->getId();
            
            if (!isset($details[$questionId])) {
                $details[$questionId] = [
                    'question' => $question,
                    'reponses' => []
                ];
            }
            $details[$questionId]['reponses'][] = $reponseUtilisateur->getReponse();
        }

        $resultats = [];
        foreach ($details as $data) {
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
            foreach ($question->getReponses() as $reponse) {
                if ($reponse->isCorrect()) {
                    $bonnesReponses[] = [
                        'id' => $reponse->getId(),
                        'texte' => $reponse->getTexte(),
                        'estCorrecte' => true
                    ];
                }
            }

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

        return [
            'tentativeId' => $tentative->getId(),
            'score' => $scorePercentage,
            'reussie' => $reussie,
            'dateDebut' => $tentative->getDateDebut()?->format('c'),
            'dateFin' => $tentative->getDateFin()?->format('c'),
            'questionnaire' => [
                'id' => $tentative->getQuestionnaire()->getId(),
                'title' => $tentative->getQuestionnaire()->getTitre(),
                'accessCode' => $tentative->getQuestionnaire()->getCodeAcces()
            ],
            'resultats' => $resultats
        ];
    }
}