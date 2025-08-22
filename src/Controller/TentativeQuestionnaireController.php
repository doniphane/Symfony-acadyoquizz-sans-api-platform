<?php

namespace App\Controller;

use App\Entity\TentativeQuestionnaire;
use App\Entity\Questionnaire;
use App\Repository\TentativeQuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tentative_questionnaires')]
class TentativeQuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private TentativeQuestionnaireRepository $tentativeRepository
    ) {
    }

    /**
     * Récupérer toutes les tentatives de questionnaire
     */
    #[Route('', name: 'tentative_questionnaire_get_collection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCollection(Request $request): JsonResponse
    {
        $questionnaireId = $request->query->get('questionnaire');
        
        $tentatives = $this->tentativeRepository->findWithFiltersAndUser(
            $questionnaireId ? (int) $questionnaireId : null,
            $this->getUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        $data = [];
        foreach ($tentatives as $tentative) {
            $data[] = $this->serializeTentative($tentative);
        }

        return new JsonResponse($data);
    }

    /**
     * Récupérer une tentative spécifique
     */
    #[Route('/{id}', name: 'tentative_questionnaire_get_item', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getItem(int $id): JsonResponse
    {
        $tentative = $this->tentativeRepository->find($id);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette tentative
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $tentative->getQuestionnaire()->getCreePar() !== $this->getUser()
        ) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeTentative($tentative, true));
    }

    /**
     * Créer une nouvelle tentative (pour les quiz publics)
     */
    #[Route('', name: 'tentative_questionnaire_post', methods: ['POST'])]
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

        $tentative = new TentativeQuestionnaire();
        $tentative->setQuestionnaire($questionnaire);


        if (isset($data['prenomParticipant'])) {
            $tentative->setPrenomParticipant($data['prenomParticipant']);
        }
        if (isset($data['nomParticipant'])) {
            $tentative->setNomParticipant($data['nomParticipant']);
        }
        if (isset($data['dateDebut'])) {
            $tentative->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        }
        if (isset($data['dateFin'])) {
            $tentative->setDateFin(new \DateTimeImmutable($data['dateFin']));
        }
        if (isset($data['score'])) {
            $tentative->setScore($data['score']);
        }
        if (isset($data['nombreTotalQuestions'])) {
            $tentative->setNombreTotalQuestions($data['nombreTotalQuestions']);
        }

        // Si l'utilisateur est connecté, l'associer à la tentative
        if ($this->getUser()) {
            $tentative->setUtilisateur($this->getUser());
        }

        // Validation
        $errors = $this->validator->validate($tentative);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($tentative);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeTentative($tentative, true), Response::HTTP_CREATED);
    }

    /**
     * Mettre à jour une tentative
     */
    #[Route('/{id}', name: 'tentative_questionnaire_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(int $id, Request $request): JsonResponse
    {
        $tentative = $this->tentativeRepository->find($id);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a accès à cette tentative
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $tentative->getQuestionnaire()->getCreePar() !== $this->getUser()
        ) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }


        if (isset($data['prenomParticipant'])) {
            $tentative->setPrenomParticipant($data['prenomParticipant']);
        }
        if (isset($data['nomParticipant'])) {
            $tentative->setNomParticipant($data['nomParticipant']);
        }
        if (isset($data['dateFin'])) {
            $tentative->setDateFin(new \DateTimeImmutable($data['dateFin']));
        }
        if (isset($data['score'])) {
            $tentative->setScore($data['score']);
        }
        if (isset($data['nombreTotalQuestions'])) {
            $tentative->setNombreTotalQuestions($data['nombreTotalQuestion']);
        }


        $errors = $this->validator->validate($tentative);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeTentative($tentative, true));
    }

    /**
     * Supprimer une tentative
     */
    #[Route('/{id}', name: 'tentative_questionnaire_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $tentative = $this->tentativeRepository->find($id);

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($tentative);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sérialise une tentative en tableau
     */
    private function serializeTentative(TentativeQuestionnaire $tentative, bool $includeDetails = false): array
    {
        $data = [
            'id' => $tentative->getId(),
            'prenomParticipant' => $tentative->getPrenomParticipant(),
            'nomParticipant' => $tentative->getNomParticipant(),
            'dateDebut' => $tentative->getDateDebut()?->format('Y-m-d H:i:s'),
            'dateFin' => $tentative->getDateFin()?->format('Y-m-d H:i:s'),
            'score' => $tentative->getScore(),
            'nombreTotalQuestions' => $tentative->getNombreTotalQuestions(),
            'questionnaire' => '/api/questionnaires/' . $tentative->getQuestionnaire()->getId()
        ];

        if ($includeDetails) {
            $data['questionnaire'] = [
                'id' => $tentative->getQuestionnaire()->getId(),
                'titre' => $tentative->getQuestionnaire()->getTitre(),
                'codeAcces' => $tentative->getQuestionnaire()->getCodeAcces()
            ];

            if ($tentative->getUtilisateur()) {
                $data['utilisateur'] = [
                    'id' => $tentative->getUtilisateur()->getId(),
                    'nom' => $tentative->getUtilisateur()->getNom(),
                    'prenom' => $tentative->getUtilisateur()->getPrenom()
                ];
            }


            $reponsesUtilisateur = [];
            foreach ($tentative->getReponsesUtilisateur() as $reponseUtilisateur) {
                $reponsesUtilisateur[] = [
                    'id' => $reponseUtilisateur->getId(),
                    'question' => '/api/questions/' . $reponseUtilisateur->getQuestion()->getId(),
                    'reponse' => '/api/reponses/' . $reponseUtilisateur->getReponse()->getId(),
                    'dateReponse' => $reponseUtilisateur->getDateReponse()?->format('Y-m-d H:i:s')
                ];
            }
            $data['reponsesUtilisateur'] = $reponsesUtilisateur;
        }

        return $data;
    }
}