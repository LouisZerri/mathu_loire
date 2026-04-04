<?php

namespace App\Entity;

use App\Repository\SeatAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SeatAssignmentRepository::class)]
class SeatAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['assigned', 'blocked'], message: 'Statut invalide.')]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'seatAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Seat $seat = null;

    #[ORM\ManyToOne(inversedBy: 'seatAssignments')]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(inversedBy: 'seatAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Representation $representation = null;

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

    public function getSeat(): ?Seat
    {
        return $this->seat;
    }

    public function setSeat(?Seat $seat): static
    {
        $this->seat = $seat;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

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
}
