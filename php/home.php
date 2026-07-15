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
$accountTypeText = htmlspecialchars($accountType, ENT_QUOTES, 'UTF-8');
$accountAge = null;
$adultRole = trim($_SESSION['adult_role'] ?? '');
$parentName = null;
$childAccounts = [];

if ($userId && ($accountType === '' || $adultRole === '')) {
    $stmt = $pdo->prepare('SELECT account_type, adult_role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $accountType = trim($profile['account_type'] ?? $accountType);
        $adultRole = trim($profile['adult_role'] ?? $adultRole);
        $accountTypeText = htmlspecialchars($accountType, ENT_QUOTES, 'UTF-8');
        $_SESSION['account_type'] = $accountType;
        $_SESSION['adult_role'] = $adultRole;
    }
}

if ($userId) {
    $stmt = $pdo->prepare('SELECT account_type, age, adult_role, parent_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $accountType = $profile['account_type'];
        $accountTypeText = htmlspecialchars($profile['account_type'], ENT_QUOTES, 'UTF-8');
        $accountAge = $profile['age'];
        $adultRole = $profile['adult_role'];

        $adultRole = $profile['adult_role'];
        $isParentRole = ($accountType === 'Parent') || ($accountType === 'Adult' && stripos($adultRole, 'Parent') !== false);

        if ($isParentRole) {
            $childStmt = $pdo->prepare('SELECT username, display_name, age FROM users WHERE parent_id = ? ORDER BY id');
            $childStmt->execute([$userId]);
            $childAccounts = $childStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!$isParentRole) {
            $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE parent_id = ?');
            $childCountStmt->execute([$userId]);
            $hasLinkedChildren = (int)$childCountStmt->fetchColumn() > 0;
            if ($hasLinkedChildren) {
                $isParentRole = true;
            }
        }

        if ($accountType === 'Child' || $accountType === 'Teen') {
            if (!empty($profile['parent_id'])) {
                $parentStmt = $pdo->prepare('SELECT display_name FROM users WHERE id = ?');
                $parentStmt->execute([$profile['parent_id']]);
                $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
                if ($parent) {
                    $parentName = htmlspecialchars($parent['display_name'], ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }
}

$isParentRole = ($accountType === 'Parent') || ($accountType === 'Adult' && stripos($adultRole, 'Parent') !== false);

$adultRoleText = '';
if ($accountType === 'Adult' && !empty($adultRole)) {
    $roles = array_filter(array_map('trim', explode(',', $adultRole)));
    if (!empty($roles)) {
        $adultRoleText = ' Roles: ' . htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8') . '.';
    }
}

$pageIntro = 'As a ' . $accountTypeText . ' account, you can manage goals, schedule time, and add new categories.';
if ($accountType === 'Child') {
    $pageIntro = 'Welcome! This child-friendly space makes planning fun with bright steps, easy goals, and friendly reminders.';
} elseif ($accountType === 'Teen') {
    $pageIntro = 'This teen space is made for focus, social planning, and balancing school with hobbies.';
} elseif ($accountType === 'Adult' && stripos($adultRole, 'Parent') !== false) {
    $pageIntro = 'Parent dashboard: manage your child account, assign tasks, and check schedules from one place.' . $adultRoleText;
} elseif ($accountType === 'Adult') {
    $pageIntro = 'This adult planner supports work, study, and daily routines with a clean, calm layout.' . $adultRoleText;
} elseif ($accountType === 'Parent') {
    $pageIntro = 'Parent dashboard: manage your child account, assign tasks, and check schedules from one place.';
}

$dbToday = $pdo->query("SELECT CURDATE()")->fetchColumn();
$today = new DateTime($dbToday);
$todayTasks = [];
$todayTasksStmt = $pdo->prepare('SELECT task_name, category, Ttime FROM tasks WHERE user_id = ? AND DATE(Tdate) = ? ORDER BY Ttime ASC');
$todayTasksStmt->execute([$userId, $today->format('Y-m-d')]);
$todayTasks = $todayTasksStmt->fetchAll();
$todayTaskCount = count($todayTasks);

$motivationalQuotes = [
    "The only way to do great work is to love what you do. — Steve Jobs",
    "Success is not final, failure is not fatal. — Winston Churchill",
    "You are capable of amazing things. Believe in yourself.",
    "Every accomplishment starts with the decision to try.",
    "Your potential is endless. Make today count.",
    "Progress over perfection. Keep moving forward.",
    "You've got this! One task at a time.",
    "Hard work creates success. Stay focused.",
    "Believe you can and you're halfway there. — Theodore Roosevelt",
    "The best time to start was yesterday. The second best time is now.",
    "Challenges help you grow. Embrace them.",
    "Your effort will always be worth it.",
    "Focus on what you can control today.",
    "Small steps lead to big changes.",
    "You are stronger than you think."
];
$dailyQuote = $motivationalQuotes[array_rand($motivationalQuotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Home</title>
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/windows.css">
  <link rel="stylesheet" href="../css/buttons.css">
  <link rel="stylesheet" href="../css/tabs.css">
  <link rel="stylesheet" href="../css/forms.css">
  <script src="../js/main.js" defer></script>
</head>
<body class="account-<?php echo strtolower($accountType); ?>">
  <div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-column">
      <?php include __DIR__ . '/header.php'; ?>
      <main class="app-window page-shell account-<?php echo strtolower($accountType); ?>">
        <section class="section window-panel">
          <div class="panel-header">
            <h2>Good morning, <?php echo $userName; ?>.</h2>
            <p><?php echo $pageIntro; ?></p>
            <?php if ($parentName): ?>
              <p class="hint-text">Your parent administrator is <?php echo $parentName; ?>.</p>
            <?php endif; ?>
            <?php if ($isParentRole): ?>
              <div class="button-row" style="margin-top: 16px; gap: 10px;">
                <a class="button button-secondary" href="parent-tasks.php">Manage Child Tasks</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="panel-grid">
            <article class="card task-card" id="today-tasks">
              <h3>Today's Tasks</h3>
              <p>Your tasks for today at a glance.</p>
              <?php if (empty($todayTasks)): ?>
                <p class="hint-text">No tasks scheduled for today. Go to Task Planner to add some.</p>
              <?php else: ?>
                <div class="task-list">
                  <?php foreach ($todayTasks as $task): ?>
                    <div class="task-item">
                      <strong><?php echo htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <small><?php echo htmlspecialchars($task['category'], ENT_QUOTES, 'UTF-8'); ?><?php echo $task['Ttime'] ? ' • ' . htmlspecialchars($task['Ttime'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
            <article class="card workload-card" id="workload-gauge">
              <h3>Workload Gauge</h3>
              <p>How busy is today?</p>
              <div class="gauge">
                <div class="gauge-fill" id="workload-fill" style="width: <?php echo min(100, $todayTaskCount * 20); ?>%"></div>
              </div>
              <p class="workload-text" id="workload-text">
                <?php 
                  if ($todayTaskCount === 0) {
                    echo 'No tasks yet.';
                  } elseif ($todayTaskCount <= 2) {
                    echo $todayTaskCount . ' task' . ($todayTaskCount === 1 ? '' : 's') . ' — easy day ahead.';
                  } elseif ($todayTaskCount <= 5) {
                    echo $todayTaskCount . ' tasks — balanced workload.';
                  } else {
                    echo $todayTaskCount . ' tasks — busy schedule.';
                  }
                ?>
              </p>
            </article>
            <article class="card quote-card" id="daily-quote">
              <h3>Daily Motivation</h3>
              <div class="quote-box">
                <p class="quote-text">✨ <?php echo htmlspecialchars($dailyQuote, ENT_QUOTES, 'UTF-8'); ?> ✨</p>
              </div>
              <p class="hint-text">Let this inspire your day ahead.</p>
            </article>
          </div>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
