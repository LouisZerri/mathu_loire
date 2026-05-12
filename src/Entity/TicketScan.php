<?php

namespace App\Entity;

use App\Repository\TicketScanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketScanRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_ticket_scan', columns: ['reservation_id', 'ticket_index'])]
#[ORM\Index(name: 'idx_ticket_scan_reservation', columns: ['reservation_id'])]
class TicketScan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ticketScans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\Column]
    private ?int $ticketIndex = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $scannedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $scannedBy = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTicketIndex(): ?int
    {
        return $this->ticketIndex;
    }

    public function setTicketIndex(int $ticketIndex): static
    {
        $this->ticketIndex = $ticketIndex;

        return $this;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(\DateTimeImmutable $scannedAt): static
    {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getScannedBy(): ?User
    {
        return $this->scannedBy;
    }

    public function setScannedBy(?User $scannedBy): static
    {
        $this->scannedBy = $scannedBy;

        return $this;
    }
}
