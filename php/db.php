<?php
$host = '127.0.0.1';
$db   = 'tackl_planner';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            task_name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            Tdate DATE NOT NULL,
            Ttime TIME NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id, Tdate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
    // Migrate existing tasks: assign to first user if user_id column doesn't exist
    $pdo->exec('ALTER TABLE tasks ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED DEFAULT 1 AFTER id');
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
