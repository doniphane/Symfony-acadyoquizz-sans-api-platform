<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le texte de la question est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'La question doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\NoSuspiciousCharacters()]
    #[Assert\Regex(
        pattern: '/\?$/',
        message: 'Une question doit se terminer par un point d\'interrogation.'
    )]
    private ?string $texte = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le numéro d\'ordre est obligatoire.')]
    #[Assert\Type(
        type: 'integer',
        message: 'Le numéro d\'ordre doit être un nombre entier.'
    )]
    #[Assert\Positive(message: 'Le numéro d\'ordre doit être un nombre positif.')]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Le numéro d\'ordre doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $numeroOrdre = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le questionnaire associé est obligatoire.')]
    #[Assert\Valid]
    private ?Questionnaire $questionnaire = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Reponse::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    #[Assert\Count(
        min: 2,
        minMessage: 'Une question doit avoir au moins {{ limit }} réponses.'
    )]
    private Collection $reponses;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: ReponseUtilisateur::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    private Collection $reponsesUtilisateur;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
        $this->reponsesUtilisateur = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(?string $texte): static
    {
        if ($texte !== null) {
            // Nettoyage simple et sécurisé
            $texte = trim($texte);

            // Supprime les caractères de contrôle dangereux
            $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texte);

            // Supprime les balises script/iframe dangereuses
            $texte = preg_replace('/<(script|iframe)[^>]*>.*?<\/\1>/is', '', $texte);

            // Si vide après nettoyage, on met null
            $texte = $texte !== '' ? $texte : null;
        }

        $this->texte = $texte;
        return $this;
    }

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(?int $numeroOrdre): static
    {
        // Validation simple des limites
        if ($numeroOrdre !== null) {
            $numeroOrdre = max(1, min(100, $numeroOrdre));
        }

        $this->numeroOrdre = $numeroOrdre;
        return $this;
    }

    public function getQuestionnaire(): ?Questionnaire
    {
        return $this->questionnaire;
    }

    public function setQuestionnaire(?Questionnaire $questionnaire): static
    {
        $this->questionnaire = $questionnaire;
        return $this;
    }

    /**
     * @return Collection<int, Reponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setQuestion($this);
        }

        return $this;
    }

    public function removeReponse(Reponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            if ($reponse->getQuestion() === $this) {
                $reponse->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReponseUtilisateur>
     */
    public function getReponsesUtilisateur(): Collection
    {
        return $this->reponsesUtilisateur;
    }

    public function addReponseUtilisateur(ReponseUtilisateur $reponseUtilisateur): static
    {
        if (!$this->reponsesUtilisateur->contains($reponseUtilisateur)) {
            $this->reponsesUtilisateur->add($reponseUtilisateur);
            $reponseUtilisateur->setQuestion($this);
        }

        return $this;
    }

    public function removeReponseUtilisateur(ReponseUtilisateur $reponseUtilisateur): static
    {
        if ($this->reponsesUtilisateur->removeElement($reponseUtilisateur)) {
            if ($reponseUtilisateur->getQuestion() === $this) {
                $reponseUtilisateur->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si la question a au moins une réponse correcte
     */
    public function hasCorrectAnswer(): bool
    {
        foreach ($this->reponses as $reponse) {
            if ($reponse->isCorrect()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compte le nombre de réponses correctes
     */
    public function getCorrectAnswersCount(): int
    {
        $count = 0;
        foreach ($this->reponses as $reponse) {
            if ($reponse->isCorrect()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Compte le nombre total de réponses
     */
    public function getAnswersCount(): int
    {
        return $this->reponses->count();
    }

    /**
     * Vérifie si la question est valide (a au moins 2 réponses et au moins une correcte)
     */
    #[Assert\IsTrue(message: 'Une question doit avoir au moins une réponse correcte.')]
    public function isValid(): bool
    {
        return $this->hasCorrectAnswer();
    }

    /**
     * Détermine si la question est à choix multiple (plusieurs réponses correctes) ou unique (une seule réponse correcte)
     */
    public function isMultipleChoice(): bool
    {
        $correctAnswersCount = 0;
        foreach ($this->reponses as $reponse) {
            if ($reponse->isCorrect()) {
                $correctAnswersCount++;
            }
        }
        return $correctAnswersCount > 1;
    }

    /**
     * Retourne le type de question sous forme de texte
     */
    public function getQuestionType(): string
    {
        return $this->isMultipleChoice() ? 'multiple' : 'single';
    }

    /**
     * Récupère toutes les réponses correctes
     * 
     * @return Reponse[]
     */
    public function getCorrectAnswers(): array
    {
        $correctAnswers = [];
        foreach ($this->reponses as $reponse) {
            if ($reponse->isCorrect()) {
                $correctAnswers[] = $reponse;
            }
        }

        return $correctAnswers;
    }

    /**
     * Récupère les réponses ordonnées par numéro d'ordre
     * 
     * @return Reponse[]
     */
    public function getAnswersOrderedByNumber(): array
    {
        $reponses = $this->reponses->toArray();
        usort($reponses, function (Reponse $a, Reponse $b): int {
            return $a->getNumeroOrdre() <=> $b->getNumeroOrdre();
        });

        return $reponses;
    }

    /**
     * Réordonne automatiquement les réponses par numéro d'ordre
     */
    public function reorderAnswers(): void
    {
        $reponses = $this->getAnswersOrderedByNumber();
        $order = 1;

        foreach ($reponses as $reponse) {
            $reponse->setNumeroOrdre($order++);
        }
    }
}