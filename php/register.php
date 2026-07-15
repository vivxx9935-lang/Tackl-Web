<?php
session_start();
require_once __DIR__ . '/db.php';
$errors = [];
$successMessage = '';
$accountTypes = [
    'Child' => 'Child (0 - 12 years)',
    'Teen' => 'Teen (13 - 18 years)',
    'Adult' => 'Adult (19+ years)',
];
$adultRoles = ['Student', 'Worker', 'Parent'];
$selectedType = 'Child';
$selectedRoles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $accountType = $_POST['account_type'] ?? 'Child';
    $age = trim($_POST['age'] ?? '');
    $selectedType = $accountType;

    $selectedRoles = array_values(array_filter(array_map('trim', (array)($_POST['roles'] ?? []))));
    $parentRoleSelected = in_array('Parent', $selectedRoles, true);

    $childUsername = strtolower(trim($_POST['child_username'] ?? ''));
    $childDisplayName = trim($_POST['child_display_name'] ?? '');
    $childPassword = $_POST['child_password'] ?? '';
    $childConfirmPassword = $_POST['child_confirm_password'] ?? '';
    $childAge = trim($_POST['child_age'] ?? '');
    $childCreateRequested = $parentRoleSelected && ($childUsername !== '' || $childDisplayName !== '' || $childPassword !== '' || $childConfirmPassword !== '' || $childAge !== '');

    if ($username === '') {
        $errors[] = 'Please choose a username.';
    }
    if ($displayName === '') {
        $errors[] = 'Please provide a display name.';
    }
    if ($password === '') {
        $errors[] = 'Please choose a password.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($accountType === 'Child') {
        if ($age === '' || !ctype_digit($age) || (int)$age < 0 || (int)$age > 12) {
            $errors[] = 'Child accounts must use an age between 0 and 12 years.';
        }
    } elseif ($accountType === 'Teen') {
        if ($age === '' || !ctype_digit($age) || (int)$age < 13 || (int)$age > 18) {
            $errors[] = 'Teen accounts must use an age between 13 and 18 years.';
        }
    } elseif ($accountType === 'Adult') {
        if ($age === '' || !ctype_digit($age) || (int)$age < 19) {
            $errors[] = 'Adult accounts should use an age of 19 or older.';
        }
        if (empty($selectedRoles)) {
            $errors[] = 'Please choose at least one role for adult accounts.';
        }
        if (count($selectedRoles) > 3) {
            $errors[] = 'Please choose no more than three roles.';
        }
        foreach ($selectedRoles as $role) {
            if (!in_array($role, $adultRoles, true)) {
                $errors[] = 'Invalid role selection.';
                break;
            }
        }
    }

    if ($childCreateRequested) {
        if ($childUsername === '') {
            $errors[] = 'When creating a child account, please choose a child username.';
        }
        if ($childDisplayName === '') {
            $errors[] = 'Please provide a display name for the child account.';
        }
        if ($childPassword === '') {
            $errors[] = 'Please choose a password for the child account.';
        }
        if ($childPassword !== $childConfirmPassword) {
            $errors[] = 'Child passwords do not match.';
        }
        if ($childAge === '' || !ctype_digit($childAge) || (int)$childAge < 0 || (int)$childAge > 12) {
            $errors[] = 'Linked child accounts must have an age between 0 and 12 years.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('This username is already taken. Please choose another one.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $ageValue = $age !== '' ? (int)$age : null;
            $adultRoleValue = !empty($selectedRoles) ? implode(',', $selectedRoles) : null;

            $insert = $pdo->prepare(
                'INSERT INTO users (username, password_hash, display_name, account_type, age, adult_role, parent_id)
                 VALUES (?, ?, ?, ?, ?, ?, NULL)'
            );
            $insert->execute([$username, $passwordHash, $displayName, $accountType, $ageValue, $adultRoleValue]);
            $parentId = (int)$pdo->lastInsertId();

            if ($childCreateRequested) {
                $stmt->execute([$childUsername]);
                if ($stmt->fetch()) {
                    throw new Exception('The child account username is already taken. Pick a different child username.');
                }

                $childPasswordHash = password_hash($childPassword, PASSWORD_DEFAULT);
                $insertChild = $pdo->prepare(
                    'INSERT INTO users (username, password_hash, display_name, account_type, age, adult_role, parent_id)
                     VALUES (?, ?, ?, ?, ?, NULL, ? )'
                );
                $insertChild->execute([$childUsername, $childPasswordHash, $childDisplayName, 'Child', (int)$childAge, $parentId]);
            }

            $pdo->commit();
            header('Location: login.php?registered=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tackl Planner - Register</title>
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
    <section class="section window-panel register-shell account-child">
      <div class="panel-header">
        <h2>Create a new Tackl account</h2>
        <p>Pick the account type that matches the user: Child, Teen, or Adult. Adult users may choose one to three roles from Student, Worker, and Parent.</p>
      </div>
      <div class="panel-body">
        <?php if (!empty($successMessage)): ?>
          <div class="success-panel">
            <p><?php echo safe($successMessage); ?></p>
          </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="error-panel">
            <?php foreach ($errors as $error): ?>
              <p><?php echo safe($error); ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form class="card register-card" method="POST" action="register.php">
          <div class="input-group">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="<?php echo safe($_POST['username'] ?? ''); ?>" placeholder="choose a username" required>
          </div>
          <div class="input-group">
            <label for="display_name">Display Name</label>
            <input id="display_name" name="display_name" type="text" value="<?php echo safe($_POST['display_name'] ?? ''); ?>" placeholder="How you'd like to be called" required>
          </div>
          <div class="input-group">
            <label for="account_type">Account Type</label>
            <select id="account_type" name="account_type" required>
              <?php foreach ($accountTypes as $type => $label): ?>
                <option value="<?php echo safe($type); ?>" <?php echo $selectedType === $type ? 'selected' : ''; ?>><?php echo safe($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="input-group conditional-field conditional-age">
            <label for="age">Age</label>
            <input id="age" name="age" type="number" min="0" max="120" value="<?php echo safe($_POST['age'] ?? ''); ?>" placeholder="Enter age" required>
            <p class="hint-text">Child accounts are 0–12, Teen accounts are 13–18, Adults and Parents are 19+.</p>
          </div>
          <div class="input-group conditional-field conditional-adult-role">
            <label>Adult Role</label>
            <div class="checkbox-row">
              <?php foreach ($adultRoles as $role): ?>
                <label class="checkbox-label">
                  <input type="checkbox" name="roles[]" value="<?php echo safe($role); ?>" <?php echo in_array($role, $selectedRoles, true) ? 'checked' : ''; ?>>
                  <?php echo safe($role); ?>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="hint-text">Pick one or more roles. Select Parent to manage a child account.</p>
          </div>
          <div class="input-group password-group">
            <label for="password">Password</label>
            <div class="password-field">
              <input id="password" name="password" type="password" placeholder="Create a strong password" required>
              <button type="button" class="button button-secondary show-password-button" aria-label="Show password">Show</button>
            </div>
          </div>
          <div class="input-group password-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="password-field">
              <input id="confirm_password" name="confirm_password" type="password" placeholder="Repeat password" required>
              <button type="button" class="button button-secondary show-password-button" aria-label="Show password">Show</button>
            </div>
          </div>

          <div class="parent-child-block conditional-field conditional-parent-fields">
            <div class="section-header">
              <h3>Link a child account</h3>
              <p>Parents may add a child account now so the child can sign in later with its own username.</p>
            </div>
            <div class="input-group">
              <label for="child_username">Child Username</label>
              <input id="child_username" name="child_username" type="text" value="<?php echo safe($_POST['child_username'] ?? ''); ?>" placeholder="child's username">
            </div>
            <div class="input-group">
              <label for="child_display_name">Child Display Name</label>
              <input id="child_display_name" name="child_display_name" type="text" value="<?php echo safe($_POST['child_display_name'] ?? ''); ?>" placeholder="How the child is called">
            </div>
            <div class="input-group">
              <label for="child_age">Child Age</label>
              <input id="child_age" name="child_age" type="number" min="0" max="12" value="<?php echo safe($_POST['child_age'] ?? ''); ?>" placeholder="0 - 12 years">
            </div>
            <div class="input-group password-group">
              <label for="child_password">Child Password</label>
              <div class="password-field">
                <input id="child_password" name="child_password" type="password" placeholder="Create a child password">
                <button type="button" class="button button-secondary show-password-button" aria-label="Show password">Show</button>
              </div>
            </div>
            <div class="input-group password-group">
              <label for="child_confirm_password">Confirm Child Password</label>
              <div class="password-field">
                <input id="child_confirm_password" name="child_confirm_password" type="password" placeholder="Repeat child password">
                <button type="button" class="button button-secondary show-password-button" aria-label="Show password">Show</button>
              </div>
            </div>
          </div>

          <div class="button-row">
            <button class="button button-primary" type="submit">Register</button>
            <a class="button button-secondary" href="login.php">Back to Login</a>
          </div>
        </form>
      </div>
    </section>
  </div>
  <script>
    const shell = document.querySelector('.register-shell');
    const accountTypeSelector = document.getElementById('account_type');
    const ageField = document.querySelector('.conditional-age');
    const adultRoleField = document.querySelector('.conditional-adult-role');
    const parentFields = document.querySelector('.conditional-parent-fields');

    function updateRegisterUI() {
      const value = accountTypeSelector.value;
      shell.className = shell.className.replace(/\baccount-\w+\b/, '');
      shell.classList.add('account-' + value.toLowerCase());

      ageField.style.display = 'grid';
      adultRoleField.style.display = 'none';
      parentFields.style.display = 'none';

      if (value === 'Child' || value === 'Teen') {
        ageField.style.display = 'grid';
      } else if (value === 'Adult') {
        ageField.style.display = 'grid';
        adultRoleField.style.display = 'grid';
      } else if (value === 'Parent') {
        ageField.style.display = 'grid';
        adultRoleField.style.display = 'grid';
        parentFields.style.display = 'grid';
      }
    }

    const roleCheckboxes = document.querySelectorAll('.checkbox-row input[type="checkbox"]');

    function updateParentFields() {
      const parentSelected = Array.from(roleCheckboxes).some(checkbox => checkbox.value === 'Parent' && checkbox.checked);
      parentFields.style.display = parentSelected ? 'grid' : 'none';
    }

    if (accountTypeSelector) {
      accountTypeSelector.addEventListener('change', () => {
        updateRegisterUI();
        updateParentFields();
      });
      roleCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateParentFields));
      updateRegisterUI();
      updateParentFields();
    }
  </script>
</body>
</html>
