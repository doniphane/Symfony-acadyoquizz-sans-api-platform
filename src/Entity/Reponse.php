<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ReponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReponseRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['reponse:read']],
    denormalizationContext: ['groups' => ['reponse:write']]
)]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reponse:read', 'questionnaire:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['reponse:read', 'reponse:write', 'questionnaire:read'])]
    #[Assert\NotBlank(message: 'Le texte de la réponse est obligatoire.')]
    #[Assert\Length(
        min: 1,
        max: 1000,
        minMessage: 'La réponse doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'La réponse ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[^<>{}"\\\\\[\]`]*$/',
        message: 'La réponse contient des caractères non autorisés.'
    )]
    #[Assert\Regex(
        pattern: '/^(?!.*(javascript:|data:|vbscript:|onload=|onerror=)).*$/i',
        message: 'La réponse contient du contenu potentiellement dangereux.'
    )]
    private ?string $texte = null;

    #[ORM\Column]
    #[Groups(['reponse:read', 'reponse:write', 'questionnaire:read'])]
    #[Assert\NotNull(message: 'Le statut de correction est obligatoire.')]
    #[Assert\Type(
        type: 'bool',
        message: 'Le statut de correction doit être un booléen (true/false).'
    )]
    private ?bool $estCorrecte = false;

    #[ORM\Column]
    #[Groups(['reponse:read', 'reponse:write', 'questionnaire:read'])]
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
        max: 50,
        notInRangeMessage: 'Le numéro d\'ordre doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $numeroOrdre = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reponse:read', 'reponse:write'])]
    #[Assert\NotNull(message: 'La question associée est obligatoire.')]
    private ?Question $question = null;

    #[ORM\OneToMany(mappedBy: 'reponse', targetEntity: ReponseUtilisateur::class, orphanRemoval: true)]
    private Collection $reponsesUtilisateur;

    public function __construct()
    {
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

    #[Groups(['reponse:read', 'questionnaire:read'])]
    public function isCorrect(): ?bool
    {
        return $this->estCorrecte;
    }

    public function setEstCorrecte(bool $estCorrecte): static
    {
        $this->estCorrecte = $estCorrecte;

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
        } elseif ($numeroOrdre > 50) {
            $numeroOrdre = 50;
        }

        $this->numeroOrdre = $numeroOrdre;

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
            $reponseUtilisateur->setReponse($this);
        }

        return $this;
    }

    public function removeReponseUtilisateur(ReponseUtilisateur $reponseUtilisateur): static
    {
        if ($this->reponsesUtilisateur->removeElement($reponseUtilisateur)) {
            // set the owning side to null (unless already changed)
            if ($reponseUtilisateur->getReponse() === $this) {
                $reponseUtilisateur->setReponse(null);
            }
        }

        return $this;
    }

    /**
     * Méthode utilitaire pour valider qu'une question a au moins une réponse correcte
     */
    public function validateQuestionHasCorrectAnswer(): bool
    {
        if (!$this->question) {
            return false;
        }

        foreach ($this->question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                return true;
            }
        }

        return false;
    }

    public function countCorrectAnswersInQuestion(): int
    {
        if (!$this->question) {
            return 0;
        }

        $count = 0;
        foreach ($this->question->getReponses() as $reponse) {
            if ($reponse->isCorrect()) {
                $count++;
            }
        }

        return $count;
    }
}