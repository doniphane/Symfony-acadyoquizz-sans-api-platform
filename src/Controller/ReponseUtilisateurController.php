<?php

namespace App\Controller;

use App\Entity\ReponseUtilisateur;
use App\Entity\TentativeQuestionnaire;
use App\Entity\Question;
use App\Entity\Reponse;
use App\Repository\ReponseUtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reponse_utilisateurs')]
class ReponseUtilisateurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ReponseUtilisateurRepository $reponseUtilisateurRepository
    ) {
    }

    /**
     * Récupérer toutes les réponses utilisateur 
     */
    #[Route('', name: 'reponse_utilisateur_get_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(Request $request): JsonResponse
    {
        $tentativeId = $request->query->get('tentative') ? (int) $request->query->get('tentative') : null;
        $questionId = $request->query->get('question') ? (int) $request->query->get('question') : null;

        $reponsesUtilisateur = $this->reponseUtilisateurRepository->findWithFilters(
            $tentativeId,
            $questionId,
            $this->getUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        $data = [];
        foreach ($reponsesUtilisateur as $reponseUtilisateur) {
            $data[] = $this->serializeReponseUtilisateur($reponseUtilisateur);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer une réponse utilisateur spécifique
     */
    #[Route('/{id}', name: 'reponse_utilisateur_get_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $reponseUtilisateur = $this->reponseUtilisateurRepository->findOneWithAccessCheck(
            $id,
            $this->getUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        if (!$reponseUtilisateur) {
            return new JsonResponse(['error' => 'Réponse utilisateur non trouvée ou accès refusé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializeReponseUtilisateur($reponseUtilisateur, true));
    }

    /**
     * Créer une nouvelle réponse utilisateur
     */
    #[Route('', name: 'reponse_utilisateur_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }


        $requiredFields = ['tentativeQuestionnaire', 'question', 'reponse'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
            }
        }


        $tentative = $this->entityManager->getRepository(TentativeQuestionnaire::class)->find($data['tentativeQuestionnaire']);
        $question = $this->entityManager->getRepository(Question::class)->find($data['question']);
        $reponse = $this->entityManager->getRepository(Reponse::class)->find($data['reponse']);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }
        if (!$question) {
            return new JsonResponse(['error' => 'Question non trouvée'], Response::HTTP_NOT_FOUND);
        }
        if (!$reponse) {
            return new JsonResponse(['error' => 'Réponse non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la réponse appartient bien à la question
        if ($reponse->getQuestion() !== $question) {
            return new JsonResponse(['error' => 'La réponse ne correspond pas à la question'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que la question appartient bien au questionnaire de la tentative oublier la dernier fois
        if (!$tentative->getQuestionnaire()->getQuestions()->contains($question)) {
            return new JsonResponse(['error' => 'La question ne fait pas partie de ce questionnaire'], Response::HTTP_BAD_REQUEST);
        }

        $reponseUtilisateur = new ReponseUtilisateur();
        $reponseUtilisateur->setTentativeQuestionnaire($tentative);
        $reponseUtilisateur->setQuestion($question);
        $reponseUtilisateur->setReponse($reponse);

        if (isset($data['dateReponse'])) {
            $reponseUtilisateur->setDateReponse(new \DateTimeImmutable($data['dateReponse']));
        }


        $errors = $this->validator->validate($reponseUtilisateur);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($reponseUtilisateur);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeReponseUtilisateur($reponseUtilisateur, true), Response::HTTP_CREATED);
    }

    /**
     * Mettre à jour une réponse utilisateur
     */
    #[Route('/{id}', name: 'reponse_utilisateur_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(int $id, Request $request): JsonResponse
    {
        $reponseUtilisateur = $this->entityManager->getRepository(ReponseUtilisateur::class)->find($id);

        if (!$reponseUtilisateur) {
            return new JsonResponse(['error' => 'Réponse utilisateur non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette réponse
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $reponseUtilisateur->getTentativeQuestionnaire()->getQuestionnaire()->getCreePar() !== $this->getUser()
        ) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }


        if (isset($data['reponse'])) {
            $reponse = $this->entityManager->getRepository(Reponse::class)->find($data['reponse']);
            if (!$reponse) {
                return new JsonResponse(['error' => 'Réponse non trouvée'], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la réponse appartient bien à la question
            if ($reponse->getQuestion() !== $reponseUtilisateur->getQuestion()) {
                return new JsonResponse(['error' => 'La réponse ne correspond pas à la question'], Response::HTTP_BAD_REQUEST);
            }

            $reponseUtilisateur->setReponse($reponse);
        }

        if (isset($data['dateReponse'])) {
            $reponseUtilisateur->setDateReponse(new \DateTimeImmutable($data['dateReponse']));
        }


        $errors = $this->validator->validate($reponseUtilisateur);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeReponseUtilisateur($reponseUtilisateur, true));
    }

    /**
     * Supprimer une réponse utilisateur
     */
    #[Route('/{id}', name: 'reponse_utilisateur_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $reponseUtilisateur = $this->entityManager->getRepository(ReponseUtilisateur::class)->find($id);

        if (!$reponseUtilisateur) {
            return new JsonResponse(['error' => 'Réponse utilisateur non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($reponseUtilisateur);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sérialise une réponse utilisateur en tableau
     */
    private function serializeReponseUtilisateur(ReponseUtilisateur $reponseUtilisateur, bool $includeDetails = false): array
    {
        $data = [
            'id' => $reponseUtilisateur->getId(),
            'dateReponse' => $reponseUtilisateur->getDateReponse()?->format('Y-m-d H:i:s'),
            'tentativeQuestionnaire' => '/api/tentative_questionnaires/' . $reponseUtilisateur->getTentativeQuestionnaire()->getId(),
            'question' => '/api/questions/' . $reponseUtilisateur->getQuestion()->getId(),
            'reponse' => '/api/reponses/' . $reponseUtilisateur->getReponse()->getId(),
            'isCorrect' => $reponseUtilisateur->isCorrect()
        ];

        if ($includeDetails) {
            $data['tentativeQuestionnaire'] = [
                'id' => $reponseUtilisateur->getTentativeQuestionnaire()->getId(),
                'prenomParticipant' => $reponseUtilisateur->getTentativeQuestionnaire()->getPrenomParticipant(),
                'nomParticipant' => $reponseUtilisateur->getTentativeQuestionnaire()->getNomParticipant()
            ];

            $data['question'] = [
                'id' => $reponseUtilisateur->getQuestion()->getId(),
                'texte' => $reponseUtilisateur->getQuestion()->getTexte(),
                'numeroOrdre' => $reponseUtilisateur->getQuestion()->getNumeroOrdre()
            ];

            $data['reponse'] = [
                'id' => $reponseUtilisateur->getReponse()->getId(),
                'texte' => $reponseUtilisateur->getReponse()->getTexte(),
                'estCorrecte' => $reponseUtilisateur->getReponse()->isCorrect()
            ];
        }

        return $data;
    }
}