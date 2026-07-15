<?php
session_start();
require_once __DIR__ . '/db.php';
$errors = [];
$showLogoutPopup = isset($_GET['logged_out']);
$showRegisteredPopup = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, display_name, account_type, adult_role, parent_id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = $user['display_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['account_type'] = trim($user['account_type'] ?? '');
            $_SESSION['adult_role'] = trim($user['adult_role'] ?? '');
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['parent_id'] = $user['parent_id'];
            header('Location: home.php');
            exit;
        }

        $errors[] = 'Incorrect username or password. If you are new, please register first.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Log In</title>
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
  <div class="app-window">
    <section class="section window-panel login-shell">
      <div class="panel-header">
        <h2>Welcome to Tackl</h2>
        <p>Log in to organize your day, build goals, and manage parent/child assignments.</p>
      </div>
      <div class="panel-body">
        <?php if ($showLogoutPopup): ?>
          <div class="popup visible" data-popup-message="You have successfully logged out. Welcome back anytime!">You are logged out.</div>
        <?php endif; ?>
        <?php if ($showRegisteredPopup): ?>
          <div class="popup" data-popup-message="Your account was created successfully. Please log in to continue.">Your account was created successfully. Please log in to continue.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="error-panel">
            <?php foreach ($errors as $error): ?>
              <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form class="card login-card" method="POST" action="login.php">
          <div class="input-group">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="username" required>
          </div>
          <div class="input-group password-group">
            <label for="password">Password</label>
            <div class="password-field">
              <input id="password" name="password" type="password" placeholder="password" required>
              <button type="button" class="button button-secondary show-password-button" id="toggle-password" aria-label="Show password">Show</button>
            </div>
          </div>
          <div class="button-row">
            <button class="button button-primary" type="submit">Log In</button>
            <a class="button button-secondary" href="register.php">Create Account</a>
          </div>
        </form>
      </div>
    </section>
  </div>
</body>
</html>