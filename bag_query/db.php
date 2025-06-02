<?php
declare(strict_types=1);

/**
 * Establishes connections to the configured databases.
 *
 * @return array<string, PDO> An associative array of PDO connection objects.
 * @throws PDOException If a connection fails.
 */
function connect_databases(): array
{
    $config = require __DIR__ . '/../config.php'; // Assuming config.php is in the parent directory
    $connections = [];
    $db_keys = ['db_A', 'db_C', 'db_W'];

    foreach ($db_keys as $key) {
        $dbConfigs = $config['databases'] ?? null; // Get the 'databases' sub-array
        if (!$dbConfigs || !isset($dbConfigs[$key])) { // Check if sub-array and the specific key exist
            throw new Exception("Database configuration for '{$key}' not found in config['databases'] section of config.php");
        }
        $dbConf = $dbConfigs[$key]; // Access from the sub-array
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConf['host'],
            $dbConf['port'],
            $dbConf['database'],
            $dbConf['charset'] ?? 'utf8mb4'
        );
        try {
             $connections[$key] = new PDO($dsn, $dbConf['username'], $dbConf['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
             ]);
        } catch (PDOException $e) {
            // Re-throw with more context
            throw new PDOException("Connection failed for '{$key}': " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    return $connections;
} 