<?php
include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

if (!empty($_POST['pin']) && !empty($_POST['pin_confirm'])) {
    $pin = preg_replace('/\D/', '', $_POST['pin']);
    $pin_confirm = preg_replace('/\D/', '', $_POST['pin_confirm']);
    if (strlen($pin) !== 6 || strlen($pin_confirm) !== 6) {
        $error = 'Pin must be 6 digits.';
    } elseif ($pin !== $pin_confirm) {
        $error = 'Pins do not match.';
    } else {
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET security_pin = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Security pin set. You will be asked for it on next login.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set Security Pin – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page auth-pin">
  <div class="container">
    <div class="card auth-card auth-card-teal">
      <h2 class="auth-card-heading">Security Pin</h2>
      <p class="auth-card-sub">Set a 6-digit pin for extra security</p>
      <?php if ($message): ?><p class="success-msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <form method="POST" class="auth-form pin-form" id="pin-form">
        <label class="input-label">Enter new pin</label>
        <div class="pin-input-wrap">
          <input type="hidden" name="pin" id="pin-value">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" class="pin-dot" data-index="<?= $i ?>" autocomplete="off" aria-label="Digit <?= $i + 1 ?>">
          <?php endfor; ?>
        </div>
        <label class="input-label">Confirm pin</label>
        <div class="pin-input-wrap">
          <input type="hidden" name="pin_confirm" id="pin-confirm-value">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" class="pin-dot pin-dot-confirm" data-index="<?= $i ?>" autocomplete="off" aria-label="Confirm digit <?= $i + 1 ?>">
          <?php endfor; ?>
        </div>
        <button type="submit" class="btn-primary">Save Pin</button>
      </form>
      <p class="auth-switch"><a href="../dashboard/index.php">Back to Dashboard</a></p>
    </div>
  </div>
  <script>
  (function() {
    var form = document.getElementById('pin-form');
    var hidden = document.getElementById('pin-value');
    var hiddenConfirm = document.getElementById('pin-confirm-value');
    var dots = document.querySelectorAll('.pin-dot:not(.pin-dot-confirm)');
    var dotsConfirm = document.querySelectorAll('.pin-dot-confirm');

    function collect(el, arr) {
      var s = '';
      arr.forEach(function(d) { s += d.value || ''; });
      if (el) el.value = s;
      return s;
    }

    function wireDots(inputs, hiddenInput) {
      inputs.forEach(function(input, i) {
        input.addEventListener('input', function() {
          var v = this.value.replace(/\D/g, '');
          this.value = v.slice(0, 1);
          if (v && i < inputs.length - 1) inputs[i + 1].focus();
          collect(hiddenInput, inputs);
        });
        input.addEventListener('keydown', function(e) {
          if (e.key === 'Backspace' && !this.value && i > 0) inputs[i - 1].focus();
        });
      });
    }

    wireDots(Array.from(dots), hidden);
    wireDots(Array.from(dotsConfirm), hiddenConfirm);

    if (form) {
      form.addEventListener('submit', function() {
        collect(hidden, Array.from(dots));
        collect(hiddenConfirm, Array.from(dotsConfirm));
      });
    }
  })();
  </script>
</body>
</html>
