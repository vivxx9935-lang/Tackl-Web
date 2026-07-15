<?php
require_once __DIR__ . '/php/db.php';

echo "=== TASKS IN DATABASE ===\n";
$result = $pdo->query('SELECT id, user_id, task_name, Tdate FROM tasks LIMIT 10')->fetchAll();
foreach ($result as $r) {
    echo "ID: {$r['id']}, user_id: {$r['user_id']}, task: {$r['task_name']}, date: {$r['Tdate']}\n";
}

echo "\n=== USERS IN DATABASE ===\n";
$users = $pdo->query('SELECT id, username, display_name FROM users')->fetchAll();
foreach ($users as $u) {
    echo "ID: {$u['id']}, user: {$u['username']}, name: {$u['display_name']}\n";
}

echo "\n=== CURRENT SESSION USER ID ===\n";
session_start();
if (!empty($_SESSION['user_id'])) {
    echo "Session user_id: {$_SESSION['user_id']}\n";
} else {
    echo "No user_id in session\n";
}
?>
