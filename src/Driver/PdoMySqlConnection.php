<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
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
 */
final class PdoMySqlConnection implements ConnectionInterface
{
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
     * @throws \Exception
     */
    public function transaction(callable $callback): int|string|bool|float|null
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            /** @var int|string|bool|float|null $result */
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
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
