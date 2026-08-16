<?php
// config/db.php
// Database connection using PDO with automatic initialization

// Detect if running locally or on InfinityFree remote server
$is_localhost = (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0)));

if ($is_localhost) {
    $host = 'localhost';
    $dbname = 'final_db';
    $username = 'root';
    $password = '';
} else {
    $host = 'sql102.byetcluster.com';
    $dbname = 'if0_42663738_clash';
    $username = 'if0_42663738';
    $password = 'mwKYPBHrKd5L4E';
}

try {
    if ($is_localhost) {
        // Connect to MySQL server first (without database) to ensure it exists
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
    } else {
        // On remote server, connect directly to the existing database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Check if the tournaments table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tournaments'");
    $tableExists = $tableCheck->rowCount() > 0;

    if (!$tableExists) {
        $sqlFile = dirname(__DIR__) . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // Execute multiple queries
            $pdo->exec($sql);
        } else {
            throw new Exception("Initialization SQL file database.sql not found.");
        }
    }

    // Migration: Update game banner URLs if they are still legacy filenames
    try {
        $stmtMigrateCheck = $pdo->query("SELECT COUNT(*) FROM `games` WHERE `banner_url` LIKE '%.jpg'");
        if ($stmtMigrateCheck && $stmtMigrateCheck->fetchColumn() > 0) {
            $gameBannerUpdates = [
                'valorant' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80',
                'cs2' => 'https://images.unsplash.com/photo-1553481187-be93c21490a9?auto=format&fit=crop&w=600&q=80',
                'bgmi' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80',
                'freefire' => 'https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=600&q=80',
                'eafc' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80',
                'rocketleague' => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=600&q=80',
                'pubgpc' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=600&q=80',
                'codm' => 'https://images.unsplash.com/photo-1627856013091-fed6e4e30025?auto=format&fit=crop&w=600&q=80'
            ];
            $stmtMigrate = $pdo->prepare("UPDATE `games` SET `banner_url` = :url WHERE `slug` = :slug");
            foreach ($gameBannerUpdates as $slug => $url) {
                $stmtMigrate->execute(['url' => $url, 'slug' => $slug]);
            }
        }
    } catch (PDOException $ex) {
        // Safe fallback
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
} catch (Exception $e) {
    die("Application configuration error: " . $e->getMessage());
}
