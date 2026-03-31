<?php

namespace App\Entity;

use App\Repository\SeatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeatRepository::class)]
class Seat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: '`row`', length: 10)]
    private ?string $row = null;

    #[ORM\Column]
    private ?int $number = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    /**
     * @var Collection<int, SeatAssignment>
     */
    #[ORM\OneToMany(targetEntity: SeatAssignment::class, mappedBy: 'seat', orphanRemoval: true)]
    private Collection $seatAssignments;

    public function __construct()
    {
        $this->seatAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRow(): ?string
    {
        return $this->row;
    }

    public function setRow(string $row): static
    {
        $this->row = $row;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
            $seatAssignment->setSeat($this);
        }

        return $this;
    }

    public function removeSeatAssignment(SeatAssignment $seatAssignment): static
    {
        if ($this->seatAssignments->removeElement($seatAssignment)) {
            // set the owning side to null (unless already changed)
            if ($seatAssignment->getSeat() === $this) {
                $seatAssignment->setSeat(null);
            }
        }

        return $this;
    }
}
