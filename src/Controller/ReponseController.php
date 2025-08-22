<?php

namespace App\Controller;

use App\Entity\Reponse;
use App\Entity\Question;
use App\Repository\ReponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reponses')]
class ReponseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ReponseRepository $reponseRepository
    ) {
    }


    #[Route('', name: 'reponse_get_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(Request $request): JsonResponse
    {
        $questionId = $request->query->get('question') ? (int) $request->query->get('question') : null;

        $reponses = $this->reponseRepository->findWithFilters(
            $questionId,
            $this->getUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        $data = [];
        foreach ($reponses as $reponse) {
            $data[] = $this->serializeReponse($reponse);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer une réponse spécifique
     */
    #[Route('/{id}', name: 'reponse_get_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $reponse = $this->entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Réponse non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette réponse
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $reponse->getQuestion()->getQuestionnaire()->getCreePar() !== $this->getUser()
        ) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeReponse($reponse, true));
    }

    /**
     * Créer une nouvelle réponse
     */
    #[Route('', name: 'reponse_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function post(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['question'])) {
            return new JsonResponse(['error' => 'ID de la question requis'], Response::HTTP_BAD_REQUEST);
        }

        $question = $this->entityManager->getRepository(Question::class)->find($data['question']);

        if (!$question) {
            return new JsonResponse(['error' => 'Question non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($question->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $reponse = new Reponse();
        $reponse->setQuestion($question);


        if (isset($data['texte'])) {
            $reponse->setTexte($data['texte']);
        }
        if (isset($data['estCorrecte'])) {
            $reponse->setEstCorrecte($data['estCorrecte']);
        }
        if (isset($data['numeroOrdre'])) {
            $reponse->setNumeroOrdre($data['numeroOrdre']);
        } else {
            $maxOrder = $this->reponseRepository->findMaxOrderByQuestion($question);
            $reponse->setNumeroOrdre(($maxOrder ?? 0) + 1);
        }

        // Validation
        $errors = $this->validator->validate($reponse);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($reponse);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeReponse($reponse, true), Response::HTTP_CREATED);
    }

    /**
     * Mettre à jour une réponse
     */
    #[Route('/{id}', name: 'reponse_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(int $id, Request $request): JsonResponse
    {
        $reponse = $this->entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Réponse non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($reponse->getQuestion()->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour les champs
        if (isset($data['texte'])) {
            $reponse->setTexte($data['texte']);
        }
        if (isset($data['estCorrecte'])) {
            $reponse->setEstCorrecte($data['estCorrecte']);
        }
        if (isset($data['numeroOrdre'])) {
            $reponse->setNumeroOrdre($data['numeroOrdre']);
        }

        // Validation
        $errors = $this->validator->validate($reponse);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeReponse($reponse, true));
    }

    /**
     * Supprimer une réponse
     */
    #[Route('/{id}', name: 'reponse_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $reponse = $this->entityManager->getRepository(Reponse::class)->find($id);

        if (!$reponse) {
            return new JsonResponse(['error' => 'Réponse non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le créateur du questionnaire
        if ($reponse->getQuestion()->getQuestionnaire()->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($reponse);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sérialise une réponse en tableau
     */
    private function serializeReponse(Reponse $reponse, bool $includeDetails = false): array
    {
        $data = [
            'id' => $reponse->getId(),
            'texte' => $reponse->getTexte(),
            'estCorrecte' => $reponse->isCorrect(),
            'numeroOrdre' => $reponse->getNumeroOrdre(),
            'question' => '/api/questions/' . $reponse->getQuestion()->getId()
        ];

        if ($includeDetails) {
            $data['question'] = [
                'id' => $reponse->getQuestion()->getId(),
                'texte' => $reponse->getQuestion()->getTexte(),
                'numeroOrdre' => $reponse->getQuestion()->getNumeroOrdre()
            ];
        }

        return $data;
    }
}