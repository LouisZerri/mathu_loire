<?php

namespace App\Entity;

use App\Repository\RepresentationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepresentationRepository::class)]
class Representation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $datetime = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column]
    private ?int $maxOnlineReservations = null;

    #[ORM\Column]
    private ?int $venueCapacity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $adultPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $childPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $groupPrice = null;

    #[ORM\ManyToOne(inversedBy: 'representations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Show $show = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'representation', orphanRemoval: true)]
    private Collection $reservations;

    /**
     * @var Collection<int, SeatAssignment>
     */
    #[ORM\OneToMany(targetEntity: SeatAssignment::class, mappedBy: 'representation', orphanRemoval: true)]
    private Collection $seatAssignments;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->seatAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatetime(): ?\DateTime
    {
        return $this->datetime;
    }

    public function setDatetime(\DateTime $datetime): static
    {
        $this->datetime = $datetime;

        return $this;
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

    public function getMaxOnlineReservations(): ?int
    {
        return $this->maxOnlineReservations;
    }

    public function setMaxOnlineReservations(int $maxOnlineReservations): static
    {
        $this->maxOnlineReservations = $maxOnlineReservations;

        return $this;
    }

    public function getVenueCapacity(): ?int
    {
        return $this->venueCapacity;
    }

    public function setVenueCapacity(int $venueCapacity): static
    {
        $this->venueCapacity = $venueCapacity;

        return $this;
    }

    public function getAdultPrice(): ?string
    {
        return $this->adultPrice;
    }

    public function setAdultPrice(string $adultPrice): static
    {
        $this->adultPrice = $adultPrice;

        return $this;
    }

    public function getChildPrice(): ?string
    {
        return $this->childPrice;
    }

    public function setChildPrice(string $childPrice): static
    {
        $this->childPrice = $childPrice;

        return $this;
    }

    public function getGroupPrice(): ?string
    {
        return $this->groupPrice;
    }

    public function setGroupPrice(?string $groupPrice): static
    {
        $this->groupPrice = $groupPrice;

        return $this;
    }

    public function getShow(): ?Show
    {
        return $this->show;
    }

    public function setShow(?Show $show): static
    {
        $this->show = $show;

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setRepresentation($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getRepresentation() === $this) {
                $reservation->setRepresentation(null);
            }
        }

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
            $seatAssignment->setRepresentation($this);
        }

        return $this;
    }

    public function removeSeatAssignment(SeatAssignment $seatAssignment): static
    {
        if ($this->seatAssignments->removeElement($seatAssignment)) {
            // set the owning side to null (unless already changed)
            if ($seatAssignment->getRepresentation() === $this) {
                $seatAssignment->setRepresentation(null);
            }
        }

        return $this;
    }
}
