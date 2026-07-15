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
$isChildAccount = $accountType === 'Child';
$childAccounts = [];

if (!$isParentRole && !empty($userId)) {
    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE parent_id = ?');
    $childCountStmt->execute([$userId]);
    if ((int)$childCountStmt->fetchColumn() > 0) {
        $isParentRole = true;
    }
}
$errors = [];
$successMessage = '';

if ($isParentRole && $userId) {
    $childStmt = $pdo->prepare('SELECT username, display_name, age FROM users WHERE parent_id = ? ORDER BY id');
    $childStmt->execute([$userId]);
    $childAccounts = $childStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Child accounts cannot add tasks
    if ($isChildAccount && $_POST['action'] === 'add') {
        $errors[] = 'Child accounts cannot create tasks. Your parent can assign tasks to you.';
    } else {
        $action = $_POST['action'] ?? 'add';
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : null;

    if ($action === 'delete' && $taskId) {
        $delete = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $delete->execute([$taskId]);
        header('Location: tasks.php?deleted=1');
        exit;
    }

    if ($action === 'finish' && $taskId) {
        $complete = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?');
        $complete->execute(['complete', $taskId]);
        header('Location: tasks.php?finished=1');
        exit;
    }

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
            $insert->execute([$userId, $taskTitle, $taskCategory, $taskDate, $taskTime ?: null]);
            header('Location: tasks.php?added=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    }
}

$successMessage = '';
if (isset($_GET['added'])) {
    $successMessage = 'Task saved successfully.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Task deleted successfully.';
} elseif (isset($_GET['finished'])) {
    $successMessage = 'Task marked as finished.';
}

// DEBUG: Check user_id
if (!$userId) {
    $errors[] = 'ERROR: No user_id found in session. User ID: ' . var_export($userId, true);
}

$dbToday = $pdo->query("SELECT CURDATE()")->fetchColumn();
$today = new DateTime($dbToday);

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = (clone $today)->modify("+$i day");
}
$weekStart = clone $today;
$weekEnd = (clone $today)->modify('+6 days');
$weekTasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) BETWEEN ? AND ? ORDER BY Tdate ASC, Ttime ASC');
$weekTasksStmt->execute([$userId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$weekTasks = $weekTasksStmt->fetchAll();
$tasksByDate = [];
foreach ($weekTasks as $task) {
    $taskDateKey = date('Y-m-d', strtotime($task['Tdate']));
    $tasksByDate[$taskDateKey][] = $task;
}

$todayTasks = [];
$todayFallback = null; // if we show a different date's tasks, store it here
$todayTasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) = ? ORDER BY Ttime ASC');
$todayTasksStmt->execute([$userId, $today->format('Y-m-d')]);
$todayTasks = $todayTasksStmt->fetchAll();
if (empty($todayTasks)) {
  // first try weekly grouped results
  $todayTasks = $tasksByDate[$today->format('Y-m-d')] ?? [];
}
if (empty($todayTasks)) {
  // find the next date that has tasks (within future)
  $nextDateStmt = $pdo->prepare('SELECT DATE(Tdate) as d FROM tasks WHERE user_id = ? AND DATE(Tdate) > ? GROUP BY DATE(Tdate) ORDER BY DATE(Tdate) ASC LIMIT 1');
  $nextDateStmt->execute([$userId, $today->format('Y-m-d')]);
  $nextRow = $nextDateStmt->fetch(PDO::FETCH_ASSOC);
  if ($nextRow && !empty($nextRow['d'])) {
    $nextDate = $nextRow['d'];
    $todayFallback = $nextDate;
    // try to get from grouped week data first
    $todayTasks = $tasksByDate[$nextDate] ?? [];
    if (empty($todayTasks)) {
      $nextTasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) = ? ORDER BY Ttime ASC');
      $nextTasksStmt->execute([$userId, $nextDate]);
      $todayTasks = $nextTasksStmt->fetchAll();
    }
  }
}

$taskStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status, created_at FROM tasks WHERE user_id = ? ORDER BY Tdate ASC, Ttime ASC, created_at DESC');
$taskStmt->execute([$userId]);
$tasks = $taskStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Task Planner</title>
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
            <h2>Task Planner</h2>
            <p>Schedule tasks for today and manage saved tasks in one place.</p>
            <?php if ($isParentRole): ?>
              <div class="button-row" style="margin-top: 16px; gap: 10px;">
                <a class="button button-secondary" href="parent-tasks.php">Manage Child Tasks</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="panel-grid">
            <?php if (!$isChildAccount): ?>
            <article class="card add-task-card" id="add-tasks">
              <h3>Schedule a Task</h3>
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
              <form id="new-task-form" class="task-form" method="POST" action="tasks.php">
                <input type="hidden" name="action" value="add">
                <div class="input-group">
                  <label for="task-date">Date</label>
                  <input id="task-date" name="task_date" type="date" required>
                </div>
                <div class="input-group">
                  <label for="task-title">Task Title</label>
                  <input id="task-title" name="task_title" type="text" placeholder="e.g. Finish science project" required>
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
                <button class="button button-primary" type="submit">Add Task</button>
              </form>
            </article>
            <?php else: ?>
            <article class="card add-task-card" id="add-tasks">
              <h3>Your Assigned Tasks</h3>
              <p class="hint-text">Your parent manages your task assignments. Check your tasks below and mark them complete when finished.</p>
            </article>
            <?php endif; ?>
            <article class="card window-panel today-task-window" id="today-task-window">
              <div class="today-window-header">
                <h3>Today's Task Window</h3>
                <p>Focus on the tasks planned for today and manage them from this panel.</p>
              </div>
              <div class="task-list">
                <?php if (!empty($todayFallback)): ?>
                  <p class="hint-text">No tasks scheduled for today. Showing tasks for <?php echo htmlspecialchars($todayFallback, ENT_QUOTES, 'UTF-8'); ?> (next scheduled day).</p>
                <?php endif; ?>
                <?php if (empty($todayTasks)): ?>
                  <p class="hint-text">No tasks scheduled for today.</p>
                <?php else: ?>
                  <?php foreach ($todayTasks as $task): ?>
                    <?php $completedClass = $task['status'] === 'complete' ? ' completed' : ''; ?>
                    <div class="task-item<?php echo $completedClass; ?>">
                      <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                      <div class="task-actions">
                        <?php if ($task['status'] !== 'complete'): ?>
                          <form method="POST" class="task-action-form">
                            <input type="hidden" name="action" value="finish">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <button type="submit" class="button button-secondary">Finish</button>
                          </form>
                        <?php endif; ?>
                        <form method="POST" class="task-action-form">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                          <button type="submit" class="button button-secondary">Delete</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </article>
            <article class="card task-card" id="saved-tasks">
              <h3>Saved Tasks</h3>
              <?php if (empty($tasks)): ?>
                <p class="hint-text">No tasks have been saved yet.</p>
              <?php else: ?>
                <div class="task-list">
                  <?php foreach ($tasks as $task): ?>
                    <?php $completedClass = $task['status'] === 'complete' ? ' completed' : ''; ?>
                    <div class="task-item<?php echo $completedClass; ?>">
                      <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Tdate'] ? ' • ' . htmlspecialchars($task['Tdate'], ENT_QUOTES, 'UTF-8') : ''; ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                      <div class="task-actions">
                        <?php if ($task['status'] !== 'complete'): ?>
                          <form method="POST" class="task-action-form">
                            <input type="hidden" name="action" value="finish">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <button type="submit" class="button button-secondary">Finish</button>
                          </form>
                        <?php endif; ?>
                        <form method="POST" class="task-action-form">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                          <button type="submit" class="button button-secondary">Delete</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
          </div>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
