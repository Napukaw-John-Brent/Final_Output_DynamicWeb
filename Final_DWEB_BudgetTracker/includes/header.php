<header class="page-header">
    <div class="brand">
        <img src="../images/smartbudget-logo.svg" alt="SmartBudget" class="logo-img">
        <span class="brand-name">SmartBudget</span>
    </div>
    
    <nav>
        <a href="../dashboard/index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="../deals/stats.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Stats
        </a>
        <a href="../deals/qr-saver.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'qr-saver.php' ? 'active' : '' ?>">
            <span class="nav-icon">📱</span> QR Codes
        </a>
        <a href="../deals/profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon">👤</span> Profile
        </a>
    </nav>

    <div class="header-actions">
        <button class="btn-icon" onclick="smartBudget.showToast('Help & Support', 'info')" title="Help">
            <span>❓</span>
        </button>
        <button class="btn-icon" onclick="smartBudget.showToast('Notifications', 'info')" title="Notifications">
            <span>🔔</span>
            <?php
            // Add notification count logic here
            $notificationCount = 0;
            if ($notificationCount > 0): ?>
                <span class="notification-badge"><?= $notificationCount ?></span>
            <?php endif; ?>
        </button>
    </div>
</header>

<style>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    padding: 15px 20px;
    background: rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border-subtle);
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 20px;
    transition: all 0.2s;
}

.nav-link.active {
    background: var(--accent);
    color: #1A2828;
}

.nav-icon {
    font-size: 1.1rem;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn-icon {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s;
    position: relative;
}

.btn-icon:hover {
    background: rgba(255,255,255,0.1);
    color: var(--accent);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #f87171;
    color: white;
    font-size: 0.7rem;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}
</style>