<?php
$pageTitle = "Statistics";
require_once __DIR__ . '/../config/performance.php';
$perf = new PerformanceMonitor();

include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$monthly_sql = "
    SELECT DATE_FORMAT(date,'%b %Y') AS label,
           DATE_FORMAT(date,'%Y-%m') AS ym,
           SUM(amount) AS total_spent
    FROM expenses
    WHERE user_id=$user_id AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym,label ORDER BY ym ASC";
$monthly_data = [];
$r = mysqli_query($conn,$monthly_sql);
while($row=mysqli_fetch_assoc($r)) $monthly_data[]=$row;

$weekly_sql = "
    SELECT CONCAT('Wk ',WEEK(date,1)) AS label,
           YEARWEEK(date,1) AS yw,
           SUM(amount) AS total_spent
    FROM expenses
    WHERE user_id=$user_id AND date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY yw,label ORDER BY yw ASC";
$weekly_data = [];
$r = mysqli_query($conn,$weekly_sql);
while($row=mysqli_fetch_assoc($r)) $weekly_data[]=$row;

$yearly_sql = "
    SELECT YEAR(date) AS label, YEAR(date) AS yr, SUM(amount) AS total_spent
    FROM expenses WHERE user_id=$user_id
    GROUP BY yr ORDER BY yr ASC LIMIT 4";
$yearly_data = [];
$r = mysqli_query($conn,$yearly_sql);
while($row=mysqli_fetch_assoc($r)) $yearly_data[]=$row;

$cat_sql = "
    SELECT e.category,
           DATE_FORMAT(e.date,'%b') AS mon,
           DATE_FORMAT(e.date,'%Y-%m') AS ym,
           SUM(e.amount) AS total,
           COALESCE(cb.allocated_amount,0) AS budget
    FROM expenses e
    LEFT JOIN category_budgets cb
        ON cb.user_id=e.user_id AND cb.category=e.category
        AND cb.month=DATE_FORMAT(e.date,'%Y-%m')
    WHERE e.user_id=$user_id AND e.date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY e.category,ym,mon ORDER BY e.category,ym ASC";
$cat_raw = [];
$r = mysqli_query($conn,$cat_sql);
while($row=mysqli_fetch_assoc($r))
    $cat_raw[$row['category']][$row['mon']]=['total'=>(float)$row['total'],'budget'=>(float)$row['budget']];

$breakdown_sql = "
    SELECT DATE_FORMAT(e.date,'%b %Y') AS month_label,
           DATE_FORMAT(e.date,'%Y-%m') AS ym,
           SUM(e.amount) AS spent,
           COALESCE(b.total_budget,0) AS budget
    FROM expenses e
    LEFT JOIN budgets b ON b.user_id=e.user_id AND b.month=DATE_FORMAT(e.date,'%Y-%m')
    WHERE e.user_id=$user_id AND e.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym,month_label,b.total_budget ORDER BY ym DESC LIMIT 6";
$breakdown_data = [];
$r = mysqli_query($conn,$breakdown_sql);
while($row=mysqli_fetch_assoc($r)) $breakdown_data[]=$row;

$js_monthly   = json_encode($monthly_data);
$js_weekly    = json_encode($weekly_data);
$js_yearly    = json_encode($yearly_data);
$js_cats      = json_encode($cat_raw);
$js_breakdown = json_encode($breakdown_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="View your spending statistics and trends with SmartBudget.">
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
<link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    <link rel="stylesheet" href="../css/responsive.css">
<title><?= $pageTitle ?> – SmartBudget</title>
</head>
<body class="app-page">
<div class="container figma-container">

  <?php include '../includes/header.php'; ?>

  <div class="stats-wrap">

    <div class="stats-toprow">
      <h2>Spending <span>Statistics</span></h2>
      <div class="tab-bar">
        <button class="stab"        onclick="switchPeriod('weekly',this)">Weekly</button>
        <button class="stab active" onclick="switchPeriod('monthly',this)">Monthly</button>
        <button class="stab"        onclick="switchPeriod('yearly',this)">Yearly</button>
      </div>
    </div>

    <div class="stats-summary">
      <div class="summary-card figma-card">
        <div class="summary-title">Total Spent</div>
        <div class="summary-amount" id="sTotal">—</div>
        <div class="summary-meta"  id="sPeriods">across all periods</div>
      </div>
      <div class="summary-card figma-card">
        <div class="summary-title">Average / Period</div>
        <div class="summary-amount" id="sAvg">—</div>
        <div class="summary-meta"  id="sAvgSub">per period</div>
      </div>
      <div class="summary-card figma-card">
        <div class="summary-title">Previous Period</div>
        <div class="summary-amount" id="sVs">—</div>
        <div class="summary-meta"  id="sVsSub">peak period</div>
      </div>
    </div>

    <div class="stats-main">
      <div class="card figma-teal">
        <div class="teal-card-title" id="chartTitle">Total Monthly Spending</div>
        <div id="chartWrap"><div class="empty-msg">Loading chart…</div></div>
      </div>
      <div class="card figma-teal">
        <div class="teal-card-title">Category Trends</div>
        <div id="catList"><div class="empty-msg">Loading…</div></div>
      </div>
    </div>

    <div class="stats-section-hdr">Month-to-Month Breakdown</div>
    <div class="card figma-teal card-overflow-hidden">
      <div class="tbl-wrap">
        <table class="stats-table">
          <thead>
            <tr>
              <th>Month</th>
              <th>Budget</th>
              <th>Spent</th>
              <th>Remaining</th>
              <th>Usage</th>
              <th>vs Last Month</th>
            </tr>
          </thead>
          <tbody id="breakdownBody">
            <tr><td colspan="6" class="empty-msg">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
const RAW = {
  monthly:   <?= $js_monthly ?>,
  weekly:    <?= $js_weekly ?>,
  yearly:    <?= $js_yearly ?>,
  cats:      <?= $js_cats ?>,
  breakdown: <?= $js_breakdown ?>
};

if(!RAW.monthly.length) RAW.monthly=[
  {label:'Oct 2025',total_spent:10200},{label:'Nov 2025',total_spent:12800},
  {label:'Dec 2025',total_spent:17950},{label:'Jan 2026',total_spent:13440},
  {label:'Feb 2026',total_spent:11340},
];
if(!RAW.weekly.length) RAW.weekly=[
  {label:'Wk 40',total_spent:2300},{label:'Wk 41',total_spent:2800},
  {label:'Wk 42',total_spent:3100},{label:'Wk 43',total_spent:2600},
  {label:'Wk 44',total_spent:3400},{label:'Wk 45',total_spent:2900},
  {label:'Wk 46',total_spent:2200},{label:'Wk 47',total_spent:2700},
];
if(!RAW.yearly.length) RAW.yearly=[
  {label:'2023',total_spent:118000},{label:'2024',total_spent:134500},{label:'2025',total_spent:156200},
];
if(!Object.keys(RAW.cats).length) RAW.cats={
  'Food':          {Oct:{total:8000,budget:10000},Nov:{total:7800,budget:10000},Dec:{total:9200,budget:10000},Jan:{total:8400,budget:10000},Feb:{total:7600,budget:10000}},
  'Transportation':{Oct:{total:2100,budget:5000}, Nov:{total:2400,budget:5000}, Dec:{total:2700,budget:5000}, Jan:{total:2200,budget:5000}, Feb:{total:2300,budget:5000}},
  'Bills':         {Oct:{total:4200,budget:5000}, Nov:{total:4500,budget:5000}, Dec:{total:4800,budget:5000}, Jan:{total:4600,budget:5000}, Feb:{total:4300,budget:5000}},
  'Savings':       {Oct:{total:1500,budget:5000}, Nov:{total:1200,budget:5000}, Dec:{total:800, budget:5000}, Jan:{total:1400,budget:5000}, Feb:{total:1600,budget:5000}},
};
if(!RAW.breakdown.length) RAW.breakdown=[
  {month_label:'Feb 2026',spent:11340,budget:20000},
  {month_label:'Jan 2026',spent:13440,budget:20000},
  {month_label:'Dec 2025',spent:17950,budget:20000},
  {month_label:'Nov 2025',spent:12800,budget:20000},
  {month_label:'Oct 2025',spent:10200,budget:20000},
];

const fmt  = n => '₱'+Number(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmtK = n => n>=1000?(n/1000).toFixed(n%1000===0?0:1)+'k':n;
const BAR_COLS = ['#0d3d2e','#1a5c45','#0a2a1e','#164a38','#0f3528','#133e2e'];

function drawChart(period){
  const rows  = RAW[period];
  const titles= {monthly:'Total Monthly Spending',weekly:'Weekly Spending Comparison',yearly:'Yearly Spending Overview'};
  document.getElementById('chartTitle').textContent = titles[period];

  if(!rows.length){
    document.getElementById('chartWrap').innerHTML='<div class="empty-msg">No expense data yet.</div>';
    updateSummary([],rows,period); return;
  }

  const vals = rows.map(r=>+r.total_spent);
  const maxV = Math.max(...vals);
  const steps= 4;
  const step = Math.ceil(maxV/steps/500)*500||1000;
  const topV = step*steps;

  const yHtml = Array.from({length:steps+1},(_,i)=>(steps-i)*step)
    .map(v=>`<div class="y-tick">${fmtK(v)}</div>`).join('');

  const barsHtml = rows.map((r,i)=>{
    const pct = topV>0?(r.total_spent/topV*100):0;
    const col = BAR_COLS[i%BAR_COLS.length];
    const lbl = r.label.includes(' ')?r.label.replace(' ','<br>'):r.label;
    return `<div class="bar-col">
      <div class="bar-rect" style="height:${Math.max(pct,2)}%;background:${col}" data-tip="${fmt(r.total_spent)}"></div>
      <div class="bar-lbl">${lbl}</div>
    </div>`;
  }).join('');

  document.getElementById('chartWrap').innerHTML=
    `<div class="chart-area"><div class="y-axis">${yHtml}</div>${barsHtml}</div>`;

  updateSummary(vals,rows,period);
}

function updateSummary(vals,rows,period){
  if(!vals.length){
    ['sTotal','sAvg','sVs'].forEach(id=>document.getElementById(id).textContent='—');
    return;
  }
  const total  = vals.reduce((a,b)=>a+b,0);
  const avg    = total/vals.length;
  const peakI  = vals.indexOf(Math.max(...vals));
  const last   = vals[vals.length-1];
  const prev   = vals[vals.length-2]??last;
  const diff   = prev>0?((last-prev)/prev*100).toFixed(1):0;
  const cls    = diff>0?'up':diff<0?'down':'flat';
  const arrow  = diff>0?'▲':diff<0?'▼':'–';
  const pLabel = period==='yearly'?'years':period==='weekly'?'weeks':'months';

  document.getElementById('sTotal').textContent   = fmt(total);
  document.getElementById('sPeriods').textContent = `across ${vals.length} ${pLabel}`;
  document.getElementById('sAvg').textContent     = fmt(avg);
  document.getElementById('sAvgSub').textContent  = `avg per ${pLabel.slice(0,-1)}`;
  document.getElementById('sVs').innerHTML        = `<span class="sbadge ${cls}">${arrow} ${Math.abs(diff)}%</span>`;
  document.getElementById('sVsSub').textContent   = `Peak: ${rows[peakI].label} (${fmt(vals[peakI])})`;
}

function drawCats(){
  const cats = RAW.cats;
  const keys = Object.keys(cats);
  if(!keys.length){
    document.getElementById('catList').innerHTML='<div class="empty-msg">No category data yet.</div>';
    return;
  }
  const tips={
    'Food':'Consistently high — consider a meal plan',
    'Transportation':'Stable and well within budget',
    'Bills':'Near limit — consider raising allocation',
    'Savings':'Low rate — aim for 50%+ each month',
  };
  document.getElementById('catList').innerHTML = keys.map((cat,ci)=>{
    const months = cats[cat];
    const mons   = Object.keys(months);
    const totals = mons.map(m=>months[m].total);
    const budgets= mons.map(m=>months[m].budget||0);
    const tSpent = totals.reduce((a,b)=>a+b,0);
    const tBudget= budgets.reduce((a,b)=>a+b,0);
    const avgPct = tBudget>0?Math.round(tSpent/tBudget*100):0;
    const maxT   = Math.max(...totals,1);

    const miniHtml = mons.map((m,i)=>{
      const pct  = totals[i]/maxT*100;
      const over = budgets[i]>0&&totals[i]>budgets[i];
      return `<div class="mini-col">
        <div class="mini-bar${over?' over':''}" style="height:${Math.max(pct,5)}%;opacity:${.35+i/mons.length*.65}"></div>
        <div class="mini-lbl">${m}</div>
      </div>`;
    }).join('');

    return `<div class="cat-item">
      <div class="cat-hdr">
        <span class="cat-name">${cat}</span>
        <span class="cat-avg">Avg ${avgPct}% Used</span>
      </div>
      <div class="mini-bars">${miniHtml}</div>
      ${tips[cat]?`<div class="cat-tip">${tips[cat]}</div>`:''}
    </div>`;
  }).join('');
}

function drawBreakdown(){
  const rows = RAW.breakdown;
  if(!rows.length){
    document.getElementById('breakdownBody').innerHTML=
      '<tr><td colspan="6" class="empty-msg">No data yet.</td></tr>';
    return;
  }
  document.getElementById('breakdownBody').innerHTML = rows.map((r,i)=>{
    const spent     = +r.spent;
    const budget    = +r.budget||0;
    const remaining = budget-spent;
    const pct       = budget>0?Math.min(spent/budget*100,100).toFixed(0):0;
    const over      = spent>budget&&budget>0;
    const prevSpent = rows[i+1]?+rows[i+1].spent:spent;
    const vsLast    = prevSpent>0?((spent-prevSpent)/prevSpent*100).toFixed(1):0;
    const cls       = vsLast>0?'up':vsLast<0?'down':'flat';
    const arrow     = vsLast>0?'▲':vsLast<0?'▼':'–';
    return `<tr>
      <td class="td-mo">${r.month_label}</td>
      <td>${budget>0?fmt(budget):'—'}</td>
      <td style="color:${over?'#8b0000':'#1A2828'};font-weight:${over?700:500}">${fmt(spent)}</td>
      <td class="${remaining>=0?'td-grn':'td-red'}">${budget>0?(remaining>=0?fmt(remaining):'−'+fmt(Math.abs(remaining))):'—'}</td>
      <td>
        <div class="prog-row">
          <div class="prog-bg"><div class="prog-fill${over?' over':''}" style="width:${pct}%"></div></div>
          <span class="prog-pct">${budget>0?pct+'%':'—'}</span>
        </div>
      </td>
      <td>${i<rows.length-1
        ?`<span class="vs-badge ${cls}">${arrow} ${Math.abs(vsLast)}%</span>`
        :'<span class="vs-badge-placeholder">—</span>'}</td>
    </tr>`;
  }).join('');
}

function switchPeriod(p,btn){
  document.querySelectorAll('.stab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  drawChart(p);
}

drawChart('monthly');
drawCats();
drawBreakdown();
</script>
<?php $perf->displayStats(); ?>
</body>
</html>