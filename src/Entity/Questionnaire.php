<?php

namespace App\Entity;

use App\Repository\QuestionnaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: QuestionnaireRepository::class)]
#[UniqueEntity(
    fields: ['codeAcces'],
    message: 'Ce code d\'accès est déjà utilisé.'
)]
class Questionnaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\sÀ-ÿ\-_\.\!\?]+$/u',
        message: 'Le titre contient des caractères non autorisés.'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\n\rÀ-ÿ\-_\.\!\?\,\;\:\(\)\"\']+$/u',
        message: 'La description contient des caractères non autorisés.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Assert\NotBlank(message: 'Le code d\'accès ne peut pas être vide.')]
    #[Assert\Length(
        min: 6,
        max: 6,
        exactMessage: 'Le code d\'accès doit contenir exactement {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]+$/',
        message: 'Le code d\'accès ne peut contenir que des lettres majuscules et des chiffres.'
    )]
    private ?string $codeAcces = null;

    #[ORM\Column]
    #[Assert\Type(
        type: 'bool',
        message: 'La valeur doit être un booléen.'
    )]
    private ?bool $estActif = true;

    #[ORM\Column]
    #[Assert\Type(
        type: 'bool',
        message: 'La valeur doit être un booléen.'
    )]
    private ?bool $estDemarre = false;

    #[ORM\Column]
    #[Assert\Type(
        type: 'integer',
        message: 'Le score de passage doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score de passage doit être entre {{ min }}% et {{ max }}%.'
    )]
    private ?int $scorePassage = 70;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de création doit être une date valide.'
    )]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\ManyToOne(inversedBy: 'questionnaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $creePar = null;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: Question::class, orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: TentativeQuestionnaire::class, orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $tentativesQuestionnaire;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->tentativesQuestionnaire = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
        $this->codeAcces = $this->genererCodeAcces();
    }

    /**
     * Génère un nouveau code d'accès pour ce questionnaire
     * Utilisé si le code actuel entre en conflit
     */
    public function regenererCodeAcces(): void
    {
        $this->codeAcces = $this->genererCodeAcces();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = trim($titre);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;

        return $this;
    }

    public function getCodeAcces(): ?string
    {
        return $this->codeAcces;
    }

    public function setCodeAcces(string $codeAcces): static
    {
        $this->codeAcces = strtoupper(trim($codeAcces));

        return $this;
    }

    /**
     * Getter pour uniqueCode (alias de codeAcces pour compatibilité frontend)
     */
    public function getUniqueCode(): ?string
    {
        return $this->codeAcces;
    }

    public function isActive(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;

        return $this;
    }

    public function isStarted(): ?bool
    {
        return $this->estDemarre;
    }

    public function setEstDemarre(bool $estDemarre): static
    {
        $this->estDemarre = $estDemarre;

        return $this;
    }

    public function getScorePassage(): ?int
    {
        return $this->scorePassage;
    }

    public function setScorePassage(int $scorePassage): static
    {
        $this->scorePassage = max(0, min(100, $scorePassage));

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getCreePar(): ?Utilisateur
    {
        return $this->creePar;
    }

    public function setCreePar(?Utilisateur $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getQuestionnaire() === $this) {
                $question->setQuestionnaire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TentativeQuestionnaire>
     */
    public function getTentativesQuestionnaire(): Collection
    {
        return $this->tentativesQuestionnaire;
    }

    public function addTentativeQuestionnaire(TentativeQuestionnaire $tentativeQuestionnaire): static
    {
        if (!$this->tentativesQuestionnaire->contains($tentativeQuestionnaire)) {
            $this->tentativesQuestionnaire->add($tentativeQuestionnaire);
            $tentativeQuestionnaire->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeTentativeQuestionnaire(TentativeQuestionnaire $tentativeQuestionnaire): static
    {
        if ($this->tentativesQuestionnaire->removeElement($tentativeQuestionnaire)) {
            // set the owning side to null (unless already changed)
            if ($tentativeQuestionnaire->getQuestionnaire() === $this) {
                $tentativeQuestionnaire->setQuestionnaire(null);
            }
        }

        return $this;
    }

    private function genererCodeAcces(): string
    {
        // Génère un code d'accès de 6 caractères aléatoires

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
}