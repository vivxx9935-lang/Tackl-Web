<?php
$user = htmlspecialchars($_SESSION['user'] ?? 'Planner', ENT_QUOTES, 'UTF-8');
$accountType = htmlspecialchars($_SESSION['account_type'] ?? 'Guest', ENT_QUOTES, 'UTF-8');
?>
<header class="top-bar component">
  <div class="brand">
    <span class="brand-mark">T</span>
    <div>
      <h1>tackl</h1>
      <p>Planner space for children, teens, adults, and families</p>
    </div>
  </div>
  <div class="actions">
    <span class="account-badge"><?php echo $user; ?> (<?php echo $accountType; ?>)</span>
    <a href="logout.php" class="button button-secondary">Log Out</a>
  </div>
</header>
