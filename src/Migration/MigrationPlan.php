<?php

declare(strict_types=1);

namespace LPhenom\Db\Migration;

/**
 * Immutable DTO representing a migration execution plan entry.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrationPlan
{
    public function __construct(
        public readonly string $version,
        public readonly string $name,
        public readonly ?\DateTimeImmutable $appliedAt = null,
    ) {
    }

    /**
     * Return a new instance marked as applied at the given time.
     */
    public function withAppliedAt(\DateTimeImmutable $appliedAt): self
    {
        return new self($this->version, $this->name, $appliedAt);
    }

    /**
     * Whether this migration has been applied.
     */
    public function isApplied(): bool
    {
        return $this->appliedAt !== null;
    }
}

