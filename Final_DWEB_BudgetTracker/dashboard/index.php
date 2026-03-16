<?php
$pageTitle = "Dashboard";
require_once __DIR__ . '/../config/performance.php';
$perf = new PerformanceMonitor();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$month = date('Y-m');

if (isset($_POST['edit_expense_id'])) {
    $exp_id = (int) $_POST['edit_expense_id'];
    $amount = (float) ($_POST['edit_amount'] ?? 0);
    $description = trim($_POST['edit_description'] ?? '');
    $category = trim($_POST['edit_category'] ?? 'Food');

    $stmt = $conn->prepare("UPDATE expenses SET amount = ?, category = ?, description = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("dssii", $amount, $category, $description, $exp_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

if (isset($_POST['delete_expense_id'])) {
    $exp_id = (int) $_POST['delete_expense_id'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $exp_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

if (isset($_POST['set_budget']) && ($_POST['budget'] ?? '') !== '') {
    $total_budget = (float) $_POST['budget'];
    $pct_food = isset($_POST['pct_food']) ? (float) $_POST['pct_food'] : 40;
    $pct_transport = isset($_POST['pct_transport']) ? (float) $_POST['pct_transport'] : 25;
    $pct_bills = isset($_POST['pct_bills']) ? (float) $_POST['pct_bills'] : 20;
    $pct_savings = isset($_POST['pct_savings']) ? (float) $_POST['pct_savings'] : 15;

    $pct_food = max(0, $pct_food);
    $pct_transport = max(0, $pct_transport);
    $pct_bills = max(0, $pct_bills);
    $pct_savings = max(0, $pct_savings);

    $sum = $pct_food + $pct_transport + $pct_bills + $pct_savings;
    if ($sum > 0) {
        $pct_food = round($pct_food / $sum * 100, 2);
        $pct_transport = round($pct_transport / $sum * 100, 2);
        $pct_bills = round($pct_bills / $sum * 100, 2);
        $pct_savings = round(100 - $pct_food - $pct_transport - $pct_bills, 2);
    } else {
        $pct_food = 40;
        $pct_transport = 25;
        $pct_bills = 20;
        $pct_savings = 15;
    }

    $stmt = $conn->prepare("INSERT INTO budgets (user_id, month, total_budget) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $user_id, $month, $total_budget);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM category_budgets WHERE user_id = ? AND month = ?");
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    $stmt->close();

    $categories = [
        'Food' => $pct_food,
        'Transportation' => $pct_transport,
        'Bills' => $pct_bills,
        'Savings' => $pct_savings,
    ];

    foreach ($categories as $cat => $pct) {
        $alloc = round($total_budget * $pct / 100, 2);
        $stmt = $conn->prepare("INSERT INTO category_budgets (user_id, month, category, allocated_amount, percentage) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issdd", $user_id, $month, $cat, $alloc, $pct);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: index.php");
    exit;
}

if (isset($_POST['add_expense']) && ($_POST['amount'] ?? '') !== '') {
    $amount = (float) $_POST['amount'];
    $description = trim($_POST['description'] ?? 'Expense');
    $category = trim($_POST['category'] ?? 'Food');
    $date = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $user_id, $amount, $category, $description, $date);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT total_budget FROM budgets WHERE user_id = ? AND month = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$budget_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = $budget_row ? (float) $budget_row['total_budget'] : 0.0;

$stmt = $conn->prepare("SELECT category, allocated_amount, percentage FROM category_budgets WHERE user_id = ? AND month = ?");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$res = $stmt->get_result();
$cat_alloc = [];
while ($row = $res->fetch_assoc()) {
    $cat_alloc[$row['category']] = $row;
}
$stmt->close();

$defaults = ['Food' => 40, 'Transportation' => 25, 'Bills' => 20, 'Savings' => 15];
foreach ($defaults as $cat => $pct) {
    if (!isset($cat_alloc[$cat])) {
        $cat_alloc[$cat] = [
            'category' => $cat,
            'allocated_amount' => $total > 0 ? round($total * $pct / 100, 2) : 0,
            'percentage' => $pct,
        ];
    }
}

$stmt = $conn->prepare("SELECT id, amount, category, description, date FROM expenses WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? ORDER BY date DESC, id DESC");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$res = $stmt->get_result();
$expenses = [];
while ($row = $res->fetch_assoc()) {
    $expenses[] = $row;
}
$stmt->close();

$cat_spent = ['Food' => 0, 'Transportation' => 0, 'Bills' => 0, 'Savings' => 0];
$spent = 0.0;
foreach ($expenses as $expense) {
    $spent += (float) $expense['amount'];
    if (array_key_exists($expense['category'], $cat_spent)) {
        $cat_spent[$expense['category']] += (float) $expense['amount'];
    }
}

$remaining = $total - $spent;
$percent_used = $total > 0 ? round(($spent / $total) * 100, 1) : 0;
$percent_left = $total > 0 ? round(($remaining / $total) * 100, 1) : 0;

function catPct($spent_value, $alloc_value)
{
    if ($alloc_value <= 0) {
        return 0;
    }
    return min(100, round($spent_value / $alloc_value * 100, 1));
}

function statusCls($pct)
{
    if ($pct >= 90) {
        return 'danger';
    }
    if ($pct >= 70) {
        return 'warning';
    }
    return 'safe';
}

function catCss($cat)
{
    $map = [
        'Food' => 'food',
        'Transportation' => 'transport',
        'Bills' => 'bills',
        'Savings' => 'savings',
    ];
    return $map[$cat] ?? 'food';
}

$insights = [];
foreach (['Food', 'Transportation', 'Bills', 'Savings'] as $cat) {
    $alloc = (float) $cat_alloc[$cat]['allocated_amount'];
    $spent_cat = (float) $cat_spent[$cat];
    $pct = catPct($spent_cat, $alloc);
    $left = $alloc - $spent_cat;

    if ($pct >= 90) {
        $insights[] = ['color' => 'red', 'title' => $cat . ' near limit', 'text' => "You've used {$pct}% of your {$cat} budget. Avoid adding more {$cat} expenses this month."];
    } elseif ($pct >= 70) {
        $insights[] = ['color' => 'yellow', 'title' => $cat . ' trending high', 'text' => "{$cat} spending is at {$pct}%. Consider cutting back to stay on track."];
    } elseif ($pct < 50 && $alloc > 0) {
        $insights[] = ['color' => 'green', 'title' => $cat . ' on track', 'text' => 'Only ' . $pct . '% of ' . $cat . ' budget used. You have PHP ' . number_format($left, 2) . ' remaining.'];
    }
}
if (empty($insights)) {
    $insights[] = ['color' => 'green', 'title' => 'All categories on track', 'text' => 'Great job! All spending is within safe limits this month.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SmartBudget dashboard for budget tracking, expense management, and spending insights.">
    <meta name="keywords" content="budget dashboard, expense tracker, personal finance, money management">
    <meta name="author" content="SmartBudget Team">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
    <link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - SmartBudget</title>
</head>
<body class="app-page">
<div class="container figma-container">
    <header class="page-header">
        <div class="brand">
            <img src="../images/smartbudget-logo.svg" alt="SmartBudget" class="logo-img" style="height:40px;width:auto;">
            <span class="brand-name">SmartBudget</span>
        </div>
        <nav>
            <a href="index.php" class="nav-link active">Dashboard</a>
            <a href="../deals/stats.php" class="nav-link">Stats</a>
            <a href="../deals/qr-saver.php" class="nav-link">Saved QR Codes</a>
            <a href="../deals/profile.php" class="nav-link">Profile</a>
        </nav>
    </header>

    <div class="dashboard-grid summary-row">
        <div class="summary-card figma-card">
            <div class="summary-title">Total Monthly Budget</div>
            <div class="summary-amount"><?= $total > 0 ? 'PHP ' . number_format($total, 2) : 'PHP 0.00' ?></div>
            <div class="summary-meta"><?= $total > 0 ? 'Set on ' . date('M j, Y') : 'No budget set yet' ?></div>
        </div>
        <div class="summary-card figma-card">
            <div class="summary-title">Total Spent</div>
            <div class="summary-amount">PHP <?= number_format($spent, 2) ?></div>
            <div class="summary-meta"><?= $percent_used ?>% of budget used</div>
        </div>
        <div class="summary-card figma-card">
            <div class="summary-title">Remaining Budget</div>
            <div class="summary-amount"><?= $total > 0 ? 'PHP ' . number_format($remaining, 2) : 'PHP 0.00' ?></div>
            <div class="summary-meta"><?= $total > 0 ? $percent_left . '% left for the month' : 'Set a budget first' ?></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card figma-teal category-card">
            <h3>Category Breakdown</h3>
            <?php if ($total <= 0): ?>
                <p class="no-expenses">Set a monthly budget to see your breakdown.</p>
            <?php else: ?>
                <div class="category-list">
                    <?php foreach (['Food', 'Transportation', 'Bills', 'Savings'] as $cat): ?>
                        <?php
                        $alloc = (float) $cat_alloc[$cat]['allocated_amount'];
                        $spent_cat = (float) $cat_spent[$cat];
                        $pct = catPct($spent_cat, $alloc);
                        $status = statusCls($pct);
                        $css = catCss($cat);
                        $bar_cls = $css . ($status !== 'safe' ? ' ' . $status : '');
                        ?>
                        <div class="category-item">
                            <div class="category-left">
                                <span class="category-name"><?= htmlspecialchars($cat) ?></span>
                                <span class="category-amount">PHP <?= number_format($spent_cat, 2) ?> / PHP <?= number_format($alloc, 2) ?> allocated</span>
                            </div>
                            <div class="category-progress-row">
                                <div class="progress-bar">
                                    <div class="progress-fill <?= htmlspecialchars($bar_cls) ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="category-percentage"><?= $pct ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card figma-teal budget-card">
            <h3>Set Monthly Budget</h3>
            <form method="POST" id="budgetForm" class="budget-form">
                <input type="hidden" name="set_budget" value="1">
                <input type="hidden" name="pct_food" id="hPctFood" value="<?= $cat_alloc['Food']['percentage'] ?>">
                <input type="hidden" name="pct_transport" id="hPctTransport" value="<?= $cat_alloc['Transportation']['percentage'] ?>">
                <input type="hidden" name="pct_bills" id="hPctBills" value="<?= $cat_alloc['Bills']['percentage'] ?>">
                <input type="hidden" name="pct_savings" id="hPctSavings" value="<?= $cat_alloc['Savings']['percentage'] ?>">
                <div class="form-group budget-inline">
                    <label>Total Budget (PHP)</label>
                    <input name="budget" id="budgetInput" type="number" step="0.01" min="0" placeholder="e.g. 20000" required>
                    <button type="submit">Set</button>
                </div>
            </form>

            <div class="auto-split">
                <h4>Auto-split across categories</h4>
                <div class="split-grid" id="splitGrid" onclick="openModal()">
                    <div class="split-tag">
                        <span class="tag-name">Food</span>
                        <span class="tag-amount" id="dispFood">PHP <?= number_format($cat_alloc['Food']['allocated_amount'], 0) ?></span>
                        <span class="tag-percent" id="dispPctFood"><?= $cat_alloc['Food']['percentage'] ?>%</span>
                    </div>
                    <div class="split-tag">
                        <span class="tag-name">Transport</span>
                        <span class="tag-amount" id="dispTransport">PHP <?= number_format($cat_alloc['Transportation']['allocated_amount'], 0) ?></span>
                        <span class="tag-percent" id="dispPctTransport"><?= $cat_alloc['Transportation']['percentage'] ?>%</span>
                    </div>
                    <div class="split-tag">
                        <span class="tag-name">Bills</span>
                        <span class="tag-amount" id="dispBills">PHP <?= number_format($cat_alloc['Bills']['allocated_amount'], 0) ?></span>
                        <span class="tag-percent" id="dispPctBills"><?= $cat_alloc['Bills']['percentage'] ?>%</span>
                    </div>
                    <div class="split-tag">
                        <span class="tag-name">Savings</span>
                        <span class="tag-amount" id="dispSavings">PHP <?= number_format($cat_alloc['Savings']['allocated_amount'], 0) ?></span>
                        <span class="tag-percent" id="dispPctSavings"><?= $cat_alloc['Savings']['percentage'] ?>%</span>
                    </div>
                </div>
                <div class="customize-hint" onclick="openModal()">Tap to customize percentages</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card figma-teal expenses-card">
            <h3>Recent Expenses</h3>
            <div class="expense-list">
                <?php if (empty($expenses)): ?>
                    <p class="no-expenses">No expenses recorded this month yet.</p>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <div class="expense-item">
                            <div class="expense-info">
                                <span class="expense-description"><?= htmlspecialchars($e['description']) ?></span>
                                <span class="expense-meta"><?= htmlspecialchars($e['category']) ?> - <?= date('M j, Y', strtotime($e['date'])) ?></span>
                            </div>
                            <span class="expense-amount">PHP <?= number_format((float) $e['amount'], 2) ?></span>
                            <button type="button" class="btn-delete-expense" onclick="openEditModal('<?= (int) $e['id'] ?>', '<?= (float) $e['amount'] ?>', '<?= htmlspecialchars($e['description'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['category'], ENT_QUOTES) ?>')" title="Edit">Edit</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_expense_id" value="<?= (int) $e['id'] ?>">
                                <button type="submit" class="btn-delete-expense" onclick="return confirm('Delete this expense?')" title="Delete">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="expense-add-card">
                <h4 class="expense-title">Add New Expense</h4>
                <form method="POST" class="expense-form">
                    <input type="hidden" name="add_expense" value="1">
                    <div class="expense-grid">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" step="0.01" min="0.01" placeholder="PHP 0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" placeholder="Lunch, coffee, gas" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="Food">Food</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Bills">Bills</option>
                                <option value="Savings">Savings</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-add-expense">Add Expense</button>
                </form>
            </div>
        </div>

        <div class="card figma-teal insights-card">
            <h3>Spending Insights</h3>
            <div class="insight-list">
                <?php foreach ($insights as $ins): ?>
                    <div class="insight-box <?= htmlspecialchars($ins['color']) ?>">
                        <div class="insight-box-title"><?= htmlspecialchars($ins['title']) ?></div>
                        <p class="insight-box-text"><?= htmlspecialchars($ins['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="achievements" class="card figma-teal" style="margin-top:20px;padding:12px;"></div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="closeModalIfBg(event)">
    <div class="modal-box">
        <h4>Customize Budget Split</h4>
        <p class="modal-hint">Enter percentages for each category. Values are auto-normalized to 100% when saved.</p>
        <div class="pct-row"><label>Food</label><input type="number" id="mPctFood" min="0" max="100" step="0.1" value="<?= $cat_alloc['Food']['percentage'] ?>" oninput="updateTotal()"><span>%</span></div>
        <div class="pct-row"><label>Transport</label><input type="number" id="mPctTransport" min="0" max="100" step="0.1" value="<?= $cat_alloc['Transportation']['percentage'] ?>" oninput="updateTotal()"><span>%</span></div>
        <div class="pct-row"><label>Bills</label><input type="number" id="mPctBills" min="0" max="100" step="0.1" value="<?= $cat_alloc['Bills']['percentage'] ?>" oninput="updateTotal()"><span>%</span></div>
        <div class="pct-row"><label>Savings</label><input type="number" id="mPctSavings" min="0" max="100" step="0.1" value="<?= $cat_alloc['Savings']['percentage'] ?>" oninput="updateTotal()"><span>%</span></div>
        <div class="pct-total-row">Total: <span id="pctTotal">100</span>% <span id="pctWarning" style="display:none;">Will be normalized to 100%</span></div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn-apply" onclick="applyPercentages()">Apply</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal" onclick="closeEditModalIfBg(event)">
    <div class="modal-box">
        <h4>Edit Expense</h4>
        <form method="POST">
            <input type="hidden" name="edit_expense_id" id="edit_id">
            <div class="pct-row"><label>Amount</label><input type="number" step="0.01" name="edit_amount" id="edit_amount" required></div>
            <div class="pct-row"><label>Description</label><input type="text" name="edit_description" id="edit_description" required></div>
            <div class="pct-row">
                <label>Category</label>
                <select name="edit_category" id="edit_category">
                    <option value="Food">Food</option>
                    <option value="Transportation">Transportation</option>
                    <option value="Bills">Bills</option>
                    <option value="Savings">Savings</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-apply">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/app.js"></script>
<script>
function openModal() {
    document.getElementById('modalOverlay').classList.add('open');
    updateTotal();
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

function closeModalIfBg(e) {
    if (e.target.id === 'modalOverlay') {
        closeModal();
    }
}

function updateTotal() {
    const f = parseFloat(document.getElementById('mPctFood').value) || 0;
    const t = parseFloat(document.getElementById('mPctTransport').value) || 0;
    const b = parseFloat(document.getElementById('mPctBills').value) || 0;
    const s = parseFloat(document.getElementById('mPctSavings').value) || 0;
    const total = Math.round((f + t + b + s) * 10) / 10;
    document.getElementById('pctTotal').textContent = total;
    document.getElementById('pctWarning').style.display = (total !== 100) ? 'inline' : 'none';
}

function applyPercentages() {
    const f = parseFloat(document.getElementById('mPctFood').value) || 0;
    const t = parseFloat(document.getElementById('mPctTransport').value) || 0;
    const b = parseFloat(document.getElementById('mPctBills').value) || 0;
    const s = parseFloat(document.getElementById('mPctSavings').value) || 0;
    const sum = f + t + b + s || 1;
    const budgetValue = parseFloat(document.getElementById('budgetInput').value) || 0;

    document.getElementById('hPctFood').value = f;
    document.getElementById('hPctTransport').value = t;
    document.getElementById('hPctBills').value = b;
    document.getElementById('hPctSavings').value = s;

    function percent(val) {
        return (Math.round(val / sum * 1000) / 10) + '%';
    }
    function amount(val) {
        return 'PHP ' + Math.round(budgetValue * val / sum).toLocaleString();
    }

    document.getElementById('dispPctFood').textContent = percent(f);
    document.getElementById('dispPctTransport').textContent = percent(t);
    document.getElementById('dispPctBills').textContent = percent(b);
    document.getElementById('dispPctSavings').textContent = percent(s);

    if (budgetValue > 0) {
        document.getElementById('dispFood').textContent = amount(f);
        document.getElementById('dispTransport').textContent = amount(t);
        document.getElementById('dispBills').textContent = amount(b);
        document.getElementById('dispSavings').textContent = amount(s);
    }
    closeModal();
}

const budgetInput = document.getElementById('budgetInput');
if (budgetInput) {
    budgetInput.addEventListener('input', function () {
        const value = parseFloat(this.value) || 0;
        const f = parseFloat(document.getElementById('hPctFood').value) || 0;
        const t = parseFloat(document.getElementById('hPctTransport').value) || 0;
        const b = parseFloat(document.getElementById('hPctBills').value) || 0;
        const s = parseFloat(document.getElementById('hPctSavings').value) || 0;
        const sum = f + t + b + s || 100;
        function amount(val) {
            return 'PHP ' + Math.round(value * val / sum).toLocaleString();
        }
        document.getElementById('dispFood').textContent = amount(f);
        document.getElementById('dispTransport').textContent = amount(t);
        document.getElementById('dispBills').textContent = amount(b);
        document.getElementById('dispSavings').textContent = amount(s);
    });
}

function openEditModal(id, amount, description, category) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_category').value = category;
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function closeEditModalIfBg(e) {
    if (e.target.id === 'editModal') {
        closeEditModal();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('.page-header');
    if (header) {
        const exportBtn = document.createElement('button');
        exportBtn.id = 'exportData';
        exportBtn.className = 'btn-secondary';
        exportBtn.style.marginLeft = 'auto';
        exportBtn.textContent = 'Export Data';
        header.appendChild(exportBtn);
    }

    const achievements = document.getElementById('achievements');
    const spent = <?= (float) $spent ?>;
    const total = <?= (float) $total ?>;
    if (achievements) {
        const badges = [];
        if (spent === 0) badges.push('No expenses yet');
        if (total > 0 && spent < total * 0.5) badges.push('Great saver');
        if (total > 0 && spent > total) badges.push('Budget exceeded');
        achievements.innerHTML = badges.map(function (b) {
            return '<span class=\"badge\" style=\"background:rgba(0,204,153,0.1);color:var(--accent);padding:4px 12px;border-radius:99px;margin-right:8px;\">' + b + '</span>';
        }).join('');
    }
});
</script>
<?php $perf->displayStats(); ?>
</body>
</html>
