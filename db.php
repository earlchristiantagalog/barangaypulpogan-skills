<?php
/**
 * db.php
 * Centralized Database Connection (PDO)
 * Barangay Pulpogan Bayanihan Portal
 *
 * Stack: Pure PHP 8.x — PDO with prepared statements only.
 *
 * Usage: require_once __DIR__ . '/db.php';  then call getDB();
 */

/**
 * Get a shared PDO database connection.
 * Auto-creates the database and residents table if they don't exist.
 *
 * @return PDO
 * @throws RuntimeException if connection fails
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbHost = 'localhost';
    $dbPort = '3306';
    $dbName = 'barangay_pulpogan_bayanihan';
    $dbUser = 'root';
    $dbPass = '';

    // Override from db_settings.php if it exists
    if (file_exists(__DIR__ . '/db_settings.php')) {
        require_once __DIR__ . '/db_settings.php';
        $dbHost = defined('DB_HOST') ? DB_HOST : $dbHost;
        $dbPort = defined('DB_PORT') ? DB_PORT : $dbPort;
        $dbName = defined('DB_NAME') ? DB_NAME : $dbName;
        $dbUser = defined('DB_USER') ? DB_USER : $dbUser;
        $dbPass = defined('DB_PASS') ? DB_PASS : $dbPass;
    }

    try {
        // Connect without database to create it if needed
        $basePdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $basePdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}`
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );
        $basePdo = null;

        // Connect to the target database
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // Create residents table if it does not exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS residents (
                id            VARCHAR(20)  NOT NULL PRIMARY KEY,
                full_name     VARCHAR(100) NOT NULL,
                district      VARCHAR(20)  NOT NULL,
                purok_address VARCHAR(150) NOT NULL,
                mobile        VARCHAR(15)  NOT NULL,
                email         VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role          ENUM('citizen','officer','it_officer') NOT NULL DEFAULT 'citizen',
                created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ------------------------------------------------------------------
        // SCHEMA MIGRATION — handle column renames from old 'purok' schema
        // ------------------------------------------------------------------
        $columns = [];
        $colRows = $pdo->query("SHOW COLUMNS FROM residents")->fetchAll();
        foreach ($colRows as $row) {
            $columns[] = $row['Field'];
        }

        // If old 'purok' column exists but 'district' does not → migrate
        if (in_array('purok', $columns, true) && !in_array('district', $columns, true)) {
            $pdo->exec("ALTER TABLE residents CHANGE COLUMN purok district VARCHAR(20) NOT NULL DEFAULT ''");
            // Refresh column list
            $columns = [];
            $colRows = $pdo->query("SHOW COLUMNS FROM residents")->fetchAll();
            foreach ($colRows as $row) {
                $columns[] = $row['Field'];
            }
        }

        // Add 'purok_address' if missing
        if (!in_array('purok_address', $columns, true)) {
            $pdo->exec("ALTER TABLE residents ADD COLUMN purok_address VARCHAR(150) NOT NULL DEFAULT '' AFTER district");
        }

        // Drop old 'purok' column if it still exists alongside 'district'
        if (in_array('purok', $columns, true) && in_array('district', $columns, true)) {
            $pdo->exec("ALTER TABLE residents DROP COLUMN purok");
        }

        // Add 'role' column if missing
        if (!in_array('role', $columns, true)) {
            $pdo->exec("ALTER TABLE residents ADD COLUMN role ENUM('citizen','officer','it_officer') NOT NULL DEFAULT 'citizen' AFTER password_hash");
        } else {
            // Update role enum to include 'it_officer' if missing
            $roleCol = null;
            foreach ($colRows as $row) {
                if ($row['Field'] === 'role') {
                    $roleCol = $row;
                    break;
                }
            }
            if ($roleCol && strpos($roleCol['Type'], 'it_officer') === false) {
                $pdo->exec("ALTER TABLE residents MODIFY COLUMN role ENUM('citizen','officer','it_officer') NOT NULL DEFAULT 'citizen'");
            }
        }

        // Create posts table if it does not exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id          VARCHAR(30)  NOT NULL PRIMARY KEY,
                resident_id VARCHAR(20)  NOT NULL,
                post_type   ENUM('offer','request') NOT NULL,
                title       VARCHAR(150) NOT NULL,
                description TEXT         NOT NULL,
                status      ENUM('Pending Verification','Verified','Rejected') NOT NULL DEFAULT 'Pending Verification',
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_resident (resident_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create announcements table if it does not exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title       VARCHAR(150) NOT NULL,
                body        TEXT         NOT NULL,
                author_id   VARCHAR(20)  NOT NULL,
                author_name VARCHAR(100) NOT NULL,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return $pdo;

    } catch (PDOException $e) {
        error_log(
            '[DB_ERROR] ' . date('Y-m-d H:i:s') . ' — '
            . $e->getMessage() . ' — IP: '
            . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        throw new RuntimeException('Database connection failed. Please try again later.');
    }
}
