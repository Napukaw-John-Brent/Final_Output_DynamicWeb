<?php
$pageTitle = "Create Account";
require_once __DIR__ . '/../config/performance.php';
$perf = new PerformanceMonitor();
include "../config/db.php";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$registerError = '';
if (!empty($_POST['first_name']) && !empty($_POST['email']) && isset($_POST['password'])) {
    $first    = trim($_POST['first_name']);
    $last     = trim($_POST['last_name'] ?? '');
    $name     = trim("$first $last");
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['password_confirm'] ?? '';
    $mobile   = isset($_POST['mobile']) ? trim($_POST['mobile']) : null;
    $city     = isset($_POST['city'])   ? trim($_POST['city'])   : null;
    $country  = isset($_POST['country'])? trim($_POST['country']): null;

    $dob = null;
    if (!empty($_POST['dob_year']) && !empty($_POST['dob_month']) && !empty($_POST['dob_day'])) {
        $dob = $_POST['dob_year'] . '-'
             . str_pad($_POST['dob_month'], 2, '0', STR_PAD_LEFT) . '-'
             . str_pad($_POST['dob_day'],   2, '0', STR_PAD_LEFT);
    }

    if ($password !== $confirm) {
        $registerError = 'Passwords do not match.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, date_of_birth, city, country, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $email, $mobile, $dob, $city, $country, $password_hash);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: login.php");
            exit;
        }
        $registerError = $conn->errno === 1062 ? "Email already registered." : "Registration failed.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    <link rel="stylesheet" href="../css/responsive.css">
    <title><?= $pageTitle ?> – SmartBudget</title>
</head>
<body class="auth-page auth-create">
<div class="container">

    <h1 class="reg-title">Create Account</h1>

    <div class="reg-card">

        <?php if ($registerError !== ''): ?>
            <div class="reg-error">&#9888; <?= htmlspecialchars($registerError) ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm">

            <!-- First + Last Name -->
            <div class="reg-field">
                <label class="reg-label">Name</label>
                <div class="reg-row-2 reg-row-no-mb">
                    <input class="reg-input" type="text" name="first_name" id="firstName"
                        placeholder="First Name"
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                        required>
                    <input class="reg-input" type="text" name="last_name" id="lastName"
                        placeholder="Last Name"
                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <!-- Email -->
            <div class="reg-field">
                <label class="reg-label">Email</label>
                <input class="reg-input" type="email" name="email"
                    placeholder="Email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required>
            </div>

            <!-- Mobile -->
            <div class="reg-field">
                <label class="reg-label">Mobile Number</label>
                <input class="reg-input" type="text" name="mobile"
                    placeholder="Mobile Number"
                    value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
            </div>

            <!-- City + Country -->
            <div class="reg-field">
                <label class="reg-label">Location</label>
                <div class="reg-row-2 reg-row-no-mb">
                    <input class="reg-input" type="text" name="city"
                        placeholder="City"
                        value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    <input class="reg-input" type="text" name="country"
                        placeholder="Country">
                </div>
            </div>

            <!-- Birthday -->
            <div class="reg-field">
                <label class="reg-label">Date of Birth</label>
                <div class="reg-row-dob">
                    <div class="reg-select-wrap">
                        <select name="dob_month" class="reg-select">
                            <option value="" disabled <?= empty($_POST['dob_month']) ? 'selected' : '' ?>>Month</option>
                            <?php
                            $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                            foreach ($months as $i => $m):
                                $val = $i + 1;
                                $sel = (isset($_POST['dob_month']) && $_POST['dob_month'] == $val) ? 'selected' : '';
                            ?>
                            <option value="<?= $val ?>" <?= $sel ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reg-select-wrap">
                        <select name="dob_day" class="reg-select">
                            <option value="" disabled <?= empty($_POST['dob_day']) ? 'selected' : '' ?>>Day</option>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $sel = (isset($_POST['dob_day']) && $_POST['dob_day'] == $d) ? 'selected' : '';
                            ?>
                            <option value="<?= $d ?>" <?= $sel ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="reg-select-wrap">
                        <select name="dob_year" class="reg-select">
                            <option value="" disabled <?= empty($_POST['dob_year']) ? 'selected' : '' ?>>Year</option>
                            <?php for ($y = date('Y') - 13; $y >= 1920; $y--):
                                $sel = (isset($_POST['dob_year']) && $_POST['dob_year'] == $y) ? 'selected' : '';
                            ?>
                            <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="reg-field">
                <label class="reg-label">Password</label>
                <div class="reg-pw-wrap">
                    <input class="reg-input" type="password" name="password"
                        id="reg-password" placeholder="........" required>
                    <button type="button" class="reg-pw-toggle"
                        data-target="reg-password" aria-label="Show password">
                        <img src="../images/eye.svg" alt="">
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="reg-field">
                <label class="reg-label">Confirm Password</label>
                <div class="reg-pw-wrap">
                    <input class="reg-input" type="password" name="password_confirm"
                        id="reg-password-confirm" placeholder="........" required>
                    <button type="button" class="reg-pw-toggle"
                        data-target="reg-password-confirm" aria-label="Show confirm password">
                        <img src="../images/eye.svg" alt="">
                    </button>
                </div>
            </div>

            <p class="reg-terms">
                By continuing, you agree to <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>.
            </p>

            <button type="submit" class="reg-submit">Sign Up</button>
            <p class="reg-footer">Already have an account? <a href="login.php">Log In</a></p>
        </form>
    </div>

</div>
<script>
document.querySelectorAll('.reg-pw-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var target = document.getElementById(this.dataset.target);
        var img    = this.querySelector('img');
        if (!target) return;
        if (target.type === 'password') {
            target.type = 'text';
            img.src = '../images/eye-off.svg';
        } else {
            target.type = 'password';
            img.src = '../images/eye.svg';
        }
    });
});
</script>
</body>
</html>