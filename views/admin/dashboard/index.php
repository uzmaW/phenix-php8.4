<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo $totalUsers; ?></div>
        <div class="stat-detail">Registered accounts</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Database Tables</div>
        <div class="stat-value"><?php echo $totalTables; ?></div>
        <div class="stat-detail">Active tables</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Memory Usage</div>
        <div class="stat-value"><?php echo $memoryUsage; ?>MB</div>
        <div class="stat-detail">Current process</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">PHP Version</div>
        <div class="stat-value"><?php echo $phpVersion; ?></div>
        <div class="stat-detail">Server runtime</div>
    </div>
</div>

<div class="flex gap-1" style="margin-bottom: 1.5rem;">
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h2>Recent Users</h2>
            <a href="/admin/users" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <?php if ($recentUsers): ?>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach ($recentUsers as $user): ?>
                <tr>
                    <td><?php echo (int)$user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td class="text-sm text-muted"><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><p>No users yet.</p></div>
        <?php endif; ?>
    </div>

    <div style="flex: 1;">
        <div class="card">
            <div class="card-header"><h2>System Info</h2></div>
            <div style="font-size: 0.9rem;">
                <?php foreach ([['Framework', 'Phoenix v11'], ['Database', 'SQLite'], ['Driver', 'PDO'], ['Status', '<span class="badge badge-success">Online</span>']] as [$k, $v]): ?>
                <div class="flex justify-between mb-1" style="padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0;">
                    <span class="text-muted"><?php echo $k; ?></span><span><?php echo $v; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Quick Actions</h2></div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="/admin/users" class="btn btn-primary btn-sm" style="justify-content: center;">Manage Users</a>
                <a href="/admin/payments" class="btn btn-success btn-sm" style="justify-content: center;">Payments</a>
                <a href="/admin/analytics" class="btn btn-ghost btn-sm" style="justify-content: center;">Analytics</a>
                <a href="/" class="btn btn-ghost btn-sm" style="justify-content: center;" target="_blank">View Site</a>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<div class="flex gap-1" style="margin-bottom: 1.5rem;">
    <div class="card" style="flex: 1;">
        <div class="card-header"><h2>User Registrations (Last 7 Days)</h2></div>
        <canvas id="userChart" style="max-height: 250px;"></canvas>
    </div>
    <div class="card" style="flex: 1;">
        <div class="card-header"><h2>Revenue Overview</h2></div>
        <canvas id="revenueChart" style="max-height: 250px;"></canvas>
    </div>
</div>
<div class="flex gap-1">
    <div class="card" style="flex: 1;">
        <div class="card-header"><h2>System Activity</h2></div>
        <canvas id="activityChart" style="max-height: 250px;"></canvas>
    </div>
    <div class="card" style="flex: 1;">
        <div class="card-header"><h2>Traffic Sources</h2></div>
        <canvas id="trafficChart" style="max-height: 250px;"></canvas>
    </div>
</div>

<?php
// Generate last 7 days labels
$labels = [];
$userCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('M d', strtotime("-{$i} days"));
    $labels[] = $date;
    // Simulate user registration count per day
    $userCounts[] = rand(1, max(1, (int)($totalUsers / 7) + rand(-2, 5)));
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDefaults = {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    };

    // User registrations - Line chart
    new Chart(document.getElementById('userChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo json_encode($userCounts); ?>,
                borderColor: '#ff6b35',
                backgroundColor: 'rgba(255,107,53,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff6b35',
                pointRadius: 4,
            }]
        },
        options: chartDefaults
    });

    // Revenue - Bar chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: [<?php for ($i = 0; $i < 7; $i++) echo rand(200, 1500) . ','; ?>],
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } } }
    });

    // System activity - Line chart
    new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: {
            labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '23:59'],
            datasets: [
                {
                    label: 'Requests',
                    data: [<?php for ($i = 0; $i < 7; $i++) echo rand(50, 500) . ','; ?>],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Errors',
                    data: [<?php for ($i = 0; $i < 7; $i++) echo rand(0, 20) . ','; ?>],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.1)',
                    fill: true,
                    tension: 0.4,
                }
            ]
        },
        options: { ...chartDefaults, plugins: { legend: { display: true, position: 'bottom' } } }
    });

    // Traffic sources - Doughnut chart
    new Chart(document.getElementById('trafficChart'), {
        type: 'doughnut',
        data: {
            labels: ['Direct', 'Search', 'Social', 'Referral', 'Email'],
            datasets: [{
                data: [35, 28, 18, 12, 7],
                backgroundColor: ['#ff6b35', '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } } }
        }
    });
});
</script>
