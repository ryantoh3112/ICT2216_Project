<?php

namespace App\Entity;

use App\Repository\JWTSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JWTSessionRepository::class)]
class JWTSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'jwtSession')]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]

    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $issuedAt = null; # Newly Added

    public function getId(): ?int
    {
        return $this->id;
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

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTime
    {
        return $this->revokedAt;
    }

    # Newly Added Method - Need to add to our database Schema
    public function setRevokedAt(?\DateTime $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }
    
    public function getIssuedAt(): ?\DateTime
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTime $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }
}
