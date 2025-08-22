<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Questionnaire;
use App\Entity\Reponse;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/questions')]
class QuestionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private QuestionRepository $questionRepository
    ) {
    }

    /**
     * Récupérer toutes les questions d'un questionnaire
     */
    #[Route('', name: 'question_get_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(Request $request): JsonResponse
    {
        $questionnaireId = $request->query->get('questionnaire');

        if (!$questionnaireId) {
            return new JsonResponse(['error' => 'ID du questionnaire requis'], Response::HTTP_BAD_REQUEST);
        }

        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $questions = $this->entityManager->getRepository(Question::class)
            ->findBy(['questionnaire' => $questionnaire], ['numeroOrdre' => 'ASC']);

        $data = [];
        foreach ($questions as $question) {
            $data[] = $this->serializeQuestion($question, true);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer une question spécifique
     */
    #[Route('/{id}', name: 'question_get_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $question = $this->entityManager->getRepository(Question::class)->find($id);

        if (!$question) {
            return new JsonResponse(['error' => 'Question non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($question->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeQuestion($question, true));
    }

    /**
     * Créer une nouvelle question
     */
    #[Route('', name: 'question_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function post(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['questionnaire'])) {
            return new JsonResponse(['error' => 'ID du questionnaire requis'], Response::HTTP_BAD_REQUEST);
        }

        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($data['questionnaire']);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $question = new Question();
        $question->setQuestionnaire($questionnaire);


        if (isset($data['texte'])) {
            $question->setTexte($data['texte']);
        }
        if (isset($data['numeroOrdre'])) {
            $question->setNumeroOrdre($data['numeroOrdre']);
        } else {
            $maxOrder = $this->questionRepository->findMaxOrderByQuestionnaire($questionnaire);
            $question->setNumeroOrdre(($maxOrder ?? 0) + 1);
        }

        $this->entityManager->persist($question);

        // Ajouter les réponses AVANT la validation
        if (isset($data['reponses']) && is_array($data['reponses'])) {
            foreach ($data['reponses'] as $reponseData) {
                $reponse = new Reponse();
                $reponse->setQuestion($question);

                if (isset($reponseData['texte'])) {
                    $reponse->setTexte($reponseData['texte']);
                }
                if (isset($reponseData['estCorrecte'])) {
                    $reponse->setEstCorrecte($reponseData['estCorrecte']);
                }
                if (isset($reponseData['numeroOrdre'])) {
                    $reponse->setNumeroOrdre($reponseData['numeroOrdre']);
                }

                $this->entityManager->persist($reponse);

                $question->addReponse($reponse);
            }
        }

        // Validation APRÈS avoir ajouté les réponses
        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeQuestion($question, true), Response::HTTP_CREATED);
    }

    /**
     * Mettre à jour une question
     */
    #[Route('/{id}', name: 'question_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(int $id, Request $request): JsonResponse
    {
        $question = $this->entityManager->getRepository(Question::class)->find($id);

        if (!$question) {
            return new JsonResponse(['error' => 'Question non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($question->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour les champs de la question
        if (isset($data['texte'])) {
            $question->setTexte($data['texte']);
        }
        if (isset($data['numeroOrdre'])) {
            $question->setNumeroOrdre($data['numeroOrdre']);
        }


        if (isset($data['reponses']) && is_array($data['reponses'])) {
            // Supprimer les anciennes réponses
            foreach ($question->getReponses() as $oldReponse) {
                $this->entityManager->remove($oldReponse);
            }
            $question->getReponses()->clear();

            // Ajouter les nouvelles réponses
            foreach ($data['reponses'] as $reponseData) {
                $reponse = new Reponse();
                $reponse->setQuestion($question);

                if (isset($reponseData['texte'])) {
                    $reponse->setTexte($reponseData['texte']);
                }
                if (isset($reponseData['estCorrecte'])) {
                    $reponse->setEstCorrecte($reponseData['estCorrecte']);
                }
                if (isset($reponseData['numeroOrdre'])) {
                    $reponse->setNumeroOrdre($reponseData['numeroOrdre']);
                }

                $this->entityManager->persist($reponse);
                $question->addReponse($reponse);
            }
        }


        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeQuestion($question, true));
    }

    /**
     * Supprimer une question
     */
    #[Route('/{id}', name: 'question_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $question = $this->entityManager->getRepository(Question::class)->find($id);

        if (!$question) {
            return new JsonResponse(['error' => 'Question non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($question->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sérialise une question en tableau
     */
    private function serializeQuestion(Question $question, bool $includeReponses = false): array
    {
        $data = [
            'id' => $question->getId(),
            'texte' => $question->getTexte(),
            'numeroOrdre' => $question->getNumeroOrdre(),
            'type' => $question->getQuestionType(),
            'isMultipleChoice' => $question->isMultipleChoice(),
            'questionnaire' => [
                'id' => $question->getQuestionnaire()->getId(),
                'titre' => $question->getQuestionnaire()->getTitre()
            ]
        ];

        if ($includeReponses) {
            $reponses = [];
            foreach ($question->getReponses() as $reponse) {
                $reponses[] = [
                    'id' => $reponse->getId(),
                    'texte' => $reponse->getTexte(),
                    'estCorrecte' => $reponse->isCorrect(),
                    'numeroOrdre' => $reponse->getNumeroOrdre()
                ];
            }
            $data['reponses'] = $reponses;
        }

        return $data;
    }
}