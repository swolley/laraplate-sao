<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Modules\Core\Models\User;

/**
 * Who acted, and on behalf of what.
 *
 * In slice 1a this only attributes authorship. It exists now so that phase 3 —
 * which brings remote actors that Core versioning cannot attribute — can add
 * provenance without touching a single call site.
 */
final readonly class ChangeContext
{
    private function __construct(
        private ?int $user_id,
        private ?string $source_key,
        private bool $override,
    ) {}

    public static function forAutomation(string $source_key): self
    {
        return new self(null, $source_key, false);
    }

    public static function forUser(User $user): self
    {
        return new self($user->getKey(), null, false);
    }

    public function hasOverride(): bool
    {
        return $this->override;
    }

    public function sourceKey(): ?string
    {
        return $this->source_key;
    }

    public function userId(): ?int
    {
        return $this->user_id;
    }

    public function withOverride(): self
    {
        return new self($this->user_id, $this->source_key, true);
    }
}
