<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Login' }} - Phoenix Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-container { width: 100%; max-width: 400px; padding: 2rem; }
        .login-card { background: #fff; border-radius: 12px; padding: 2.5rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .login-brand { text-align: center; margin-bottom: 2rem; }
        .login-brand h1 { font-size: 1.75rem; color: #1a1a2e; margin-bottom: 0.25rem; }
        .login-brand p { color: #888; font-size: 0.9rem; }
        .login-brand svg { width: 48px; height: 48px; color: #ff6b35; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; color: #333; }
        .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-group input:focus { outline: none; border-color: #ff6b35; box-shadow: 0 0 0 3px rgba(255,107,53,0.15); }
        .btn { display: block; width: 100%; padding: 0.75rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #ff6b35; color: #fff; }
        .btn-primary:hover { background: #e55a2b; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-size: 0.9rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .login-footer { text-align: center; margin-top: 1.5rem; color: #666; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-brand">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                <h1>Phoenix Admin</h1>
                <p>Sign in to your dashboard</p>
            </div>
            {!! $content ?? '' !!}
            <div class="login-footer">
                Phoenix Framework &mdash; Rust-inspired PHP
            </div>
        </div>
    </div>
</body>
</html>
