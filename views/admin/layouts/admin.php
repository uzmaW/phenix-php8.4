<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin' }} - Phoenix Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1a1a1a; background: #f0f2f5; display: flex; min-height: 100vh; }

        .sidebar { width: 240px; background: #1a1a2e; color: #fff; padding: 0; flex-shrink: 0; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; }
        .sidebar-brand { padding: 1.25rem 1.5rem; font-size: 1.25rem; font-weight: 700; color: #ff6b35; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 0.5rem; }
        .sidebar-brand svg { width: 24px; height: 24px; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #b0b0b0; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active { background: rgba(255,107,53,0.15); color: #ff6b35; border-right: 3px solid #ff6b35; }
        .sidebar-nav a svg { width: 18px; height: 18px; flex-shrink: 0; }
        .sidebar-section { padding: 0.5rem 1.5rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #666; margin-top: 0.5rem; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .sidebar-footer a { color: #b0b0b0; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; }
        .sidebar-footer a:hover { color: #ff6b35; }

        .main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; }
        .topbar { background: #fff; padding: 0.75rem 2rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .topbar h1 { font-size: 1.25rem; color: #1a1a2e; font-weight: 600; }
        .topbar-user { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: #555; }
        .topbar-avatar { width: 32px; height: 32px; background: #ff6b35; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; }

        .content { padding: 2rem; flex: 1; }

        .card { background: #fff; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #eee; }
        .card-header h2 { font-size: 1rem; color: #1a1a2e; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 4px solid #ff6b35; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.purple { border-left-color: #8b5cf6; }
        .stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: #1a1a2e; }
        .stat-detail { font-size: 0.8rem; color: #999; margin-top: 0.25rem; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        tr:hover { background: #f8f9fa; }
        tr:last-child td { border-bottom: none; }

        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.85rem; transition: all 0.2s; }
        .btn-primary { background: #ff6b35; color: #fff; }
        .btn-primary:hover { background: #e55a2b; }
        .btn-sm { padding: 0.3rem 0.75rem; font-size: 0.8rem; }
        .btn-ghost { background: transparent; color: #666; border: 1px solid #ddd; }
        .btn-ghost:hover { background: #f5f5f5; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.9rem; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #ff6b35; box-shadow: 0 0 0 3px rgba(255,107,53,0.1); }

        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 100; }
        .modal { background: #fff; border-radius: 12px; width: 100%; max-width: 450px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.1rem; color: #1a1a2e; }
        .modal-close { background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #eee; display: flex; gap: 0.5rem; justify-content: flex-end; }

        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: #888; }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 1rem; color: #ccc; }

        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: 1rem; }
        .mt-1 { margin-top: 1rem; }
        .mb-1 { margin-bottom: 1rem; }
        .text-sm { font-size: 0.85rem; }
        .text-muted { color: #888; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            Phoenix Admin
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="/admin" class="{{ ($currentPage ?? '') === 'dashboard' ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <div class="sidebar-section">Management</div>
            <a href="/admin/users" class="{{ ($currentPage ?? '') === 'users' ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Users
            </a>
            <a href="/admin/settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/" target="_blank" style="margin-bottom: 0.5rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                View Site
            </a>
            <a href="/admin/logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1>{{ $title ?? 'Dashboard' }}</h1>
            <div class="topbar-user">
                <span>{{ $_SESSION['admin_user'] ?? 'Admin' }}</span>
                <div class="topbar-avatar">A</div>
            </div>
        </header>
        <div class="content">
            {!! $content ?? '' !!}
        </div>
    </div>
</body>
</html>
