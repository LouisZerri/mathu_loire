<?php

namespace App\Entity;

use App\Repository\CashRegisterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Feuille de caisse liée à une représentation : fond de caisse à l'ouverture et clôture.
 */
#[ORM\Entity(repositoryClass: CashRegisterRepository::class)]
class CashRegister
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const DENOMINATIONS_BILLS = [500, 200, 100, 50, 20, 10, 5];
    public const DENOMINATIONS_COINS = [2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Representation $representation = null;

    #[ORM\Column(type: Types::JSON)]
    private array $openingCounts = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $closingCounts = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $closingCheques = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $closingCb = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $openedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    public function __construct()
    {
        $this->openedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepresentation(): ?Representation
    {
        return $this->representation;
    }
    public function setRepresentation(Representation $r): self
    {
        $this->representation = $r;
        return $this;
    }

    public function getOpeningCounts(): array
    {
        return $this->openingCounts;
    }
    public function setOpeningCounts(array $c): self
    {
        $this->openingCounts = $c;
        return $this;
    }

    public function getClosingCounts(): ?array
    {
        return $this->closingCounts;
    }
    public function setClosingCounts(?array $c): self
    {
        $this->closingCounts = $c;
        return $this;
    }

    public function getClosingCheques(): ?array
    {
        return $this->closingCheques;
    }
    public function setClosingCheques(?array $c): self
    {
        $this->closingCheques = $c;
        return $this;
    }

    public function getClosingCb(): ?string
    {
        return $this->closingCb;
    }
    public function setClosingCb(?string $cb): self
    {
        $this->closingCb = $cb;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $s): self
    {
        $this->status = $s;
        return $this;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }
    public function setOpenedAt(\DateTimeImmutable $d): self
    {
        $this->openedAt = $d;
        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }
    public function setClosedAt(?\DateTimeImmutable $d): self
    {
        $this->closedAt = $d;
        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }
    public function setOpenedBy(?User $u): self
    {
        $this->openedBy = $u;
        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }
    public function setClosedBy(?User $u): self
    {
        $this->closedBy = $u;
        return $this;
    }

    /**
     * Calcule le total d'un comptage (ouverture ou clôture).
     */
    public static function computeCountTotal(array $counts): float
    {
        $total = 0.0;
        foreach ($counts as $denomination => $quantity) {
            $total += (float) $denomination * (int) $quantity;
        }
        return round($total, 2);
    }

    public function getOpeningTotal(): float
    {
        return self::computeCountTotal($this->openingCounts);
    }

    public function getClosingCashTotal(): float
    {
        return $this->closingCounts ? self::computeCountTotal($this->closingCounts) : 0.0;
    }

    public function getClosingChequesTotal(): float
    {
        if (!$this->closingCheques) return 0.0;
        return array_sum(array_map(fn($c) => (float) ($c['amount'] ?? 0), $this->closingCheques));
    }

    public function getClosingTotal(): float
    {
        return $this->getClosingCashTotal() + $this->getClosingChequesTotal() + (float) ($this->closingCb ?? 0);
    }
}
