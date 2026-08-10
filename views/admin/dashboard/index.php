<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value">{{ $totalUsers }}</div>
        <div class="stat-detail">Registered accounts</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Database Tables</div>
        <div class="stat-value">{{ $totalTables }}</div>
        <div class="stat-detail">Active tables</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Memory Usage</div>
        <div class="stat-value">{{ $memoryUsage }}MB</div>
        <div class="stat-detail">Current process</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">PHP Version</div>
        <div class="stat-value">{{ $phpVersion }}</div>
        <div class="stat-detail">Server runtime</div>
    </div>
</div>

<div class="flex gap-1">
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h2>Recent Users</h2>
            <a href="/admin/users" class="btn btn-ghost btn-sm">View All</a>
        </div>
        @if($recentUsers)
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentUsers as $user)
                <tr>
                    <td>{{ $user['id'] }}</td>
                    <td>{{ $user['name'] }}</td>
                    <td>{{ $user['email'] }}</td>
                    <td class="text-sm text-muted">{{ $user['created_at'] ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">
            <p>No users yet.</p>
        </div>
        @endif
    </div>

    <div style="flex: 1;">
        <div class="card">
            <div class="card-header">
                <h2>System Info</h2>
            </div>
            <div style="font-size: 0.9rem;">
                <div class="flex justify-between mb-1" style="padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0;">
                    <span class="text-muted">Framework</span>
                    <span>Phoenix v2</span>
                </div>
                <div class="flex justify-between mb-1" style="padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0;">
                    <span class="text-muted">Database</span>
                    <span>SQLite</span>
                </div>
                <div class="flex justify-between mb-1" style="padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0;">
                    <span class="text-muted">Driver</span>
                    <span>PDO</span>
                </div>
                <div class="flex justify-between" style="padding: 0.5rem 0;">
                    <span class="text-muted">Status</span>
                    <span class="badge badge-success">Online</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Quick Actions</h2>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="/admin/users" class="btn btn-primary btn-sm" style="justify-content: center;">Manage Users</a>
                <a href="/" class="btn btn-ghost btn-sm" style="justify-content: center;" target="_blank">View Site</a>
            </div>
        </div>
    </div>
</div>
