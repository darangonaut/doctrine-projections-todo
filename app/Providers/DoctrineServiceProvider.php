<?php

declare(strict_types=1);

namespace App\Providers;

use Darangonaut\DoctrineProjections\Support\SharedPdoDriver;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver as DbalDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MySQLDriver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgSQLDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Illuminate\Database\Connection as LaravelConnection;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Wiring Doctrine into the application. The projections package does none
 * of this on purpose — it only asks that something be bound to
 * EntityManagerInterface, which is what this provider does.
 */
class DoctrineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntityManagerInterface::class, function (): EntityManagerInterface {
            $config = $this->ormConfiguration();

            /** @var LaravelConnection $laravel */
            $laravel = $this->app->make('db')->connection();

            // Doctrine runs on the PDO handle Eloquent already opened, so
            // both sides are literally the same connection — one database,
            // one transaction, no config drift between them.
            $connection = new DbalConnection(
                ['dbname' => $laravel->getDatabaseName()],
                new SharedPdoDriver($this->driverFor($laravel), $laravel->getPdo()),
                $config,
            );

            $em = new EntityManager($connection, $config);

            $this->restrictSchemaToMappedTables($em);

            return $em;
        });
    }

    private function ormConfiguration(): Configuration
    {
        $config = new Configuration;
        $config->setMetadataDriverImpl(new AttributeDriver([app_path('Models/Entity')]));
        $config->setProxyDir(storage_path('framework/doctrine-proxies'));
        $config->setProxyNamespace('DoctrineProxies');
        $config->enableNativeLazyObjects(true);

        $cache = $this->app->isProduction()
            ? new PhpFilesAdapter('doctrine', 0, storage_path('framework/cache/doctrine'))
            : new ArrayAdapter;

        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);

        return $config;
    }

    private function driverFor(LaravelConnection $laravel): DbalDriver
    {
        return match ($laravel->getDriverName()) {
            'mysql', 'mariadb' => new MySQLDriver,
            'pgsql' => new PgSQLDriver,
            'sqlite' => new SQLiteDriver,
            default => throw new RuntimeException(
                "Pre spojenie '{$laravel->getDriverName()}' nemám DBAL driver.",
            ),
        };
    }

    /**
     * Without this Doctrine treats every table it can see as its own and
     * `doctrine:diff` proposes dropping `users`, `sessions` and `migrations`.
     */
    private function restrictSchemaToMappedTables(EntityManagerInterface $em): void
    {
        // Join tables are deliberately absent from this list. The filter
        // narrows what Doctrine introspects, and SchemaTool still creates
        // `task_tag` from the mapping — verified by dropping the table and
        // watching the CREATE come back.
        $owned = array_map(
            static fn ($meta): string => $meta->getTableName(),
            $em->getMetadataFactory()->getAllMetadata(),
        );

        $em->getConnection()->getConfiguration()->setSchemaAssetsFilter(
            static fn (string $table): bool => in_array($table, $owned, true),
        );
    }
}
