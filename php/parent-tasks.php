<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = htmlspecialchars($_SESSION['user'] ?? 'Planner', ENT_QUOTES, 'UTF-8');
$accountType = trim($_SESSION['account_type'] ?? '');
$adultRole = trim($_SESSION['adult_role'] ?? '');

if ($userId && ($accountType === '' || $adultRole === '')) {
    $stmt = $pdo->prepare('SELECT account_type, adult_role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $accountType = trim($profile['account_type'] ?? $accountType);
        $adultRole = trim($profile['adult_role'] ?? $adultRole);
        $_SESSION['account_type'] = $accountType;
        $_SESSION['adult_role'] = $adultRole;
    }
}

$isParentRole = ($accountType === 'Parent') || ($accountType === 'Adult' && stripos($adultRole, 'Parent') !== false);
if (!$isParentRole && !empty($userId)) {
    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE parent_id = ?');
    $childCountStmt->execute([$userId]);
    if ((int)$childCountStmt->fetchColumn() > 0) {
        $isParentRole = true;
    }
}

// If not a parent, redirect to regular tasks page
if (!$isParentRole) {
    header('Location: tasks.php');
    exit;
}

$childAccounts = [];
$errors = [];
$successMessage = '';
$selectedChildId = isset($_GET['child_id']) ? (int)$_GET['child_id'] : null;
$selectedChild = null;
$childTasks = [];

// Get parent's linked child and teen accounts
if ($userId) {
    $childStmt = $pdo->prepare('SELECT id, username, display_name, age, account_type FROM users WHERE parent_id = ? ORDER BY account_type ASC, display_name ASC');
    $childStmt->execute([$userId]);
    $childAccounts = $childStmt->fetchAll(PDO::FETCH_ASSOC);
}

// If a child is selected, verify they belong to this parent and load their tasks
if ($selectedChildId && $userId) {
    $childStmt = $pdo->prepare('SELECT id, username, display_name, age, account_type FROM users WHERE id = ? AND parent_id = ?');
    $childStmt->execute([$selectedChildId, $userId]);
    $selectedChild = $childStmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedChild) {
        $errors[] = 'Invalid child account selected.';
        $selectedChildId = null;
    } else {
        // Load child's tasks
        $tasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status, created_at FROM tasks WHERE user_id = ? ORDER BY Tdate ASC, Ttime ASC, created_at DESC');
        $tasksStmt->execute([$selectedChildId]);
        $childTasks = $tasksStmt->fetchAll();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : null;
    $targetChildId = isset($_POST['child_id']) ? (int)$_POST['child_id'] : $selectedChildId;

    // Verify the child belongs to this parent
    if ($targetChildId && $userId) {
        $verifyStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND parent_id = ?');
        $verifyStmt->execute([$targetChildId, $userId]);
        $childCheck = $verifyStmt->fetch();

        if (!$childCheck) {
            $errors[] = 'Invalid child account.';
        } else {
            if ($action === 'delete' && $taskId) {
                $delete = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
                $delete->execute([$taskId, $targetChildId]);
                header("Location: parent-tasks.php?child_id=$targetChildId&deleted=1");
                exit;
            }

            if ($action === 'finish' && $taskId) {
                $complete = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?');
                $complete->execute(['complete', $taskId, $targetChildId]);
                header("Location: parent-tasks.php?child_id=$targetChildId&finished=1");
                exit;
            }

            if ($action === 'add') {
                $taskTitle = trim($_POST['task_title'] ?? '');
                $taskCategory = trim($_POST['task_category'] ?? 'School Work');
                $customCategory = trim($_POST['custom_category'] ?? '');
                $taskDate = trim($_POST['task_date'] ?? '');
                $taskTime = trim($_POST['task_time'] ?? '');

                if ($taskCategory === '_custom_') {
                    $taskCategory = $customCategory;
                }

                if ($taskTitle === '') {
                    $errors[] = 'Please enter a task title.';
                }
                if ($taskDate === '') {
                    $errors[] = 'Please select a date for the task.';
                }
                if ($taskCategory === '' || $taskCategory === '_custom_') {
                    $errors[] = 'Please choose or type a category.';
                }

                if (empty($errors)) {
                    try {
                        $insert = $pdo->prepare('INSERT INTO tasks (user_id, task_name, category, Tdate, Ttime) VALUES (?, ?, ?, ?, ?)');
                        $insert->execute([$targetChildId, $taskTitle, $taskCategory, $taskDate, $taskTime ?: null]);
                        header("Location: parent-tasks.php?child_id=$targetChildId&added=1");
                        exit;
                    } catch (PDOException $e) {
                        $errors[] = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$successMessage = '';
if (isset($_GET['added'])) {
    $successMessage = 'Task assigned successfully.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Task deleted successfully.';
} elseif (isset($_GET['finished'])) {
    $successMessage = 'Task marked as finished.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Manage Child Tasks</title>
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/windows.css">
  <link rel="stylesheet" href="../css/buttons.css">
  <link rel="stylesheet" href="../css/tabs.css">
  <link rel="stylesheet" href="../css/forms.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
  <div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-column">
      <?php include __DIR__ . '/header.php'; ?>
      <main class="app-window page-shell">
        <section class="section window-panel">
          <div class="panel-header">
            <h2>Manage Child Tasks</h2>
            <p>Assign and monitor tasks for your linked child and teen accounts.</p>
          </div>

          <?php if (empty($childAccounts)): ?>
            <div class="error-panel">
              <p>You don't have any child accounts linked yet. Create a child or teen account first, or link existing accounts in your settings.</p>
            </div>
          <?php else: ?>
            <div class="panel-grid">
              <!-- Child Account Selector -->
              <article class="card category-card" id="child-selector">
                <h3>Select Child Account</h3>
                <p>Choose which account to manage tasks for.</p>
                <div class="child-list">
                  <?php foreach ($childAccounts as $child): ?>
                    <a href="parent-tasks.php?child_id=<?php echo $child['id']; ?>" class="button button-secondary" style="margin-bottom: 8px; display: block; text-align: left;">
                      <strong><?php echo htmlspecialchars($child['display_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <small><?php echo htmlspecialchars($child['account_type'], ENT_QUOTES, 'UTF-8'); ?> (Age <?php echo intval($child['age']); ?>)</small>
                    </a>
                  <?php endforeach; ?>
                </div>
              </article>

              <?php if ($selectedChild): ?>
                <!-- Add Task Form -->
                <article class="card add-task-card" id="add-task">
                  <h3>Assign a Task to <?php echo htmlspecialchars($selectedChild['display_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <?php if (!empty($successMessage)): ?>
                    <div class="success-panel"><p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p></div>
                  <?php endif; ?>
                  <?php if (!empty($errors)): ?>
                    <div class="error-panel">
                      <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <form id="new-task-form" class="task-form" method="POST" action="parent-tasks.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="child_id" value="<?php echo $selectedChild['id']; ?>">
                    <div class="input-group">
                      <label for="task-date">Date</label>
                      <input id="task-date" name="task_date" type="date" required>
                    </div>
                    <div class="input-group">
                      <label for="task-title">Task Title</label>
                      <input id="task-title" name="task_title" type="text" placeholder="e.g. Complete math homework" required>
                    </div>
                    <div class="input-group">
                      <label for="task-category">Category</label>
                      <select id="task-category" name="task_category">
                        <option value="School Work">School Work</option>
                        <option value="Housework">Housework</option>
                        <option value="Actual Work">Actual Work</option>
                        <option value="Social">Social Activity</option>
                        <option value="Event">Event</option>
                        <option value="_custom_">Custom category...</option>
                      </select>
                    </div>
                    <div class="input-group" id="custom-category-group" style="display:none;">
                      <label for="custom-category-input">Custom Category</label>
                      <input id="custom-category-input" name="custom_category" type="text" placeholder="Type a category" />
                    </div>
                    <div class="input-group">
                      <label for="task-time">Time</label>
                      <input id="task-time" name="task_time" type="time">
                    </div>
                    <button class="button button-primary" type="submit">Assign Task</button>
                  </form>
                </article>

                <!-- Tasks List -->
                <article class="card task-card" id="assigned-tasks">
                  <h3>Tasks for <?php echo htmlspecialchars($selectedChild['display_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <?php if (empty($childTasks)): ?>
                    <p class="hint-text">No tasks assigned yet.</p>
                  <?php else: ?>
                    <div class="task-list">
                      <?php foreach ($childTasks as $task): ?>
                        <?php $completedClass = $task['status'] === 'complete' ? ' completed' : ''; ?>
                        <div class="task-item<?php echo $completedClass; ?>">
                          <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                          <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Tdate'] ? ' • ' . htmlspecialchars($task['Tdate'], ENT_QUOTES, 'UTF-8') : ''; ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                          <div class="task-actions">
                            <?php if ($task['status'] !== 'complete'): ?>
                              <form method="POST" class="task-action-form">
                                <input type="hidden" name="action" value="finish">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="child_id" value="<?php echo $selectedChild['id']; ?>">
                                <button type="submit" class="button button-secondary">Mark Done</button>
                              </form>
                            <?php endif; ?>
                            <form method="POST" class="task-action-form">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                              <input type="hidden" name="child_id" value="<?php echo $selectedChild['id']; ?>">
                              <button type="submit" class="button button-secondary">Delete</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
