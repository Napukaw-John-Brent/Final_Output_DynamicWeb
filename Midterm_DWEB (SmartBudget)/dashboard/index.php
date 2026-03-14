<?php
include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Handle Save QR (optional quick-save from dashboard)
if (isset($_POST['save_qr'])) {
    $qr_data = trim((string) ($_POST['qr_data'] ?? ''));
    if ($qr_data !== '') {
        $stmt = $conn->prepare("SELECT id FROM qr_codes WHERE user_id = ? AND qr_data = ? LIMIT 1");
        $stmt->bind_param("is", $user_id, $qr_data);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO qr_codes (user_id, qr_data) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $qr_data);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: index.php");
    exit;
}

// Handle Save Budget
if (isset($_POST['budget']) && $_POST['budget'] !== '') {
    $total_budget = (float) $_POST['budget'];
    $month = date('Y-m');
    $stmt = $conn->prepare("INSERT INTO budgets (user_id, month, total_budget) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $month, $total_budget);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

// Handle Add Expense
if (isset($_POST['amount']) && $_POST['amount'] !== '') {
    $amount = (float) $_POST['amount'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : 'Expense';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'Other';
    $date = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $user_id, $amount, $category, $description, $date);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT total_budget FROM budgets WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = $row ? (float) $row['total_budget'] : 20000;

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$spent = $row ? (float) $row['total'] : 11340;

$remaining = $total - $spent;
$percent_used = $total > 0 ? round(($spent / $total) * 100, 1) : 0;
$percent_left = $total > 0 ? round(($remaining / $total) * 100, 1) : 0;

// Load saved QRs for picker
$stmt = $conn->prepare("SELECT id, qr_data FROM qr_codes WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$savedQrResult = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app-page">
  <div class="container figma-container">
    <header class="page-header">
      <div class="brand">
        <img src="../images/smartbudget-logo.jpg" alt="SmartBudget" class="logo-img" style="height: 40px; width: auto;">
        <span class="brand-name">SmartBudget</span>
      </div>
      <nav>
        <a href="index.php" class="nav-link active">Dashboard</a>
        <a href="#" class="nav-link">Stats</a>
        <a href="../deals/qr-saver.php" class="nav-link">Saved QR Codes</a>
        <a href="#" class="nav-link">Profile</a>
        <a href="../auth/logout.php" class="nav-link">Logout</a>
      </nav>
    </header>

    <!-- Top Summary Cards -->
    <div class="dashboard-grid summary-row">
      <div class="summary-card figma-card">
        <div class="summary-title">Total Monthly Budget</div>
        <div class="summary-amount">₱<?= number_format($total) ?></div>
        <div class="summary-meta">Set on Feb 1, 2026</div>
      </div>

      <div class="summary-card figma-card">
        <div class="summary-title">Total Spent</div>
        <div class="summary-amount">₱<?= number_format($spent) ?></div>
        <div class="summary-meta"><?= $percent_used ?>% of budget used</div>
      </div>

      <div class="summary-card figma-card">
        <div class="summary-title">Remaining Budget</div>
        <div class="summary-amount">₱<?= number_format($remaining) ?></div>
        <div class="summary-meta"><?= $percent_left ?>% left for the month</div>
      </div>
    </div>

    <!-- Middle Row -->
    <div class="dashboard-grid">
      <!-- Category Breakdown Card -->
      <div class="card figma-teal category-card">
        <h3>Category Breakdown</h3>
        <div class="category-list">
          <div class="category-item">
            <span class="category-name">Food</span>
            <div class="progress-bar">
              <div class="progress-fill food" style="width: 82%"></div>
            </div>
            <span class="category-percentage">82%</span>
          </div>
          
          <div class="category-item">
            <span class="category-name">Transportation</span>
            <div class="progress-bar">
              <div class="progress-fill transport" style="width: 42%"></div>
            </div>
            <span class="category-percentage">42%</span>
          </div>
          
          <div class="category-item">
            <span class="category-name">Bills</span>
            <div class="progress-bar">
              <div class="progress-fill bills" style="width: 95%"></div>
            </div>
            <span class="category-percentage">95%</span>
          </div>
          
          <div class="category-item">
            <span class="category-name">Savings</span>
            <div class="progress-bar">
              <div class="progress-fill savings" style="width: 30%"></div>
            </div>
            <span class="category-percentage">30%</span>
          </div>
        </div>
      </div>

      <!-- Set Monthly Budget Card -->
      <div class="card figma-teal budget-card">
        <h3>Set Monthly Budget</h3>
        <form method="POST" class="budget-form">
          <div class="form-group" style="display: flex; gap: var(--space-2); align-items: center;">
            <label style="font-size: var(--text-xs); color: #fff;">Total Budget (₱)</label>
            <input name="budget" type="number" step="0.01" min="0" placeholder="20000" required style="flex: 1; background: #fff; border: none; border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3); color: #1A2828; font-size: var(--text-sm);">
            <button type="submit" class="btn-set" style="background: #1A2828; color: #fff; border-radius: var(--radius-sm); padding: var(--space-2) var(--space-4); font-size: var(--text-xs); border: none; cursor: pointer;">Set</button>
          </div>
        </form>
        
        <div class="auto-split">
          <h4 style="font-size: var(--text-xs); color: #fff; margin-bottom: var(--space-3);">Auto-split across categories</h4>
          <div class="split-grid">
            <div class="split-tag">
              <span class="tag-name">Food</span>
              <span class="tag-amount">₱8,000</span>
              <span class="tag-percent">40%</span>
            </div>
            <div class="split-tag">
              <span class="tag-name">Transport</span>
              <span class="tag-amount">₱5,000</span>
              <span class="tag-percent">25%</span>
            </div>
            <div class="split-tag">
              <span class="tag-name">Bills</span>
              <span class="tag-amount">₱4,000</span>
              <span class="tag-percent">20%</span>
            </div>
            <div class="split-tag">
              <span class="tag-name">Savings</span>
              <span class="tag-amount">₱3,000</span>
              <span class="tag-percent">15%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="dashboard-grid">
      <!-- Recent Expenses Card -->
      <div class="card figma-teal expenses-card">
        <h3>Recent Expenses</h3>
        <div class="expense-list">
          <div class="expense-item">
            <div class="expense-info">
              <span class="expense-description">Weekly Groceries</span>
              <span class="expense-meta">Food - Feb 14, 2026</span>
            </div>
            <span class="expense-amount">₱1,240</span>
          </div>
          <div class="expense-item">
            <div class="expense-info">
              <span class="expense-description">Monthly Jeepney Fare</span>
              <span class="expense-meta">Transportation - Feb 14, 2026</span>
            </div>
            <span class="expense-amount">₱800</span>
          </div>
          <div class="expense-item">
            <div class="expense-info">
              <span class="expense-description">Electricity Bill</span>
              <span class="expense-meta">Bills - Feb 14, 2026</span>
            </div>
            <span class="expense-amount">₱2,150</span>
          </div>
          <div class="expense-item">
            <div class="expense-info">
              <span class="expense-description">Savings</span>
              <span class="expense-meta">Savings - Feb 14, 2026</span>
            </div>
            <span class="expense-amount">₱900</span>
          </div>
        </div>
        
        <div class="add-expense-box">
          <form method="POST" class="add-expense-form-light">
            <select id="saved-qr-select" aria-label="Saved QR codes" style="width: 180px;">
              <option value="">Use saved QR…</option>
              <?php while ($qr = $savedQrResult->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($qr['qr_data'], ENT_QUOTES) ?>">
                  <?php
                    $label = (string) $qr['qr_data'];
                    $labelShort = (strlen($label) > 28) ? (substr($label, 0, 28) . '...') : $label;
                    echo htmlspecialchars($labelShort);
                  ?>
                </option>
              <?php endwhile; ?>
            </select>
            <input name="amount" type="number" step="0.01" min="0" placeholder="0.00" required style="width: 80px;">
            <input id="expense-description" name="description" type="text" placeholder="e.g. Lunch / QR data" required style="flex: 1;">
            <select name="category" required style="width: 120px;">
              <option value="Food">Food</option>
              <option value="Transportation">Transportation</option>
              <option value="Bills">Bills</option>
              <option value="Savings">Savings</option>
            </select>
            <button type="submit">Add</button>
          </form>
          <form method="POST" class="add-expense-form-light" style="margin-top: 10px; display: flex; gap: 8px; align-items: center;">
            <input name="qr_data" type="text" placeholder="Quick save QR data" style="flex: 1;">
            <button type="submit" name="save_qr" value="1">Save QR</button>
          </form>
        </div>
      </div>

      <!-- Spending Insights Card -->
      <div class="card figma-teal insights-card">
        <h3>Spending Insights</h3>
        <div class="insight-list">
          <div class="insight-box red">
            <div class="insight-box-title">Bills near limit</div>
            <p class="insight-box-text">You've used 95% of your Bills budget. Avoid adding new bill expenses this month.</p>
          </div>
          
          <div class="insight-box red">
            <div class="insight-box-title">Food trending high</div>
            <p class="insight-box-text">Food spending is at 82%. Consider meal-prepping to stretch your food budget.</p>
          </div>
          
          <div class="insight-box green">
            <div class="insight-box-title">Transport on track</div>
            <p class="insight-box-text">Only 42% of transport budget used. You have ₱2,900 remaining.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function () {
      const sel = document.getElementById('saved-qr-select');
      const desc = document.getElementById('expense-description');
      if (!sel || !desc) return;
      sel.addEventListener('change', () => {
        const val = sel.value || '';
        if (!val) return;
        desc.value = val;
        desc.focus();
        // reset picker so user can re-select same QR later
        sel.value = '';
      });
    })();
  </script>
</body>
</html>
