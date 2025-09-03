<?php

namespace App\Service;

use App\Entity\TentativeQuestionnaire;
use App\Entity\Utilisateur;
use App\Repository\TentativeQuestionnaireRepository;
use App\Repository\ReponseUtilisateurRepository;

class QuizHistoryService
{
    public function __construct(
        private TentativeQuestionnaireRepository $tentativeRepository,
        private ReponseUtilisateurRepository $reponseUtilisateurRepository
    ) {
    }

    /**
     * Récupère l'historique des tentatives d'un utilisateur
     */
    public function getUserAttempts(Utilisateur $user): array
    {
        $attempts = $this->tentativeRepository->findByUserWithQuestionnaire($user);
        
        return array_map([$this, 'serializeAttemptForHistory'], $attempts);
    }

    /**
     * Récupère les détails d'une tentative spécifique
     */
    public function getAttemptDetails(int $attemptId, Utilisateur $user): ?array
    {
        $attempt = $this->tentativeRepository->findOneByIdAndUser($attemptId, $user);
        
        if (!$attempt) {
            return null;
        }

        $reponsesUtilisateur = $this->reponseUtilisateurRepository
            ->findByTentativeWithDetailsOrderedByQuestionOrder($attempt);

        $details = [];
        foreach ($reponsesUtilisateur as $ru) {
            $question = $ru->getQuestion();
            $selectedAnswer = $ru->getReponse();
            $correctAnswerText = $this->getCorrectAnswerText($question);

            $details[] = [
                'questionId' => (string) $question->getId(),
                'questionTexte' => $question->getTexte(),
                'reponseUtilisateurTexte' => $selectedAnswer?->getTexte() ?? '',
                'reponseCorrecteTexte' => $correctAnswerText ?? '',
                'estCorrecte' => $selectedAnswer?->isCorrect() ?? false,
            ];
        }

        return [
            'reponsesDetails' => $details,
        ];
    }

    /**
     * Sérialise une tentative pour l'historique
     */
    public function serializeAttemptForHistory(TentativeQuestionnaire $tentative): array
    {
        $questionnaire = $tentative->getQuestionnaire();
        $dateDebut = $tentative->getDateDebut();

        // Calcul du pourcentage avec méthode de l'entité si disponible
        $pourcentage = method_exists($tentative, 'getPourcentage')
            ? $tentative->getPourcentage()
            : ($tentative->getNombreTotalQuestions() > 0
                ? round(($tentative->getScore() / $tentative->getNombreTotalQuestions()) * 100, 2)
                : 0);

        return [
            'id' => $tentative->getId(),
            'questionnaireTitre' => $questionnaire?->getTitre() ?? '',
            'questionnaireCode' => $questionnaire?->getCodeAcces() ?? '',
            'date' => $dateDebut?->format('Y-m-d') ?? '',
            'heure' => $dateDebut?->format('H:i') ?? '',
            'score' => $tentative->getScore() ?? 0,
            'nombreTotalQuestions' => $tentative->getNombreTotalQuestions() ?? 0,
            'pourcentage' => $pourcentage,
            'estReussi' => $questionnaire ? ($pourcentage >= $questionnaire->getScorePassage()) : false,
        ];
    }

    /**
     * Récupère le texte de la bonne réponse pour une question
     */
    private function getCorrectAnswerText(\App\Entity\Question $question): ?string
    {
        foreach ($question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                return $reponse->getTexte();
            }
        }
        return null;
    }

    /**
     * Calcule les statistiques d'un utilisateur
     */
    public function getUserStats(Utilisateur $user): array
    {
        $attempts = $this->tentativeRepository->findByUserWithQuestionnaire($user);
        
        $totalAttempts = count($attempts);
        $totalScore = 0;
        $passedAttempts = 0;
        
        foreach ($attempts as $attempt) {
            $pourcentage = method_exists($attempt, 'getPourcentage')
                ? $attempt->getPourcentage()
                : ($attempt->getNombreTotalQuestions() > 0
                    ? round(($attempt->getScore() / $attempt->getNombreTotalQuestions()) * 100, 2)
                    : 0);
            
            $totalScore += $pourcentage;
            
            if ($attempt->getQuestionnaire() && $pourcentage >= $attempt->getQuestionnaire()->getScorePassage()) {
                $passedAttempts++;
            }
        }
        
        return [
            'totalAttempts' => $totalAttempts,
            'averageScore' => $totalAttempts > 0 ? round($totalScore / $totalAttempts, 2) : 0,
            'passedAttempts' => $passedAttempts,
            'passRate' => $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 2) : 0,
        ];
    }
}