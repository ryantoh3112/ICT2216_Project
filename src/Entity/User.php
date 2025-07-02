<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\CartItem;
use App\Entity\Payment;
use App\Entity\History;
use App\Entity\JWTSession;
use App\Entity\Auth;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Auth $auth = null;

    /**
     * @var Collection<int, CartItem>
     */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $cartItems;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'user')]
    private Collection $payment;

    /**
     * @var Collection<int, History>
     */
    #[ORM\OneToMany(targetEntity: History::class, mappedBy: 'user')]
    private Collection $history;

    /**
     * @var Collection<int, JWTSession>
     */
    #[ORM\OneToMany(targetEntity: JWTSession::class, mappedBy: 'user')]
    private Collection $jwtSession;

    #[ORM\Column(length: 255)]
    private ?string $role = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $failedLoginCount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountStatus = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lockedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $otpReset = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $otpExpiresAt = null;

    public function __construct()
    {
        $this->cartItems  = new ArrayCollection();
        $this->payment    = new ArrayCollection();
        $this->history    = new ArrayCollection();
        $this->jwtSession = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getAuth(): ?Auth
    {
        return $this->auth;
    }

    public function setAuth(Auth $auth): static
    {
        if ($auth->getUser() !== $this) {
            $auth->setUser($this);
        }
        $this->auth = $auth;
        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $item): static
    {
        if (!$this->cartItems->contains($item)) {
            $this->cartItems->add($item);
            $item->setUser($this);
        }
        return $this;
    }

    public function removeCartItem(CartItem $item): static
    {
        if ($this->cartItems->removeElement($item)) {
            if ($item->getUser() === $this) {
                $item->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayment(): Collection
    {
        return $this->payment;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payment->contains($payment)) {
            $this->payment->add($payment);
            $payment->setUser($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payment->removeElement($payment)) {
            if ($payment->getUser() === $this) {
                $payment->setUser(null);
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
            $history->setUser($this);
        }
        return $this;
    }

    public function removeHistory(History $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getUser() === $this) {
                $history->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, JWTSession>
     */
    public function getJwtSession(): Collection
    {
        return $this->jwtSession;
    }

    public function addJwtSession(JWTSession $jwtSession): static
    {
        if (!$this->jwtSession->contains($jwtSession)) {
            $this->jwtSession->add($jwtSession);
            $jwtSession->setUser($this);
        }
        return $this;
    }

    public function removeJwtSession(JWTSession $jwtSession): static
    {
        if ($this->jwtSession->removeElement($jwtSession)) {
            if ($jwtSession->getUser() === $this) {
                $jwtSession->setUser(null);
            }
        }
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTime $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getFailedLoginCount(): ?int
    {
        return $this->failedLoginCount;
    }

    public function setFailedLoginCount(?int $failedLoginCount): static
    {
        $this->failedLoginCount = $failedLoginCount;
        return $this;
    }

    public function getAccountStatus(): ?string
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(?string $accountStatus): static
    {
        $this->accountStatus = $accountStatus;
        return $this;
    }

    public function getLockedAt(): ?\DateTime
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTime $lockedAt): static
    {
        $this->lockedAt = $lockedAt;
        return $this;
    }

    public function getOtpReset(): ?string
    {
        return $this->otpReset;
    }

    public function setOtpReset(?string $otpReset): static
    {
        $this->otpReset = $otpReset;
        return $this;
    }

    public function getOtpExpiresAt(): ?\DateTimeImmutable
    {
        return $this->otpExpiresAt;
    }

    public function setOtpExpiresAt(?\DateTimeImmutable $otpExpiresAt): static
    {
        $this->otpExpiresAt = $otpExpiresAt;
        return $this;
    }
}
