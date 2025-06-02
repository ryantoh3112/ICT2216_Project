<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $eventName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $eventDescription = null;

    #[ORM\Column]
    private ?int $eventCapacity = null;

    #[ORM\Column]
    private ?\DateTime $eventPurchaseStartDate = null;

    #[ORM\Column]
    private ?\DateTime $eventPurchaseEndDate = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'event')]
    private Collection $ticket;

    #[ORM\ManyToOne(inversedBy: 'event')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Venue $venue = null;

    #[ORM\Column(length: 255)]
    private ?string $eventCategory = null;

    #[ORM\Column(length: 255)]
    private ?string $eventOrganiser = null;

    public function __construct()
    {
        $this->ticket = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): static
    {
        $this->eventName = $eventName;

        return $this;
    }

    public function getEventDescription(): ?string
    {
        return $this->eventDescription;
    }

    public function setEventDescription(?string $eventDescription): static
    {
        $this->eventDescription = $eventDescription;

        return $this;
    }

    public function getEventCapacity(): ?int
    {
        return $this->eventCapacity;
    }

    public function setEventCapacity(int $eventCapacity): static
    {
        $this->eventCapacity = $eventCapacity;

        return $this;
    }

    public function getEventPurchaseStartDate(): ?\DateTime
    {
        return $this->eventPurchaseStartDate;
    }

    public function setEventPurchaseStartDate(\DateTime $eventPurchaseStartDate): static
    {
        $this->eventPurchaseStartDate = $eventPurchaseStartDate;

        return $this;
    }

    public function getEventPurchaseEndDate(): ?\DateTime
    {
        return $this->eventPurchaseEndDate;
    }

    public function setEventPurchaseEndDate(\DateTime $eventPurchaseEndDate): static
    {
        $this->eventPurchaseEndDate = $eventPurchaseEndDate;

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
            $ticket->setEvent($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->ticket->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }

        return $this;
    }

    public function getVenue(): ?Venue
    {
        return $this->venue;
    }

    public function setVenue(?Venue $venue): static
    {
        $this->venue = $venue;

        return $this;
    }

    public function getEventCategory(): ?string
    {
        return $this->eventCategory;
    }

    public function setEventCategory(string $eventCategory): static
    {
        $this->eventCategory = $eventCategory;

        return $this;
    }

    public function getEventOrganiser(): ?string
    {
        return $this->eventOrganiser;
    }

    public function setEventOrganiser(string $eventOrganiser): static
    {
        $this->eventOrganiser = $eventOrganiser;

        return $this;
    }
}
