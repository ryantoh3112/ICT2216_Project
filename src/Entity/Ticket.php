<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Event;
use App\Entity\Payment;
use App\Entity\History;
use App\Entity\TicketType;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $seatNumber = null;

    #[ORM\ManyToOne(inversedBy: 'ticket')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\ManyToOne(inversedBy: 'ticket')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    /**
     * @var Collection<int, History>
     */
    #[ORM\OneToMany(targetEntity: History::class, mappedBy: 'ticket')]
    private Collection $history;

    #[ORM\ManyToOne(inversedBy: 'ticket')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TicketType $ticketType = null;

    // ← NEW: QR token & expiry columns as attributes
    #[ORM\Column(type: "string", length: 64, unique: true, nullable: true)]
    private ?string $qrToken = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $qrExpiresAt = null;

    public function __construct()
    {
        $this->history = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeatNumber(): ?string
    {
        return $this->seatNumber;
    }

    public function setSeatNumber(string $seatNumber): static
    {
        $this->seatNumber = $seatNumber;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;
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
            $history->setTicket($this);
        }
        return $this;
    }

    public function removeHistory(History $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getTicket() === $this) {
                $history->setTicket(null);
            }
        }
        return $this;
    }

    public function getTicketType(): ?TicketType
    {
        return $this->ticketType;
    }

    public function setTicketType(?TicketType $ticketType): static
    {
        $this->ticketType = $ticketType;
        return $this;
    }

    public function getQrToken(): ?string
    {
        return $this->qrToken;
    }

    public function setQrToken(string $qrToken): static
    {
        $this->qrToken = $qrToken;
        return $this;
    }

    public function getQrExpiresAt(): ?\DateTimeInterface
    {
        return $this->qrExpiresAt;
    }

    public function setQrExpiresAt(?\DateTimeInterface $qrExpiresAt): static
    {
        $this->qrExpiresAt = $qrExpiresAt;
        return $this;
    }
}
