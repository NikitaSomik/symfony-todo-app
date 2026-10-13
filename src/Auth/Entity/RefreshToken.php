<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\Repository\RefreshTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_tokens_family_id', columns: ['family_id'])]
final class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * SHA-256 of the token (see RefreshTokenHash). The plain value is never persisted:
     * it only leaves the server in the refresh cookie.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RefreshTokenFamily $family;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(RefreshTokenHash $token, RefreshTokenFamily $family, \DateTimeImmutable $at, \DateTimeImmutable $expiresAt)
    {
        $this->token = $token->value;
        $this->family = $family;
        $this->createdAt = $at;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): RefreshTokenFamily
    {
        return $this->family;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->usedAt ??= $at;
    }

    /** Used longer ago than a second tab or a retried request can explain: someone holds a copy. */
    public function isReplayed(\DateTimeImmutable $now, int $reuseWindowSeconds): bool
    {
        return null !== $this->usedAt && $this->usedAt < $now->modify(sprintf('-%d seconds', $reuseWindowSeconds));
    }
}
