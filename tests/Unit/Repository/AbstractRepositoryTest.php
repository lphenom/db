<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Repository;

use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Param\ParamBinder;
use LPhenom\Db\Repository\AbstractRepository;
use PHPUnit\Framework\TestCase;

/**
 * Simple DTO for testing.
 *
 * KPHP note: constructor property promotion with readonly is not supported.
 */
final class UserDto
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var bool
     */
    public bool $active;

    public function __construct(int $id, string $name, bool $active)
    {
        $this->id     = $id;
        $this->name   = $name;
        $this->active = $active;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (bool) $row['active'],
        );
    }
}

/**
 * Concrete repository for testing purposes.
 *
 * KPHP note: fromRow() returns mixed (not object) — use concrete type in docblock.
 */
final class UserRepository extends AbstractRepository
{
    /**
     * @param array<string, mixed> $row
     * @return UserDto
     */
    protected function fromRow(array $row): mixed
    {
        return UserDto::fromRow($row);
    }

    public function findById(int $id): ?UserDto
    {
        /** @var UserDto|null $result */
        $result = $this->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int($id)],
        );

        return $result;
    }

    /** @return array<int, UserDto> */
    public function findAll(): array
    {
        /** @var array<int, UserDto> $results */
        $results = $this->fetchAll('SELECT * FROM users ORDER BY id ASC');

        return $results;
    }

    public function insert(int $id, string $name, bool $active): int
    {
        return $this->execute(
            'INSERT INTO users (id, name, active) VALUES (:id, :name, :active)',
            [
                ':id'     => ParamBinder::int($id),
                ':name'   => ParamBinder::str($name),
                ':active' => ParamBinder::int($active ? 1 : 0),
            ],
        );
    }
}

/**
 * @covers \LPhenom\Db\Repository\AbstractRepository
 */
final class AbstractRepositoryTest extends TestCase
{
    private PdoMySqlConnection $conn;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->conn = new PdoMySqlConnection('sqlite::memory:', '', '');
        $this->conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)');
        $this->repository = new UserRepository($this->conn);
    }

    public function testFetchOneReturnsNullWhenNoRows(): void
    {
        $user = $this->repository->findById(1);

        self::assertNull($user);
    }

    public function testFetchOneReturnsMappedDto(): void
    {
        $this->repository->insert(1, 'Alice', true);

        $user = $this->repository->findById(1);

        self::assertInstanceOf(UserDto::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('Alice', $user->name);
        self::assertTrue($user->active);
    }

    public function testFetchAllReturnsAllMappedDtos(): void
    {
        $this->repository->insert(1, 'Alice', true);
        $this->repository->insert(2, 'Bob', false);

        $users = $this->repository->findAll();

        self::assertCount(2, $users);
        self::assertContainsOnlyInstancesOf(UserDto::class, $users);
        self::assertSame('Alice', $users[0]->name);
        self::assertSame('Bob', $users[1]->name);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoRows(): void
    {
        $users = $this->repository->findAll();

        self::assertSame([], $users);
    }

    public function testExecuteReturnsAffectedRowCount(): void
    {
        $affected = $this->repository->insert(1, 'Alice', true);

        self::assertSame(1, $affected);
    }

    public function testFromRowMapsDtoCorrectly(): void
    {
        $this->repository->insert(42, 'Charlie', false);

        $user = $this->repository->findById(42);

        self::assertInstanceOf(UserDto::class, $user);
        self::assertSame(42, $user->id);
        self::assertSame('Charlie', $user->name);
        self::assertFalse($user->active);
    }
}
