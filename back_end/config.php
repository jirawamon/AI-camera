<?php

// Database configuration - Update these with your Hostinger database details
// You can find these in Hostinger hPanel > Databases > MySQL Databases

define('DB_HOST', 'localhost');           // Usually 'localhost' on Hostinger
define('DB_NAME', 'u632902992_RIPPER_NET');  // Your database name
define('DB_USER', 'u632902992_hajibe');  // Your database username  
define('DB_PASS', 'Hajibe_5054'); // Your database password

// Dashboard settings
define('EVENTS_PER_PAGE', 20);
define('TIMEZONE', 'Asia/Bangkok'); // Change to your timezone

date_default_timezone_set(TIMEZONE);

// Database connection function
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dbHost = getenv('DB_HOST');
            $dbName = getenv('DB_NAME');
            $dbUser = getenv('DB_USER');
            $dbPass = getenv('DB_PASS');

            $dbHost = $dbHost === false ? DB_HOST : $dbHost;
            $dbName = $dbName === false ? DB_NAME : $dbName;
            $dbUser = $dbUser === false ? DB_USER : $dbUser;
            $dbPass = $dbPass === false ? DB_PASS : $dbPass;
            $dbPass = $dbPass === '__EMPTY__' ? '' : $dbPass;

            $pdo = new PDO(
                "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            // Set MySQL session timezone to Indochina/Bangkok (UTC+7)
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

?>
