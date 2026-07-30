<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entity\TodoList as TodoListEntity;
use App\Models\Projection\TodoList;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * This suite is here because the obvious wiring — reading
 * `config('database.connections.sqlite')` and handing it to Doctrine —
 * looks correct and silently puts the two sides on different databases.
 * Under `:memory:` it is not even subtle: Doctrine would get its own
 * empty database and every test above would fail.
 */
final class SharedConnectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function doctrine_and_eloquent_hold_the_very_same_pdo_handle(): void
    {
        $doctrine = $this->em()->getConnection()->getNativeConnection();

        self::assertSame(DB::connection()->getPdo(), $doctrine);
    }

    #[Test]
    public function the_test_database_really_is_in_memory(): void
    {
        // If this ever stops being :memory:, the suite is no longer
        // testing the case that motivated SharedPdoDriver.
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    #[Test]
    public function a_laravel_transaction_rolls_back_a_doctrine_flush(): void
    {
        try {
            DB::transaction(function (): void {
                $list = new TodoListEntity('Zrušený', 'zruseny');

                $this->em()->persist($list);
                $this->em()->flush();

                // Same connection, so this rollback reaches the rows
                // Doctrine just wrote — two connections could not do that.
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->forget();

        self::assertSame(0, TodoList::query()->where('slug', 'zruseny')->count());
    }

    #[Test]
    public function a_committed_transaction_keeps_both_sides_writes(): void
    {
        DB::transaction(function (): void {
            $list = new TodoListEntity('Ostal', 'ostal');

            $this->em()->persist($list);
            $this->em()->flush();
        });

        $this->forget();

        self::assertSame(1, TodoList::query()->where('slug', 'ostal')->count());
    }

    #[Test]
    public function the_entity_manager_is_a_singleton(): void
    {
        self::assertSame(
            $this->app->make(EntityManagerInterface::class),
            $this->app->make(EntityManagerInterface::class),
        );
    }
}
