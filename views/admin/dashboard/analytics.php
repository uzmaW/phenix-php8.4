<?php
// Generate date label arrays
$labels7  = [];
$labels30 = [];
for ($i = 6;  $i >= 0; $i--) { $labels7[]  = date('M d', strtotime("-{$i} days")); }
for ($i = 29; $i >= 0; $i--) { $labels30[] = date('M d', strtotime("-{$i} days")); }

// Simulate daily registration data
$regData = [];
for ($i = 0; $i < 30; $i++) {
    $regData[] = max(0, (int)($totalUsers / 30) + rand(-2, 5));
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.5rem; color: #1a1a2e; margin: 0;">Analytics Dashboard</h1>
    <p style="color: #888; margin-top: 0.25rem;">Platform performance and user activity insights</p>
</div>

<!-- KPI cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo (int)$totalUsers; ?></div>
        <div class="stat-detail">All time</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Active Today</div>
        <div class="stat-value"><?php echo rand(1, max(1, (int)($totalUsers * 0.3) + 1)); ?></div>
        <div class="stat-detail">Unique sessions</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Uptime</div>
        <div class="stat-value">99.9%</div>
        <div class="stat-detail">Last 30 days</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">Avg Response</div>
        <div class="stat-value"><?php echo rand(20, 80); ?>ms</div>
        <div class="stat-detail">API latency</div>
    </div>
</div>

<!-- Row 1: User Growth (wide) + Segments (narrow) -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="card">
        <div class="card-header"><h2>User Growth (30 Days)</h2></div>
        <canvas id="growthChart" style="max-height: 280px;"></canvas>
    </div>
    <div class="card">
        <div class="card-header"><h2>User Segments</h2></div>
        <canvas id="segmentChart" style="max-height: 280px;"></canvas>
    </div>
</div>

<!-- Row 2: Revenue (stacked bar) + Requests (multi-line) -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="card">
        <div class="card-header"><h2>Revenue by Gateway — Last 7 Days</h2></div>
        <canvas id="revChart" style="max-height: 260px;"></canvas>
    </div>
    <div class="card">
        <div class="card-header"><h2>API Requests — Last 7 Days</h2></div>
        <canvas id="reqChart" style="max-height: 260px;"></canvas>
    </div>
</div>

<!-- Row 3: Radar -->
<div class="card">
    <div class="card-header"><h2>System Performance Metrics</h2></div>
    <div style="max-width: 500px; margin: 0 auto;">
        <canvas id="radarChart" style="max-height: 360px;"></canvas>
    </div>
</div>

<script>
const labels7  = <?php echo json_encode($labels7);  ?>;
const labels30 = <?php echo json_encode($labels30); ?>;
const growthRaw = <?php echo json_encode($regData); ?>;
const gridColor = 'rgba(0,0,0,0.05)';

document.addEventListener('DOMContentLoaded', function () {

    // --- User growth (area line) ---
    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: labels30,
            datasets: [{
                label: 'New Users',
                data: growthRaw,
                borderColor: '#ff6b35',
                backgroundColor: 'rgba(255,107,53,0.12)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
                pointBackgroundColor: '#ff6b35',
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }
            }
        }
    });

    // --- User segments (doughnut) ---
    new Chart(document.getElementById('segmentChart'), {
        type: 'doughnut',
        data: {
            labels: ['Admin', 'Premium', 'Standard', 'Guest'],
            datasets: [{
                data: [5, 20, 60, 15],
                backgroundColor: ['#ff6b35', '#8b5cf6', '#3b82f6', '#10b981'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12 } } }
        }
    });

    // --- Revenue stacked bar ---
    new Chart(document.getElementById('revChart'), {
        type: 'bar',
        data: {
            labels: labels7,
            datasets: [
                {
                    label: 'Stripe',
                    data: Array.from({ length: 7 }, () => Math.floor(Math.random() * 800 + 200)),
                    backgroundColor: 'rgba(99,91,255,0.85)',
                    borderRadius: 4,
                },
                {
                    label: 'PayPal',
                    data: Array.from({ length: 7 }, () => Math.floor(Math.random() * 400 + 100)),
                    backgroundColor: 'rgba(0,48,135,0.85)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, stacked: true, grid: { color: gridColor } },
                x: { stacked: true, grid: { display: false } }
            }
        }
    });

    // --- API requests multi-line ---
    new Chart(document.getElementById('reqChart'), {
        type: 'line',
        data: {
            labels: labels7,
            datasets: [
                {
                    label: 'Success',
                    data: Array.from({ length: 7 }, () => Math.floor(Math.random() * 500 + 300)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Errors',
                    data: Array.from({ length: 7 }, () => Math.floor(Math.random() * 30)),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.1)',
                    fill: true,
                    tension: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor } },
                x: { grid: { display: false } }
            }
        }
    });

    // --- Radar performance ---
    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels: ['Response Time', 'Throughput', 'Availability', 'Security Score', 'Cache Hit Rate', 'Error Rate'],
            datasets: [
                {
                    label: 'Current',
                    data: [85, 78, 99, 92, 88, 97],
                    borderColor: '#ff6b35',
                    backgroundColor: 'rgba(255,107,53,0.15)',
                    pointBackgroundColor: '#ff6b35',
                },
                {
                    label: 'Target',
                    data: [90, 85, 99.9, 95, 95, 99],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    pointBackgroundColor: '#3b82f6',
                    borderDash: [5, 5],
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } }
        }
    });
});
</script>
