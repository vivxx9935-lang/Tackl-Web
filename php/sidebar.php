<?php
require_once __DIR__ . '/db.php';
$current = basename($_SERVER['SCRIPT_NAME']);
$userName = htmlspecialchars($_SESSION['user'] ?? 'Planner', ENT_QUOTES, 'UTF-8');
$accountType = trim($_SESSION['account_type'] ?? 'Guest');
$adultRole = trim($_SESSION['adult_role'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

// If session role data is missing, refresh it from the database.
if (!empty($userId) && ($accountType === '' || $adultRole === '' || $accountType === 'Guest')) {
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

$userType = htmlspecialchars($accountType, ENT_QUOTES, 'UTF-8');
if ($userType === 'Adult' && $adultRole !== '') {
    $userType .= ' (' . htmlspecialchars($adultRole, ENT_QUOTES, 'UTF-8') . ')';
}
$hasParentRole = stripos($adultRole, 'Parent') !== false || $accountType === 'Parent';
if (!$hasParentRole && !empty($userId)) {
    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE parent_id = ?');
    $childCountStmt->execute([$userId]);
    $hasParentRole = (int)$childCountStmt->fetchColumn() > 0;
}
$isChild = $accountType === 'Child';
function sidebarActive($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<aside class="sidebar component">
  <div class="sidebar-brand">
    <span class="brand-mark">T</span>
    <div>
      <h2>Navigation</h2>
      <p>Quick access to your planner features.</p>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a href="home.php" class="sidebar-link <?php echo sidebarActive('home.php', $current); ?>">
      <span class="sidebar-icon">🏠</span>
      <span>Home</span>
    </a>
    <a href="tasks.php" class="sidebar-link <?php echo sidebarActive('tasks.php', $current); ?>">
      <span class="sidebar-icon">📝</span>
      <span><?php echo $isChild ? 'My Tasks' : 'Tasks'; ?></span>
    </a>
    <a href="schedule.php" class="sidebar-link <?php echo sidebarActive('schedule.php', $current); ?>">
      <span class="sidebar-icon">📆</span>
      <span>Schedule</span>
    </a>
    <a href="calendar.php" class="sidebar-link <?php echo sidebarActive('calendar.php', $current); ?>">
      <span class="sidebar-icon">🗓</span>
      <span>Calendar</span>
    </a>
    <?php if ($hasParentRole): ?>
      <a href="parent-tasks.php" class="sidebar-link <?php echo sidebarActive('parent-tasks.php', $current); ?>">
        <span class="sidebar-icon">👪</span>
        <span>Manage Child Tasks</span>
      </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <span class="account-badge"><?php echo $userName; ?> (<?php echo $userType; ?>)</span>
    <a href="logout.php" class="button button-secondary sidebar-logout">Log Out</a>
  </div>
</aside>
