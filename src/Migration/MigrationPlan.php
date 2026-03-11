<?php

declare(strict_types=1);

namespace LPhenom\Db\Migration;

use DateTimeImmutable;

/**
 * @lphenom-build shared,kphp
 *
 * Immutable DTO representing a migration execution plan entry.
 *
 * KPHP note: constructor property promotion with readonly is not supported.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrationPlan
{
    /**
     * @var string
     */
    public string $version;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var DateTimeImmutable|null
     */
    public ?DateTimeImmutable $appliedAt;

    public function __construct(
        string $version,
        string $name,
        ?DateTimeImmutable $appliedAt = null,
    ) {
        $this->version   = $version;
        $this->name      = $name;
        $this->appliedAt = $appliedAt;
    }

    /**
     * Return a new instance marked as applied at the given time.
     */
    public function withAppliedAt(DateTimeImmutable $appliedAt): self
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
