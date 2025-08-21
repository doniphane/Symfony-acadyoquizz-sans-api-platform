<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ReponseUtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ReponseUtilisateurRepository::class)]
#[UniqueEntity(
    fields: ['tentativeQuestionnaire', 'question'],
    message: 'Une réponse a déjà été donnée à cette question pour cette tentative.'
)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['reponse_utilisateur:read']],
    denormalizationContext: ['groups' => ['reponse_utilisateur:write']]
)]
class ReponseUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reponse_utilisateur:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reponsesUtilisateur')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reponse_utilisateur:read', 'reponse_utilisateur:write'])]
    #[Assert\NotNull(message: 'La tentative de questionnaire ne peut pas être nulle.')]
    private ?TentativeQuestionnaire $tentativeQuestionnaire = null;

    #[ORM\ManyToOne(inversedBy: 'reponsesUtilisateur')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reponse_utilisateur:read', 'reponse_utilisateur:write'])]
    #[Assert\NotNull(message: 'La question ne peut pas être nulle.')]
    private ?Question $question = null;

    #[ORM\ManyToOne(inversedBy: 'reponsesUtilisateur')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reponse_utilisateur:read', 'reponse_utilisateur:write'])]
    #[Assert\NotNull(message: 'La réponse ne peut pas être nulle.')]
    #[Assert\Expression(
        "this.getReponse() === null or this.getQuestion() === null or this.getReponse().getQuestion() === this.getQuestion()",
        message: 'La réponse sélectionnée ne correspond pas à la question posée.'
    )]
    private ?Reponse $reponse = null;

    #[ORM\Column]
    #[Groups(['reponse_utilisateur:read', 'reponse_utilisateur:write'])]
    #[Assert\NotNull(message: 'La date de réponse ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de réponse doit être une date valide.'
    )]
    #[Assert\Expression(
        "this.getDateReponse() === null or this.getTentativeQuestionnaire() === null or this.getDateReponse() >= this.getTentativeQuestionnaire().getDateDebut()",
        message: 'La date de réponse ne peut pas être antérieure au début de la tentative.'
    )]
    #[Assert\Expression(
        "this.getDateReponse() === null or this.getTentativeQuestionnaire() === null or this.getTentativeQuestionnaire().getDateFin() === null or this.getDateReponse() <= this.getTentativeQuestionnaire().getDateFin()",
        message: 'La date de réponse ne peut pas être postérieure à la fin de la tentative.'
    )]
    private ?\DateTimeImmutable $dateReponse = null;

    public function __construct()
    {
        $this->dateReponse = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTentativeQuestionnaire(): ?TentativeQuestionnaire
    {
        return $this->tentativeQuestionnaire;
    }

    public function setTentativeQuestionnaire(?TentativeQuestionnaire $tentativeQuestionnaire): static
    {
        $this->tentativeQuestionnaire = $tentativeQuestionnaire;

        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getReponse(): ?Reponse
    {
        return $this->reponse;
    }

    public function setReponse(?Reponse $reponse): static
    {
        $this->reponse = $reponse;

        return $this;
    }

    public function getDateReponse(): ?\DateTimeImmutable
    {
        return $this->dateReponse;
    }

    public function setDateReponse(\DateTimeImmutable $dateReponse): static
    {
        $this->dateReponse = $dateReponse;

        return $this;
    }

    /**
     * Vérifie si la réponse donnée est correcte
     */
    public function isCorrect(): bool
    {
        return $this->reponse !== null && $this->reponse->isCorrect();
    }

    /**
     * Vérifie la cohérence des données de cette réponse utilisateur
     */
    #[Assert\IsTrue(message: 'Les données de la réponse utilisateur sont incohérentes.')]
    public function isDataConsistent(): bool
    {
        // Vérifier que la réponse appartient bien à la question
        if ($this->reponse !== null && $this->question !== null) {
            if ($this->reponse->getQuestion() !== $this->question) {
                return false;
            }
        }

        // Vérifier que la question appartient bien au questionnaire de la tentative
        if ($this->question !== null && $this->tentativeQuestionnaire !== null) {
            $questionnaire = $this->tentativeQuestionnaire->getQuestionnaire();
            if ($questionnaire !== null && !$questionnaire->getQuestions()->contains($this->question)) {
                return false;
            }
        }

        return true;
    }
}