<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    die('NOT LOGGED IN');
}
require_once __DIR__ . '/php/db.php';

echo "Session data:\n";
echo "logged_in: " . $_SESSION['logged_in'] . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "user: " . ($_SESSION['user'] ?? 'NOT SET') . "\n";
echo "account_type: " . ($_SESSION['account_type'] ?? 'NOT SET') . "\n\n";

$userId = $_SESSION['user_id'] ?? null;
echo "Processing userId variable: " . ($userId ?? 'NULL') . "\n\n";

// Try a simple insert
if ($userId) {
    echo "Attempting insert with user_id=$userId...\n";
    $test = $pdo->prepare('INSERT INTO tasks (user_id, task_name, category, Tdate, Ttime) VALUES (?, ?, ?, ?, ?)');
    $test->execute([$userId, 'DEBUG TASK', 'Test', date('Y-m-d'), '10:00']);
    echo "Insert succeeded\n\n";
    
    // Check what was inserted
    $check = $pdo->prepare('SELECT * FROM tasks WHERE user_id = ?');
    $check->execute([$userId]);
    $result = $check->fetchAll();
    echo "Tasks for user_id=$userId:\n";
    foreach ($result as $r) {
        print_r($r);
    }
} else {
    echo "ERROR: userId is null!\n";
}
?>
