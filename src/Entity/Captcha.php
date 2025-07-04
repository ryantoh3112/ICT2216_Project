<?php

namespace App\Entity;

use App\Repository\CaptchaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CaptchaRepository::class)]
#[ORM\Table(name: 'captcha')]
class Captcha
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deviceFingerprint = null;

    /**
     * How many times this IP/device has submitted forgot-password in the current hour window.
     */
    #[ORM\Column(name: 'attempt_count', type: 'integer')]
    private int $attemptCount = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $lastAttemptAt;

    public function __construct(string $ipAddress, ?string $deviceFingerprint = null)
    {
        $this->ipAddress         = $ipAddress;
        $this->deviceFingerprint = $deviceFingerprint;
        $this->lastAttemptAt     = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function setDeviceFingerprint(?string $deviceFingerprint): static
    {
        $this->deviceFingerprint = $deviceFingerprint;
        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function setAttemptCount(int $count): static
    {
        $this->attemptCount = $count;
        return $this;
    }

    public function getLastAttemptAt(): \DateTimeInterface
    {
        return $this->lastAttemptAt;
    }

    public function setLastAttemptAt(\DateTimeInterface $lastAttemptAt): static
    {
        $this->lastAttemptAt = $lastAttemptAt;
        return $this;
    }

    /**
     * Increment the number of attempts and update the timestamp.
     */
    public function incrementAttemptCount(): static
    {
        $this->attemptCount++;
        $this->lastAttemptAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Reset the counter and update the timestamp.
     */
    public function reset(): static
    {
        $this->attemptCount  = 0;
        $this->lastAttemptAt = new \DateTimeImmutable();
        return $this;
    }
}
