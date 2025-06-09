<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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
     * @var Collection<int, JWTBlacklist>
     */
    #[ORM\OneToMany(targetEntity: JWTBlacklist::class, mappedBy: 'user')]
    private Collection $jwtBlacklist;

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

    public function __construct()
    {
        $this->payment = new ArrayCollection();
        $this->history = new ArrayCollection();
        $this->jwtBlacklist = new ArrayCollection();
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
        // set the owning side of the relation if necessary
        if ($auth->getUser() !== $this) {
            $auth->setUser($this);
        }

        $this->auth = $auth;

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
            // set the owning side to null (unless already changed)
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
            // set the owning side to null (unless already changed)
            if ($history->getUser() === $this) {
                $history->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, JWTBlacklist>
     */
    public function getJwtBlacklist(): Collection
    {
        return $this->jwtBlacklist;
    }

    public function addJwtBlacklist(JWTBlacklist $jwtBlacklist): static
    {
        if (!$this->jwtBlacklist->contains($jwtBlacklist)) {
            $this->jwtBlacklist->add($jwtBlacklist);
            $jwtBlacklist->setUser($this);
        }

        return $this;
    }

    public function removeJwtBlacklist(JWTBlacklist $jwtBlacklist): static
    {
        if ($this->jwtBlacklist->removeElement($jwtBlacklist)) {
            // set the owning side to null (unless already changed)
            if ($jwtBlacklist->getUser() === $this) {
                $jwtBlacklist->setUser(null);
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
}
