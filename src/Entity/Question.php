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
    #[Assert\Regex(
        pattern: '/^[^<>{}"\\\\\[\]`]*$/',
        message: 'La question contient des caractères non autorisés.'
    )]
    #[Assert\Regex(
        pattern: '/^(?!.*(javascript:|data:|vbscript:|onload=|onerror=|onclick=|onmouseover=)).*$/i',
        message: 'La question contient du contenu potentiellement dangereux.'
    )]
    #[Assert\Regex(
        pattern: '/^.+\?$/',
        message: 'Une question doit se terminer par un point d\'interrogation.'
    )]
    private ?string $texte = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le numéro d\'ordre est obligatoire.')]
    #[Assert\Type(
        type: 'integer',
        message: 'Le numéro d\'ordre doit être un nombre entier.'
    )]
    #[Assert\Positive(
        message: 'Le numéro d\'ordre doit être un nombre positif.'
    )]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Le numéro d\'ordre doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $numeroOrdre = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le questionnaire associé est obligatoire.')]
    private ?Questionnaire $questionnaire = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Reponse::class, orphanRemoval: true)]
    private Collection $reponses;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: ReponseUtilisateur::class, orphanRemoval: true)]
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

    public function setTexte(string $texte): static
    {
        // Nettoyer et sécuriser le texte
        $cleanTexte = trim($texte);

        // Supprimer les caractères de contrôle dangereux
        $cleanTexte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanTexte);

        // Encoder les entités HTML pour éviter les injections XSS
        $cleanTexte = htmlspecialchars($cleanTexte, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->texte = $cleanTexte;

        return $this;
    }

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(int $numeroOrdre): static
    {
        // Valider et sécuriser le numéro d'ordre
        if ($numeroOrdre < 1) {
            $numeroOrdre = 1;
        } elseif ($numeroOrdre > 100) {
            $numeroOrdre = 100;
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
            // set the owning side to null (unless already changed)
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
            // set the owning side to null (unless already changed)
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
    public function isValid(): bool
    {
        return $this->getAnswersCount() >= 2 && $this->hasCorrectAnswer();
    }

    /**
     * Récupère toutes les réponses correctes
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
     */
    public function getAnswersOrderedByNumber(): array
    {
        $reponses = $this->reponses->toArray();
        usort($reponses, function ($a, $b) {
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