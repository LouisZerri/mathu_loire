<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['pending', 'validated', 'modified', 'cancelled'], message: 'Statut invalide.')]
    private ?string $status = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Veuillez indiquer le nombre d\'adultes.')]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'Doit être entre {{ min }} et {{ max }}.')]
    private ?int $nbAdults = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Veuillez indiquer le nombre d\'enfants.')]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'Doit être entre {{ min }} et {{ max }}.')]
    private ?int $nbChildren = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'Doit être entre {{ min }} et {{ max }}.')]
    private ?int $nbInvitations = null;

    #[ORM\Column]
    private ?bool $isPMR = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.')]
    private ?string $spectatorLastName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.')]
    private ?string $spectatorFirstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    private ?string $spectatorCity = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Regex(pattern: '/^(?:(?:\+33|0)\s?[1-9])(?:[\s.\-]?\d{2}){4}$/', message: 'Numéro de téléphone invalide (ex: 06 12 34 56 78).')]
    private ?string $spectatorPhone = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(mode: 'strict', message: 'L\'adresse email n\'est pas valide.')]
    private ?string $spectatorEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $spectatorComment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminComment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Representation $representation = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, SeatAssignment>
     */
    #[ORM\OneToMany(targetEntity: SeatAssignment::class, mappedBy: 'reservation', cascade: ['remove'])]
    private Collection $seatAssignments;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'reservation', cascade: ['remove'], orphanRemoval: true)]
    private Collection $payments;

    #[ORM\Column(nullable: true)]
    private ?int $checkoutIntentId = null;

    #[ORM\Column(length: 64)]
    private ?string $token = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    public function __construct()
    {
        $this->seatAssignments = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNbAdults(): ?int
    {
        return $this->nbAdults;
    }

    public function setNbAdults(int $nbAdults): static
    {
        $this->nbAdults = $nbAdults;

        return $this;
    }

    public function getNbChildren(): ?int
    {
        return $this->nbChildren;
    }

    public function setNbChildren(int $nbChildren): static
    {
        $this->nbChildren = $nbChildren;

        return $this;
    }

    public function getNbInvitations(): ?int
    {
        return $this->nbInvitations;
    }

    public function setNbInvitations(int $nbInvitations): static
    {
        $this->nbInvitations = $nbInvitations;

        return $this;
    }

    public function isPMR(): ?bool
    {
        return $this->isPMR;
    }

    public function setIsPMR(bool $isPMR): static
    {
        $this->isPMR = $isPMR;

        return $this;
    }

    public function getSpectatorLastName(): ?string
    {
        return $this->spectatorLastName;
    }

    public function setSpectatorLastName(string $spectatorLastName): static
    {
        $this->spectatorLastName = $spectatorLastName;

        return $this;
    }

    public function getSpectatorFirstName(): ?string
    {
        return $this->spectatorFirstName;
    }

    public function setSpectatorFirstName(string $spectatorFirstName): static
    {
        $this->spectatorFirstName = $spectatorFirstName;

        return $this;
    }

    public function getSpectatorCity(): ?string
    {
        return $this->spectatorCity;
    }

    public function setSpectatorCity(string $spectatorCity): static
    {
        $this->spectatorCity = $spectatorCity;

        return $this;
    }

    public function getSpectatorPhone(): ?string
    {
        return $this->spectatorPhone;
    }

    public function setSpectatorPhone(string $spectatorPhone): static
    {
        $this->spectatorPhone = $spectatorPhone;

        return $this;
    }

    public function getSpectatorEmail(): ?string
    {
        return $this->spectatorEmail;
    }

    public function setSpectatorEmail(string $spectatorEmail): static
    {
        $this->spectatorEmail = $spectatorEmail;

        return $this;
    }

    public function getSpectatorComment(): ?string
    {
        return $this->spectatorComment;
    }

    public function setSpectatorComment(?string $spectatorComment): static
    {
        $this->spectatorComment = $spectatorComment;

        return $this;
    }

    public function getAdminComment(): ?string
    {
        return $this->adminComment;
    }

    public function setAdminComment(?string $adminComment): static
    {
        $this->adminComment = $adminComment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRepresentation(): ?Representation
    {
        return $this->representation;
    }

    public function setRepresentation(?Representation $representation): static
    {
        $this->representation = $representation;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, SeatAssignment>
     */
    public function getSeatAssignments(): Collection
    {
        return $this->seatAssignments;
    }

    public function addSeatAssignment(SeatAssignment $seatAssignment): static
    {
        if (!$this->seatAssignments->contains($seatAssignment)) {
            $this->seatAssignments->add($seatAssignment);
            $seatAssignment->setReservation($this);
        }

        return $this;
    }

    public function removeSeatAssignment(SeatAssignment $seatAssignment): static
    {
        if ($this->seatAssignments->removeElement($seatAssignment)) {
            // set the owning side to null (unless already changed)
            if ($seatAssignment->getReservation() === $this) {
                $seatAssignment->setReservation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setReservation($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getReservation() === $this) {
                $payment->setReservation(null);
            }
        }

        return $this;
    }

    public function getCheckoutIntentId(): ?int
    {
        return $this->checkoutIntentId;
    }

    public function setCheckoutIntentId(?int $checkoutIntentId): static
    {
        $this->checkoutIntentId = $checkoutIntentId;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }
}
