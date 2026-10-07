<?php
/**
 * Factory Acoustic Monitoring and Material Compliance System
 * Database Connection Layer: db_connect.php
 * 
 * Implements a secure PDO (PHP Data Objects) connection singleton/factory
 * adhering to modern security standards:
 * - Prepared statement emulation disabled (prevents SQL injection)
 * - Strict exception error handling (PDO::ERRMODE_EXCEPTION)
 * - Strict UTF-8 multi-byte encoding (utf8mb4)
 * - Configurable via environment variables with safe localhost defaults
 */

declare(strict_types=1);

// Database configuration constants (can be overridden via system environment)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'factory_acoustic_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');           // set via environment variable

// Maps and Geocoding API Key
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: ''); // set via environment variable

/**
 * Establishes and returns a singleton PDO instance.
 *
 * @param bool $throwOnError Whether to throw exceptions or return null on failure
 * @return PDO|null
 * @throws PDOException
 */
function getDatabaseConnection(bool $throwOnError = false): ?PDO
{
    static $pdoInstance = null;

    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdoInstance = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdoInstance;
    } catch (PDOException $e) {
        error_log("[Database Connection Error] " . $e->getMessage());
        if ($throwOnError) {
            throw $e;
        }
        return null;
    }
}
