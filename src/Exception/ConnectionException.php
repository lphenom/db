<?php

declare(strict_types=1);

namespace LPhenom\Db\Exception;

use RuntimeException;

/**
 * @lphenom-build shared,kphp
 *
 * Thrown when a database connection cannot be established.
 */
final class ConnectionException extends RuntimeException
{
}
