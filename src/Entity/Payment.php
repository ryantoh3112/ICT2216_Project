<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use App\Entity\User;
use App\Entity\Ticket;
use App\Entity\History;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\PurchaseHistory;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $paymentDateTime = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'payment')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    private ?float $totalPrice = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'payment', cascade: ['persist', 'remove'])]
    
    private Collection $ticket;

    /**
     * @var Collection<int, History>
     */
    #[ORM\OneToMany(targetEntity: History::class, mappedBy: 'payment', cascade: ['persist', 'remove'])]
    private Collection $history;


      /** @var Collection<int, PurchaseHistory> */
    #[ORM\OneToMany(targetEntity: PurchaseHistory::class, mappedBy: "payment", cascade: ["persist", "remove"])]
    private Collection $purchaseHistory;

    public function __construct()
    {
        $this->ticket  = new ArrayCollection();
        $this->history = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentDateTime(): ?\DateTimeInterface
    {
        return $this->paymentDateTime;
    }

    public function setPaymentDateTime(\DateTimeInterface $paymentDateTime): static
    {
        $this->paymentDateTime = $paymentDateTime;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTicket(): Collection
    {
        return $this->ticket;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->ticket->contains($ticket)) {
            $this->ticket->add($ticket);
            $ticket->setPayment($this);
        }
        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->ticket->removeElement($ticket)) {
            if ($ticket->getPayment() === $this) {
                $ticket->setPayment(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, History>
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(History $history): static
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setPayment($this);
        }
        return $this;
    }

    public function removeHistory(History $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getPayment() === $this) {
                $history->setPayment(null);
            }
        }
        return $this;
    }

       /**
     * @return Collection<int, PurchaseHistory>
     */
    public function getPurchaseHistory(): Collection
    {
        return $this->purchaseHistory;
    }

    public function addPurchaseHistory(PurchaseHistory $entry): static
    {
        if (!$this->purchaseHistory->contains($entry)) {
            $this->purchaseHistory->add($entry);
            $entry->setPayment($this);
        }
        return $this;
    }

    public function removePurchaseHistory(PurchaseHistory $entry): static
    {
        if ($this->purchaseHistory->removeElement($entry)) {
            if ($entry->getPayment() === $this) {
                $entry->setPayment(null);
            }
        }
        return $this;
    }
}
