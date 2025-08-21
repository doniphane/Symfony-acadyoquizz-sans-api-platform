<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\TentativeQuestionnaireRepository;
use App\State\TentativeQuestionnaireProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TentativeQuestionnaireRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['tentative_questionnaire:read']],
    denormalizationContext: ['groups' => ['tentative_questionnaire:write']]
)]
#[ApiResource(
    uriTemplate: '/questionnaires/{id}/participate',
    operations: [
        new Post(processor: TentativeQuestionnaireProcessor::class),
    ],
    normalizationContext: ['groups' => ['tentative_questionnaire:read']],
    denormalizationContext: ['groups' => ['tentative_questionnaire:write']],
    formats: ['jsonld', 'json']
)]
class TentativeQuestionnaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tentative_questionnaire:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $prenomParticipant = null;

    #[ORM\Column(length: 255)]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    #[Assert\NotBlank(message: 'Le nom de famille ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de famille doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/u',
        message: 'Le nom de famille ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $nomParticipant = null;

    #[ORM\Column]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    #[Assert\NotNull(message: 'La date de début ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de début doit être une date valide.'
    )]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de fin doit être une date valide.'
    )]
    #[Assert\Expression(
        "this.getDateFin() === null or this.getDateFin() >= this.getDateDebut()",
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['tentative_questionnaire:read'])]
    #[Assert\Type(
        type: 'integer',
        message: 'Le score doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        notInRangeMessage: 'Le score ne peut pas être négatif.'
    )]
    private ?int $score = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['tentative_questionnaire:read'])]
    #[Assert\Type(
        type: 'integer',
        message: 'Le nombre total de questions doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        notInRangeMessage: 'Le nombre total de questions ne peut pas être négatif.'
    )]
    private ?int $nombreTotalQuestions = null;

    #[ORM\ManyToOne(inversedBy: 'tentativesQuestionnaire')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    private ?Questionnaire $questionnaire = null;

    #[ORM\ManyToOne(inversedBy: 'tentativesQuestionnaire')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['tentative_questionnaire:read', 'tentative_questionnaire:write'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToMany(mappedBy: 'tentativeQuestionnaire', targetEntity: ReponseUtilisateur::class, orphanRemoval: true)]
    #[Groups(['tentative_questionnaire:read'])]
    #[Assert\Valid]
    private Collection $reponsesUtilisateur;

    public function __construct()
    {
        $this->reponsesUtilisateur = new ArrayCollection();
        $this->dateDebut = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenomParticipant(): ?string
    {
        return $this->prenomParticipant;
    }

    public function setPrenomParticipant(string $prenomParticipant): static
    {
        $this->prenomParticipant = trim($prenomParticipant);

        return $this;
    }

    public function getNomParticipant(): ?string
    {
        return $this->nomParticipant;
    }

    public function setNomParticipant(string $nomParticipant): static
    {
        $this->nomParticipant = trim($nomParticipant);

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        if ($score !== null) {
            $this->score = max(0, $score);
        } else {
            $this->score = null;
        }

        return $this;
    }

    public function getNombreTotalQuestions(): ?int
    {
        return $this->nombreTotalQuestions;
    }

    public function setNombreTotalQuestions(?int $nombreTotalQuestions): static
    {
        if ($nombreTotalQuestions !== null) {
            $this->nombreTotalQuestions = max(0, $nombreTotalQuestions);
        } else {
            $this->nombreTotalQuestions = null;
        }

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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

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
            $reponseUtilisateur->setTentativeQuestionnaire($this);
        }

        return $this;
    }

    public function removeReponseUtilisateur(ReponseUtilisateur $reponseUtilisateur): static
    {
        if ($this->reponsesUtilisateur->removeElement($reponseUtilisateur)) {
            // set the owning side to null (unless already changed)
            if ($reponseUtilisateur->getTentativeQuestionnaire() === $this) {
                $reponseUtilisateur->setTentativeQuestionnaire(null);
            }
        }

        return $this;
    }

    /**
     * Calcule le score basé sur les réponses correctes
     */
    public function calculerScore(): void
    {
        $score = 0;
        $totalQuestions = 0;

        foreach ($this->reponsesUtilisateur as $reponseUtilisateur) {
            $totalQuestions++;
            if ($reponseUtilisateur->getReponse() && $reponseUtilisateur->getReponse()->isCorrect()) {
                $score++;
            }
        }

        $this->setScore($score);
        $this->setNombreTotalQuestions($totalQuestions);
    }

    /**
     * Calcule le pourcentage de réussite
     */
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage doit être entre {{ min }}% et {{ max }}%.'
    )]
    public function getPourcentage(): float
    {
        if ($this->nombreTotalQuestions === null || $this->nombreTotalQuestions === 0) {
            return 0.0;
        }

        return round(($this->score / $this->nombreTotalQuestions) * 100, 2);
    }
}