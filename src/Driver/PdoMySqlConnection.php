<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;
use LPhenom\Db\Exception\ConnectionException;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\Param;
use PDO;
use PDOException;

/**
 * PDO MySQL database connection.
 *
 * Suitable for shared hosting environments.
 * Compatible with PHP 8.1+ (no reflection/eval/magic).
 * Note: PDO is not available in KPHP; use FfiMySqlConnection in compiled mode.
 */
final class PdoMySqlConnection implements ConnectionInterface
{
    /** @var PDO */
    private PDO $pdo;

    /**
     * @throws ConnectionException
     */
    public function __construct(string $dsn, string $username, string $password)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new ConnectionException(
                'Failed to connect to database: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return new PdoResult($stmt);
        } catch (PDOException $e) {
            throw new QueryException(
                'Query failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new QueryException(
                'Execute failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * Run callback inside a transaction.
     * Commits on success, rolls back on exception.
     *
     * KPHP note: callable is forbidden — TransactionCallbackInterface is used.
     *
     * @throws \Throwable
     */
    public function transaction(TransactionCallbackInterface $callback): int|string|bool|float|null
    {
        $this->pdo->beginTransaction();

        $exception = null;
        $result = null;
        try {
            $result = $callback->execute($this);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $exception = $e;
            $this->pdo->rollBack();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * @param array<string, Param> $params
     */
    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $param) {
            $stmt->bindValue($name, $param->value, $param->type);
        }
    }
}
