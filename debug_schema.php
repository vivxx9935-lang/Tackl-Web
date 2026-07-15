<?php
require_once __DIR__ . '/php/db.php';

echo "=== USERS TABLE SCHEMA ===\n";
$users_schema = $pdo->query("DESCRIBE users")->fetchAll();
foreach ($users_schema as $col) {
    echo "{$col['Field']}: {$col['Type']} ({$col['Null']}, {$col['Key']})\n";
}

echo "\n=== TASKS TABLE SCHEMA ===\n";
$tasks_schema = $pdo->query("DESCRIBE tasks")->fetchAll();
foreach ($tasks_schema as $col) {
    echo "{$col['Field']}: {$col['Type']} ({$col['Null']}, {$col['Key']})\n";
}

echo "\n=== USERS DATA ===\n";
$users = $pdo->query("SELECT * FROM users")->fetchAll();
foreach ($users as $u) {
    echo "ID: {$u['id']}, Username: {$u['username']}, Type: {$u['account_type']}\n";
}

echo "\n=== TASKS DATA ===\n";
$tasks = $pdo->query("SELECT * FROM tasks")->fetchAll();
if (empty($tasks)) {
    echo "NO TASKS IN DATABASE\n";
} else {
    foreach ($tasks as $t) {
        echo "ID: {$t['id']}, user_id: {$t['user_id']}, task: {$t['task_name']}\n";
    }
}
?>
