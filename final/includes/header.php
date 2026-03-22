<header class="page-header">
    <div class="brand">
        <img src="../images/smartbudget-logo.svg" alt="SmartBudget" class="logo-img">
        <span class="brand-name">SmartBudget</span>
    </div>
    
    <?php
    $currentFile = basename($_SERVER['PHP_SELF']);
    $currentDir  = basename(dirname($_SERVER['PHP_SELF']));
    $isDashboard = ($currentFile === 'index.php' && $currentDir === 'dashboard');
    ?>
    <nav>
        <a href="<?= $currentDir === 'dashboard' ? 'index.php' : '../dashboard/index.php' ?>"
           class="nav-link <?= $isDashboard ? 'active' : '' ?>">
            <span class="nav-icon"></span> Dashboard
        </a>
        <a href="<?= $currentDir === 'deals' ? 'stats.php' : '../deals/stats.php' ?>"
           class="nav-link <?= $currentFile === 'stats.php' ? 'active' : '' ?>">
            <span class="nav-icon"></span> Stats
        </a>
        <a href="<?= $currentDir === 'deals' ? 'qr-saver.php' : '../deals/qr-saver.php' ?>"
           class="nav-link <?= $currentFile === 'qr-saver.php' ? 'active' : '' ?>">
            <span class="nav-icon"></span> QR Codes
        </a>
        <a href="<?= $currentDir === 'deals' ? 'profile.php' : '../deals/profile.php' ?>"
           class="nav-link <?= $currentFile === 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon"></span> Profile
        </a>
    </nav>
</header>
