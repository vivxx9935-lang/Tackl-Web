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
$childAccounts = [];

if (!$isParentRole && !empty($userId)) {
    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE parent_id = ?');
    $childCountStmt->execute([$userId]);
    if ((int)$childCountStmt->fetchColumn() > 0) {
        $isParentRole = true;
    }
}

if ($isParentRole && $userId) {
    $childStmt = $pdo->prepare('SELECT username, display_name, age FROM users WHERE parent_id = ? ORDER BY id');
    $childStmt->execute([$userId]);
    $childAccounts = $childStmt->fetchAll(PDO::FETCH_ASSOC);
}

$dbToday = $pdo->query("SELECT CURDATE()")->fetchColumn();
$today = new DateTime($dbToday);
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = (clone $today)->modify("+$i day");
}

$weekStart = clone $today;
$weekEnd = (clone $today)->modify('+6 days');
$tasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) BETWEEN ? AND ? ORDER BY Tdate ASC, Ttime ASC');
$tasksStmt->execute([$userId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$weekTasks = $tasksStmt->fetchAll();
$tasksByDate = [];
foreach ($weekTasks as $task) {
    $taskDateKey = date('Y-m-d', strtotime($task['Tdate']));
    $tasksByDate[$taskDateKey][] = $task;
}
$todayTasks = [];
$todayFallback = null;
$todayTasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) = ? ORDER BY Ttime ASC');
$todayTasksStmt->execute([$userId, $today->format('Y-m-d')]);
$todayTasks = $todayTasksStmt->fetchAll();
if (empty($todayTasks)) {
  $todayTasks = $tasksByDate[$today->format('Y-m-d')] ?? [];
}
if (empty($todayTasks)) {
    $nextDateStmt = $pdo->prepare('SELECT DATE(Tdate) as d FROM tasks WHERE user_id = ? AND DATE(Tdate) > ? GROUP BY DATE(Tdate) ORDER BY DATE(Tdate) ASC LIMIT 1');
    $nextDateStmt->execute([$userId, $today->format('Y-m-d')]);
  $nextRow = $nextDateStmt->fetch(PDO::FETCH_ASSOC);
  if ($nextRow && !empty($nextRow['d'])) {
    $todayFallback = $nextRow['d'];
    $todayTasks = $tasksByDate[$todayFallback] ?? [];
    if (empty($todayTasks)) {
        $nextTasksStmt = $pdo->prepare('SELECT id, task_name, category, Tdate, Ttime, status FROM tasks WHERE user_id = ? AND DATE(Tdate) = ? ORDER BY Ttime ASC');
        $nextTasksStmt->execute([$userId, $todayFallback]);
      $todayTasks = $nextTasksStmt->fetchAll();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Schedule</title>
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
            <h2>Schedule</h2>
            <p>View today and this week’s schedule with tasks grouped by day.</p>
          </div>
          <div class="panel-grid schedule-panel-grid">
            <article class="card schedule-card wide-schedule-card" id="week-calendar">
              <h3>Upcoming 7-Day Calendar</h3>
              <p>Review the next seven days in a horizontal calendar view.</p>
              <div class="wide-week-schedule">
                <?php foreach ($days as $day): ?>
                  <?php $dayKey = $day->format('Y-m-d'); ?>
                  <div class="day-column">
                    <strong><?php echo $day->format('l'); ?></strong>
                    <small><?php echo $day->format('M j'); ?></small>
                    <?php if (empty($tasksByDate[$dayKey])): ?>
                      <span class="hint-text">No tasks</span>
                    <?php else: ?>
                      <div class="day-tasks">
                        <?php foreach ($tasksByDate[$dayKey] as $task): ?>
                          <div class="day-task<?php echo $task['status'] === 'complete' ? ' completed' : ''; ?>">
                            <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="card schedule-card" id="today-schedule">
              <h3>Today's Schedule</h3>
              <div class="task-list">
                <?php if (!empty($todayFallback)): ?>
                  <p class="hint-text">No tasks scheduled for today. Showing tasks for <?php echo htmlspecialchars($todayFallback, ENT_QUOTES, 'UTF-8'); ?> (next scheduled day).</p>
                <?php endif; ?>
                <?php if (empty($todayTasks)): ?>
                  <p class="hint-text">No tasks scheduled for today.</p>
                <?php else: ?>
                  <?php foreach ($todayTasks as $task): ?>
                    <div class="task-item<?php echo $task['status'] === 'complete' ? ' completed' : ''; ?>">
                      <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </article>
          </div>
          <?php if ($isParentRole): ?>
            <article class="card family-card" id="family-accounts">
              <h3>Child Accounts</h3>
              <?php if (!empty($childAccounts)): ?>
                <ul>
                  <?php foreach ($childAccounts as $child): ?>
                    <li><?php echo htmlspecialchars($child['display_name'], ENT_QUOTES, 'UTF-8'); ?> - Age <?php echo htmlspecialchars($child['age'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($child['username'], ENT_QUOTES, 'UTF-8'); ?>)</li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="hint-text">No linked child accounts yet. Use the registration page to add one.</p>
              <?php endif; ?>
            </article>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
