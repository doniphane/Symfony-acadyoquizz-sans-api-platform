<?php

namespace App\Controller;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\Utilisateur;
use App\Repository\QuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/questionnaires')]
class QuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Récupérer tous les questionnaires de l'utilisateur connecté
     */
    #[Route('', name: 'questionnaire_get_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(): JsonResponse
    {
        $user = $this->getUser();
        $questionnaires = $this->entityManager->getRepository(Questionnaire::class)
            ->findBy(['creePar' => $user], ['dateCreation' => 'DESC']);

        $data = [];
        foreach ($questionnaires as $questionnaire) {
            $data[] = $this->serializeQuestionnaire($questionnaire);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer un questionnaire spécifique
     */
    #[Route('/{id}', name: 'questionnaire_get_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeQuestionnaire($questionnaire, true));
    }

    /**
     * Créer un nouveau questionnaire
     */
    #[Route('', name: 'questionnaire_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function post(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        $questionnaire = new Questionnaire();
        $questionnaire->setCreePar($this->getUser());

        // Remplir les champs
        if (isset($data['title'])) {
            $questionnaire->setTitre($data['title']);
        }
        if (isset($data['description'])) {
            $questionnaire->setDescription($data['description']);
        }
        if (isset($data['estActif'])) {
            $questionnaire->setEstActif($data['estActif']);
        }
        if (isset($data['estDemarre'])) {
            $questionnaire->setEstDemarre($data['estDemarre']);
        }
        if (isset($data['scorePassage'])) {
            $questionnaire->setScorePassage($data['scorePassage']);
        }

        // Validation
        $errors = $this->validator->validate($questionnaire);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->persist($questionnaire);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeQuestionnaire($questionnaire), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            // Si le code d'accès existe déjà, on en génère un nouveau pas sur 
            if (str_contains($e->getMessage(), 'code')) {
                $questionnaire->regenererCodeAcces();
                $this->entityManager->persist($questionnaire);
                $this->entityManager->flush();
                return new JsonResponse($this->serializeQuestionnaire($questionnaire), Response::HTTP_CREATED);
            }

            return new JsonResponse(['error' => 'Erreur lors de la création'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour un questionnaire
     */
    #[Route('/{id}', name: 'questionnaire_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(int $id, Request $request): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour les champs
        if (isset($data['title'])) {
            $questionnaire->setTitre($data['title']);
        }
        if (isset($data['description'])) {
            $questionnaire->setDescription($data['description']);
        }
        if (isset($data['estActif'])) {
            $questionnaire->setEstActif($data['estActif']);
        }
        if (isset($data['estDemarre'])) {
            $questionnaire->setEstDemarre($data['estDemarre']);
        }
        if (isset($data['scorePassage'])) {
            $questionnaire->setScorePassage($data['scorePassage']);
        }

        // Validation
        $errors = $this->validator->validate($questionnaire);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeQuestionnaire($questionnaire));
    }

    /**
     * Supprimer un questionnaire
     */
    #[Route('/{id}', name: 'questionnaire_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($questionnaire);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sérialise un questionnaire en tableau
     */
    private function serializeQuestionnaire(Questionnaire $questionnaire, bool $includeQuestions = false): array
    {
        $data = [
            'id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitre(),
            'description' => $questionnaire->getDescription(),
            'accessCode' => $questionnaire->getCodeAcces(),
            'uniqueCode' => $questionnaire->getUniqueCode(),
            'isActive' => $questionnaire->isActive(),
            'isStarted' => $questionnaire->isStarted(),
            'scorePassage' => $questionnaire->getScorePassage(),
            'createdAt' => $questionnaire->getDateCreation()?->format('c'),
            'creePar' => [
                'id' => $questionnaire->getCreePar()?->getId(),
                'email' => $questionnaire->getCreePar()?->getEmail(),
                'lastName' => $questionnaire->getCreePar()?->getLastName(),
                'firstName' => $questionnaire->getCreePar()?->getFirstName()
            ]
        ];

        if ($includeQuestions) {
            $questions = [];
            foreach ($questionnaire->getQuestions() as $question) {
                $reponses = [];
                foreach ($question->getReponses() as $reponse) {
                    $reponses[] = [
                        'id' => $reponse->getId(),
                        'texte' => $reponse->getTexte(),
                        'estCorrecte' => $reponse->isCorrect(),
                        'numeroOrdre' => $reponse->getNumeroOrdre()
                    ];
                }

                $questions[] = [
                    'id' => $question->getId(),
                    'texte' => $question->getTexte(),
                    'numeroOrdre' => $question->getNumeroOrdre(),
                    'reponses' => $reponses
                ];
            }
            $data['questions'] = $questions;
        }

        return $data;
    }
}