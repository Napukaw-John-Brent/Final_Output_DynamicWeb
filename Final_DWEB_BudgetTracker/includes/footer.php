<footer class="app-footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>SmartBudget</h4>
            <p>Track It Fast, Think Vast.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="../dashboard/index.php">Dashboard</a></li>
                <li><a href="../deals/stats.php">Statistics</a></li>
                <li><a href="../deals/qr-saver.php">QR Codes</a></li>
                <li><a href="../deals/profile.php">Profile</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h4>Support</h4>
            <ul>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> SmartBudget. All rights reserved. | Developed by Your Team</p>
    </div>
</footer>

<style>
.app-footer {
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--border-subtle);
    padding: 40px 0 20px;
    margin-top: 40px;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    padding: 0 20px;
}

.footer-section h4 {
    color: var(--accent);
    margin-bottom: 20px;
    font-size: 1.1rem;
}

.footer-section p {
    color: var(--text-secondary);
    line-height: 1.6;
}

.footer-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-section ul li {
    margin-bottom: 10px;
}

.footer-section ul li a {
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s;
}

.footer-section ul li a:hover {
    color: var(--accent);
}

.footer-bottom {
    text-align: center;
    padding-top: 20px;
    margin-top: 40px;
    border-top: 1px solid var(--border-subtle);
    color: var(--text-muted);
    font-size: 0.9rem;
}
</style>