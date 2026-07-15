<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$username = htmlspecialchars($_SESSION['user'] ?? 'Planner', ENT_QUOTES, 'UTF-8');
$dbToday = $pdo->query("SELECT CURDATE()")->fetchColumn();
$today = new DateTime($dbToday);
$monthStart = new DateTime($today->format('Y-m-01'));
$monthName = $monthStart->format('F');
$monthYear = $monthStart->format('Y');
$firstWeekday = (int)$monthStart->format('w');
$daysInMonth = (int)$monthStart->format('t');
$monthEnd = (clone $monthStart)->modify('last day of this month');
$taskCountsStmt = $pdo->prepare('SELECT Tdate, COUNT(*) AS total FROM tasks WHERE user_id = ? AND Tdate BETWEEN ? AND ? GROUP BY Tdate');
$taskCountsStmt->execute([$userId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
$taskCounts = [];
foreach ($taskCountsStmt->fetchAll() as $row) {
    $taskCounts[$row['Tdate']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Calendar</title>
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
            <h2>Calendar & Schedule</h2>
            <p>Review your week and set times for tasks, assignments, and events.</p>
          </div>
      <div class="calendar-card card">
        <div class="calendar-month-header">
          <h3><?php echo htmlspecialchars($monthName . ' ' . $monthYear, ENT_QUOTES, 'UTF-8'); ?></h3>
        </div>
        <div class="calendar-grid month-view">
          <div class="calendar-day header">Sun</div>
          <div class="calendar-day header">Mon</div>
          <div class="calendar-day header">Tue</div>
          <div class="calendar-day header">Wed</div>
          <div class="calendar-day header">Thu</div>
          <div class="calendar-day header">Fri</div>
          <div class="calendar-day header">Sat</div>
          <?php for ($blank = 0; $blank < $firstWeekday; $blank++): ?>
            <div class="calendar-day empty"></div>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
              $date = (clone $monthStart)->modify("+" . ($day - 1) . " days");
              $class = 'calendar-day';
              if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
                  $class .= ' today';
              }
            ?>
            <div class="<?php echo $class; ?>">
              <span class="date-number"><?php echo $day; ?></span>
              <?php $dateKey = $date->format('Y-m-d'); ?>
              <?php if (!empty($taskCounts[$dateKey])): ?>
                <span class="day-badge"><?php echo (int)$taskCounts[$dateKey]; ?> task<?php echo $taskCounts[$dateKey] > 1 ? 's' : ''; ?></span>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
        <p class="hint-text">Use the Schedule page for a weekly view and task details.</p>
      </div>
    </section>
  </main>
    </div>
  </div>
</body>
</html>