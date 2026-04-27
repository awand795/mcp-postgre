<?php

namespace App\Services\Database;

/**
 * DriverFactory
 *
 * Creates the appropriate DriverAdapter based on database driver type.
 * Acts as a factory pattern to abstract multi-database metadata queries.
 */
class DriverFactory
{
    /**
     * Map of driver => adapter class
     */
    private static array $driverMap = [
        'pgsql'  => PostgreSqlAdapter::class,
        'mysql'  => MySqlAdapter::class,
        'mariadb' => MySqlAdapter::class, // MariaDB uses same adapter as MySQL
        'sqlsrv' => SqlServerAdapter::class,
        'sqlite' => SqliteAdapter::class,
    ];

    /**
     * Create a driver adapter instance for the given driver type.
     *
     * @throws \InvalidArgumentException if driver is not supported
     */
    public static function make(string $driver): DriverAdapter
    {
        if (!isset(self::$driverMap[$driver])) {
            throw new \InvalidArgumentException("Unsupported database driver: {$driver}. Supported: " . implode(', ', array_keys(self::$driverMap)));
        }

        $class = self::$driverMap[$driver];
        return new $class();
    }

    /**
     * Get list of supported drivers
     */
    public static function supportedDrivers(): array
    {
        return array_keys(self::$driverMap);
    }

    /**
     * Check if a driver is supported
     */
    public static function isSupported(string $driver): bool
    {
        return isset(self::$driverMap[$driver]);
    }

    /**
     * Get default port for a driver
     */
    public static function getDefaultPort(string $driver): ?int
    {
        return match ($driver) {
            'pgsql'   => 5432,
            'mysql'   => 3306,
            'mariadb' => 3306,
            'sqlsrv'  => 1433,
            'sqlite'  => null,
            default   => null,
        };
    }

    /**
     * Check if driver uses schema concept (PostgreSQL, SQL Server)
     * vs database-as-schema concept (MySQL/MariaDB)
     */
    public static function usesSchema(string $driver): bool
    {
        return in_array($driver, ['pgsql', 'sqlsrv']);
    }

    /**
     * Get version query for a driver
     */
    public static function getVersionQuery(string $driver): string
    {
        return match ($driver) {
            'pgsql', 'mysql', 'mariadb' => 'SELECT version() AS version',
            'sqlsrv' => 'SELECT @@version AS version',
            'sqlite' => 'SELECT sqlite_version() AS version',
            default => 'SELECT version() AS version',
        };
    }
}
