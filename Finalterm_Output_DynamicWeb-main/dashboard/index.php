<?php
include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$month = date('Y-m');

// Handle Delete Expense
if (isset($_POST['delete_expense_id'])) {
    $exp_id = (int) $_POST['delete_expense_id'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $exp_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

// Handle Set Budget with custom percentages
if (isset($_POST['set_budget']) && $_POST['budget'] !== '') {
    $total_budget  = (float) $_POST['budget'];
    $pct_food      = isset($_POST['pct_food'])      ? (float)$_POST['pct_food']      : 40;
    $pct_transport = isset($_POST['pct_transport'])  ? (float)$_POST['pct_transport']  : 25;
    $pct_bills     = isset($_POST['pct_bills'])      ? (float)$_POST['pct_bills']      : 20;
    $pct_savings   = isset($_POST['pct_savings'])    ? (float)$_POST['pct_savings']    : 15;

    $pct_food = max(0,$pct_food); $pct_transport = max(0,$pct_transport);
    $pct_bills = max(0,$pct_bills); $pct_savings = max(0,$pct_savings);
    $sum = $pct_food + $pct_transport + $pct_bills + $pct_savings;
    if ($sum > 0) {
        $pct_food      = round($pct_food      / $sum * 100, 2);
        $pct_transport = round($pct_transport / $sum * 100, 2);
        $pct_bills     = round($pct_bills     / $sum * 100, 2);
        $pct_savings   = round(100 - $pct_food - $pct_transport - $pct_bills, 2);
    } else { $pct_food=40; $pct_transport=25; $pct_bills=20; $pct_savings=15; }

    $stmt = $conn->prepare("INSERT INTO budgets (user_id, month, total_budget) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $user_id, $month, $total_budget);
    $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM category_budgets WHERE user_id = ? AND month = ?");
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute(); $stmt->close();

    $cats = ['Food'=>$pct_food,'Transportation'=>$pct_transport,'Bills'=>$pct_bills,'Savings'=>$pct_savings];
    foreach ($cats as $cat => $pct) {
        $alloc = round($total_budget * $pct / 100, 2);
        $stmt = $conn->prepare("INSERT INTO category_budgets (user_id, month, category, allocated_amount, percentage) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issdd", $user_id, $month, $cat, $alloc, $pct);
        $stmt->execute(); $stmt->close();
    }
    header("Location: index.php"); exit;
}

// Handle Add Expense
if (isset($_POST['add_expense']) && $_POST['amount'] !== '') {
    $amount      = (float) $_POST['amount'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : 'Expense';
    $category    = isset($_POST['category'])    ? trim($_POST['category'])    : 'Food';
    $date        = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, date) VALUES (?,?,?,?,?)");
    $stmt->bind_param("idsss", $user_id, $amount, $category, $description, $date);
    $stmt->execute(); $stmt->close();
    header("Location: index.php"); exit;
}

// Fetch budget
$stmt = $conn->prepare("SELECT total_budget FROM budgets WHERE user_id=? AND month=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$budget_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
$total = $budget_row ? (float)$budget_row['total_budget'] : 0;

// Fetch category allocations
$stmt = $conn->prepare("SELECT category, allocated_amount, percentage FROM category_budgets WHERE user_id=? AND month=?");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$res = $stmt->get_result();
$cat_alloc = [];
while ($r = $res->fetch_assoc()) { $cat_alloc[$r['category']] = $r; }
$stmt->close();

$defaults = ['Food'=>40,'Transportation'=>25,'Bills'=>20,'Savings'=>15];
foreach ($defaults as $cat => $pct) {
    if (!isset($cat_alloc[$cat])) {
        $cat_alloc[$cat] = ['category'=>$cat, 'allocated_amount'=>$total>0?round($total*$pct/100,2):0, 'percentage'=>$pct];
    }
}

// Fetch expenses for this month
$stmt = $conn->prepare("SELECT id,amount,category,description,date FROM expenses WHERE user_id=? AND DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC, id DESC");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$res = $stmt->get_result();
$expenses = [];
while ($r = $res->fetch_assoc()) { $expenses[] = $r; }
$stmt->close();

// Totals
$cat_spent = ['Food'=>0,'Transportation'=>0,'Bills'=>0,'Savings'=>0];
$spent = 0;
foreach ($expenses as $e) {
    $spent += $e['amount'];
    if (array_key_exists($e['category'], $cat_spent)) $cat_spent[$e['category']] += $e['amount'];
}
$remaining    = $total - $spent;
$percent_used = $total > 0 ? round(($spent/$total)*100,1) : 0;
$percent_left = $total > 0 ? round(($remaining/$total)*100,1) : 0;

function catPct($spent,$alloc) { if($alloc<=0)return 0; return min(100,round($spent/$alloc*100,1)); }
function statusCls($p) { if($p>=90)return 'danger'; if($p>=70)return 'warning'; return 'safe'; }
function catCss($cat) { $m=['Food'=>'food','Transportation'=>'transport','Bills'=>'bills','Savings'=>'savings']; return $m[$cat]??'food'; }

// Dynamic insights
$insights = [];
foreach (['Food','Transportation','Bills','Savings'] as $cat) {
    $alloc = (float)$cat_alloc[$cat]['allocated_amount'];
    $s = $cat_spent[$cat]; $p = catPct($s,$alloc); $left = $alloc - $s;
    if ($p >= 90)       $insights[]=[ 'color'=>'red',   'title'=>"$cat near limit",     'text'=>"You've used {$p}% of your $cat budget. Avoid adding more $cat expenses this month."];
    elseif ($p >= 70)   $insights[]=[ 'color'=>'yellow','title'=>"$cat trending high",   'text'=>"$cat spending is at {$p}%. Consider cutting back to stay on track."];
    elseif ($p < 50 && $alloc > 0) $insights[]=[ 'color'=>'green','title'=>"$cat on track", 'text'=>"Only {$p}% of $cat budget used. You have \u{20B1}".number_format($left,2)." remaining."];
}
if (empty($insights)) $insights[]=[ 'color'=>'green','title'=>'All categories on track','text'=>'Great job! All spending is within safe limits this month.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#fff;border-radius:14px;padding:24px;width:320px;max-width:90vw; }
    .modal-box h4 { margin:0 0 6px;color:#1A2828;font-size:14px;font-weight:700; }
    .modal-box p.modal-hint { margin:0 0 16px;font-size:11px;color:#666; }
    .pct-row { display:flex;align-items:center;gap:10px;margin-bottom:10px; }
    .pct-row label { flex:1;font-size:12px;font-weight:600;color:#1A2828; }
    .pct-row input { width:70px;padding:6px 8px;border:1px solid #ccc;border-radius:6px;font-size:13px;text-align:right; }
    .pct-total-row { font-size:12px;color:#555;margin-bottom:14px; }
    .pct-total-row span { font-weight:700; }
    .modal-actions { display:flex;gap:8px;justify-content:flex-end; }
    .modal-actions button { padding:7px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none; }
    .btn-cancel { background:#e5e7eb;color:#374151; }
    .btn-apply  { background:#14B8A6;color:#fff; }
    .split-grid { cursor:pointer; }
    .split-tag:hover { opacity:.8; }
    .customize-hint { font-size:10px;color:rgba(255,255,255,.7);margin-top:8px;text-align:right;cursor:pointer; }
    .customize-hint:hover { color:#fff; }
    .btn-delete-expense { background:none;border:none;cursor:pointer;color:rgba(255,255,255,.5);font-size:14px;padding:2px 6px;margin-left:8px;border-radius:4px;transition:color .15s; }
    .btn-delete-expense:hover { color:#f87171; }
    .progress-fill.danger  { background:#DC2626!important; }
    .progress-fill.warning { background:#F59E0B!important; }
    .insight-box.yellow { background:#FEF9C3; }
    .insight-box.yellow .insight-box-title { color:#92400E; }
    .no-expenses { color:rgba(255,255,255,.6);font-size:13px;text-align:center;padding:18px 0; }
  </style>
</head>
<body class="app-page">
<div class="container figma-container">

  <header class="page-header">
    <div class="brand">
      <img src="../images/smartbudget-logo.jpg" alt="SmartBudget" class="logo-img" style="height:40px;width:auto;">
      <span class="brand-name">SmartBudget</span>
    </div>
    <nav>
      <a href="index.php" class="nav-link active">Dashboard</a>
      <a href="#" class="nav-link">Stats</a>
      <a href="../deals/qr-saver.php" class="nav-link">Saved QR Codes</a>
      <a href="#" class="nav-link">Profile</a>
    </nav>
  </header>

  <!-- Summary Cards -->
  <div class="dashboard-grid summary-row">
    <div class="summary-card figma-card">
      <div class="summary-title">Total Monthly Budget</div>
      <div class="summary-amount"><?= $total>0 ? '&#8369;'.number_format($total,2) : '&#8369;0.00' ?></div>
      <div class="summary-meta"><?= $total>0 ? 'Set on '.date('M j, Y') : 'No budget set yet' ?></div>
    </div>
    <div class="summary-card figma-card">
      <div class="summary-title">Total Spent</div>
      <div class="summary-amount">&#8369;<?= number_format($spent,2) ?></div>
      <div class="summary-meta"><?= $percent_used ?>% of budget used</div>
    </div>
    <div class="summary-card figma-card">
      <div class="summary-title">Remaining Budget</div>
      <div class="summary-amount"><?= $total>0 ? '&#8369;'.number_format($remaining,2) : '&#8369;0.00' ?></div>
      <div class="summary-meta"><?= $total>0 ? $percent_left.'% left for the month' : 'Set a budget first' ?></div>
    </div>
  </div>

  <!-- Middle Row -->
  <div class="dashboard-grid">

    <!-- Category Breakdown -->
    <div class="card figma-teal category-card">
      <h3>Category Breakdown</h3>
      <?php if($total<=0): ?>
        <p style="color:rgba(255,255,255,.7);font-size:13px;text-align:center;padding:16px 0;">Set a monthly budget to see your breakdown.</p>
      <?php else: ?>
      <div class="category-list">
        <?php foreach(['Food','Transportation','Bills','Savings'] as $cat):
          $alloc  = (float)$cat_alloc[$cat]['allocated_amount'];
          $s      = $cat_spent[$cat];
          $pct    = catPct($s,$alloc);
          $status = statusCls($pct);
          $css    = catCss($cat);
          $barCls = $css.($status!=='safe' ? ' '.$status : '');
        ?>
        <div class="category-item">
          <div class="category-left">
            <span class="category-name"><?= htmlspecialchars($cat) ?></span>
            <span class="category-amount">&#8369;<?= number_format($s,2) ?> / &#8369;<?= number_format($alloc,2) ?> allocated</span>
          </div>
          <div class="category-progress-row">
            <div class="progress-bar">
              <div class="progress-fill <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="category-percentage"><?= $pct ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Set Monthly Budget -->
    <div class="card figma-teal budget-card">
      <h3>Set Monthly Budget</h3>
      <form method="POST" id="budgetForm" class="budget-form">
        <input type="hidden" name="set_budget" value="1">
        <input type="hidden" name="pct_food"      id="hPctFood"      value="<?= $cat_alloc['Food']['percentage'] ?>">
        <input type="hidden" name="pct_transport" id="hPctTransport" value="<?= $cat_alloc['Transportation']['percentage'] ?>">
        <input type="hidden" name="pct_bills"     id="hPctBills"     value="<?= $cat_alloc['Bills']['percentage'] ?>">
        <input type="hidden" name="pct_savings"   id="hPctSavings"   value="<?= $cat_alloc['Savings']['percentage'] ?>">
        <div class="form-group" style="display:flex;gap:var(--space-2);align-items:center;">
          <label style="font-size:var(--text-xs);color:#fff;white-space:nowrap;">Total Budget (&#8369;)</label>
          <input name="budget" id="budgetInput" type="number" step="0.01" min="0" placeholder="e.g. 20000" required
            style="flex:1;background:#fff;border:none;border-radius:var(--radius-sm);padding:var(--space-2) var(--space-3);color:#1A2828;font-size:var(--text-sm);">
          <button type="submit" style="background:#1A2828;color:#fff;border-radius:var(--radius-sm);padding:var(--space-2) var(--space-4);font-size:var(--text-xs);border:none;cursor:pointer;">Set</button>
        </div>
      </form>

      <div class="auto-split" style="margin-top:12px;">
        <h4 style="font-size:var(--text-xs);color:#fff;margin-bottom:var(--space-3);">Auto-split across categories</h4>
        <div class="split-grid" id="splitGrid" onclick="openModal()">
          <div class="split-tag">
            <span class="tag-name">Food</span>
            <span class="tag-amount" id="dispFood">&#8369;<?= number_format($cat_alloc['Food']['allocated_amount'],0) ?></span>
            <span class="tag-percent" id="dispPctFood"><?= $cat_alloc['Food']['percentage'] ?>%</span>
          </div>
          <div class="split-tag">
            <span class="tag-name">Transport</span>
            <span class="tag-amount" id="dispTransport">&#8369;<?= number_format($cat_alloc['Transportation']['allocated_amount'],0) ?></span>
            <span class="tag-percent" id="dispPctTransport"><?= $cat_alloc['Transportation']['percentage'] ?>%</span>
          </div>
          <div class="split-tag">
            <span class="tag-name">Bills</span>
            <span class="tag-amount" id="dispBills">&#8369;<?= number_format($cat_alloc['Bills']['allocated_amount'],0) ?></span>
            <span class="tag-percent" id="dispPctBills"><?= $cat_alloc['Bills']['percentage'] ?>%</span>
          </div>
          <div class="split-tag">
            <span class="tag-name">Savings</span>
            <span class="tag-amount" id="dispSavings">&#8369;<?= number_format($cat_alloc['Savings']['allocated_amount'],0) ?></span>
            <span class="tag-percent" id="dispPctSavings"><?= $cat_alloc['Savings']['percentage'] ?>%</span>
          </div>
        </div>
        <div class="customize-hint" onclick="openModal()">&#9998; Tap to customise percentages</div>
      </div>
    </div>
  </div>

  <!-- Bottom Row -->
  <div class="dashboard-grid">

    <!-- Recent Expenses -->
    <div class="card figma-teal expenses-card">
      <h3>Recent Expenses</h3>
      <div class="expense-list">
        <?php if(empty($expenses)): ?>
          <p class="no-expenses">No expenses recorded this month yet.</p>
        <?php else: ?>
          <?php foreach($expenses as $e):
            $fd = date('M j, Y', strtotime($e['date']));
          ?>
          <div class="expense-item" style="display:flex;align-items:center;">
            <div class="expense-info" style="flex:1;">
              <span class="expense-description"><?= htmlspecialchars($e['description']) ?></span>
              <span class="expense-meta"><?= htmlspecialchars($e['category']) ?> &mdash; <?= $fd ?></span>
            </div>
            <span class="expense-amount">&#8369;<?= number_format($e['amount'],2) ?></span>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="delete_expense_id" value="<?= (int)$e['id'] ?>">
              <button type="submit" class="btn-delete-expense"
                onclick="return confirm('Delete this expense?')" title="Delete">&#x2715;</button>
            </form>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="add-expense-box">
        <form method="POST" class="add-expense-form-light">
          <input type="hidden" name="add_expense" value="1">
          <input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" required style="width:80px;">
          <input name="description" type="text" placeholder="e.g. Lunch" required style="flex:1;">
          <select name="category" required style="width:130px;">
            <option value="Food">Food</option>
            <option value="Transportation">Transportation</option>
            <option value="Bills">Bills</option>
            <option value="Savings">Savings</option>
          </select>
          <button type="submit">Add</button>
        </form>
      </div>
    </div>

    <!-- Spending Insights -->
    <div class="card figma-teal insights-card">
      <h3>Spending Insights</h3>
      <div class="insight-list">
        <?php foreach($insights as $ins): ?>
        <div class="insight-box <?= htmlspecialchars($ins['color']) ?>">
          <div class="insight-box-title"><?= htmlspecialchars($ins['title']) ?></div>
          <p class="insight-box-text"><?= htmlspecialchars($ins['text']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<!-- Customise Split Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModalIfBg(event)">
  <div class="modal-box">
    <h4>&#9999; Customise Budget Split</h4>
    <p class="modal-hint">Enter percentages for each category. They will be auto-normalised to total 100%.</p>
    <div class="pct-row">
      <label>&#127828; Food</label>
      <input type="number" id="mPctFood"      min="0" max="100" step="0.1" value="<?= $cat_alloc['Food']['percentage'] ?>" oninput="updateTotal()"><span>%</span>
    </div>
    <div class="pct-row">
      <label>&#128652; Transport</label>
      <input type="number" id="mPctTransport" min="0" max="100" step="0.1" value="<?= $cat_alloc['Transportation']['percentage'] ?>" oninput="updateTotal()"><span>%</span>
    </div>
    <div class="pct-row">
      <label>&#128161; Bills</label>
      <input type="number" id="mPctBills"     min="0" max="100" step="0.1" value="<?= $cat_alloc['Bills']['percentage'] ?>" oninput="updateTotal()"><span>%</span>
    </div>
    <div class="pct-row">
      <label>&#127968; Savings</label>
      <input type="number" id="mPctSavings"   min="0" max="100" step="0.1" value="<?= $cat_alloc['Savings']['percentage'] ?>" oninput="updateTotal()"><span>%</span>
    </div>
    <div class="pct-total-row">Total: <span id="pctTotal">100</span>% <span id="pctWarning" style="color:#DC2626;display:none;">&#9888; Will be auto-normalised to 100%</span></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-apply"  onclick="applyPercentages()">Apply</button>
    </div>
  </div>
</div>

<script>
function openModal(){ document.getElementById('modalOverlay').classList.add('open'); updateTotal(); }
function closeModal(){ document.getElementById('modalOverlay').classList.remove('open'); }
function closeModalIfBg(e){ if(e.target===document.getElementById('modalOverlay')) closeModal(); }

function updateTotal(){
  const f=parseFloat(document.getElementById('mPctFood').value)||0;
  const t=parseFloat(document.getElementById('mPctTransport').value)||0;
  const b=parseFloat(document.getElementById('mPctBills').value)||0;
  const s=parseFloat(document.getElementById('mPctSavings').value)||0;
  const tot=Math.round((f+t+b+s)*10)/10;
  document.getElementById('pctTotal').textContent=tot;
  document.getElementById('pctWarning').style.display=(tot!==100)?'inline':'none';
}

function applyPercentages(){
  const f=parseFloat(document.getElementById('mPctFood').value)||0;
  const t=parseFloat(document.getElementById('mPctTransport').value)||0;
  const b=parseFloat(document.getElementById('mPctBills').value)||0;
  const s=parseFloat(document.getElementById('mPctSavings').value)||0;
  document.getElementById('hPctFood').value=f;
  document.getElementById('hPctTransport').value=t;
  document.getElementById('hPctBills').value=b;
  document.getElementById('hPctSavings').value=s;
  const bv=parseFloat(document.getElementById('budgetInput').value)||0;
  const sum=f+t+b+s||1;
  function fmt(x){return '\u20B1'+(Math.round(bv*x/sum)).toLocaleString();}
  function pf(x){return (Math.round(x/sum*1000)/10)+'%';}
  document.getElementById('dispPctFood').textContent=pf(f);
  document.getElementById('dispPctTransport').textContent=pf(t);
  document.getElementById('dispPctBills').textContent=pf(b);
  document.getElementById('dispPctSavings').textContent=pf(s);
  if(bv>0){
    document.getElementById('dispFood').textContent=fmt(f);
    document.getElementById('dispTransport').textContent=fmt(t);
    document.getElementById('dispBills').textContent=fmt(b);
    document.getElementById('dispSavings').textContent=fmt(s);
  }
  closeModal();
}

document.getElementById('budgetInput').addEventListener('input',function(){
  const bv=parseFloat(this.value)||0;
  const f=parseFloat(document.getElementById('hPctFood').value)||0;
  const t=parseFloat(document.getElementById('hPctTransport').value)||0;
  const b=parseFloat(document.getElementById('hPctBills').value)||0;
  const s=parseFloat(document.getElementById('hPctSavings').value)||0;
  const sum=f+t+b+s||100;
  function fmt(x){return '\u20B1'+Math.round(bv*x/sum).toLocaleString();}
  document.getElementById('dispFood').textContent=fmt(f);
  document.getElementById('dispTransport').textContent=fmt(t);
  document.getElementById('dispBills').textContent=fmt(b);
  document.getElementById('dispSavings').textContent=fmt(s);
});
</script>
</body>
</html>
