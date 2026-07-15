<?php
require_once __DIR__ . '/php/db.php';

echo "=== TEST INSERT INTO TASKS ===\n\n";

// Get first user ID
$users = $pdo->query("SELECT id FROM users LIMIT 1")->fetch();
$testUserId = $users['id'] ?? null;

if (!$testUserId) {
    die("ERROR: No users in database\n");
}

echo "Testing with user_id: $testUserId\n\n";

try {
    $pdo->exec('DELETE FROM tasks WHERE task_name = "TEST_INSERT"');
    
    $insert = $pdo->prepare('INSERT INTO tasks (user_id, task_name, category, Tdate, Ttime) VALUES (?, ?, ?, ?, ?)');
    $result = $insert->execute([$testUserId, 'TEST_INSERT', 'Test', date('Y-m-d'), '12:00']);
    
    if ($result) {
        echo "✓ Insert succeeded\n\n";
        
        // Verify it was inserted
        $check = $pdo->prepare('SELECT id, user_id, task_name, Tdate FROM tasks WHERE task_name = "TEST_INSERT"');
        $check->execute();
        $task = $check->fetch();
        
        if ($task) {
            echo "✓ Task retrieved:\n";
            print_r($task);
        } else {
            echo "✗ Task not found after insert\n";
        }
    } else {
        echo "✗ Insert failed\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
