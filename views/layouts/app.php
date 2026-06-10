<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Phoenix Framework' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1a1a1a; background: #f8f9fa; }
        .container { max-width: 960px; margin: 0 auto; padding: 0 1rem; }
        header { background: #1a1a2e; color: #fff; padding: 1rem 0; }
        header nav { display: flex; gap: 1.5rem; align-items: center; }
        header nav a { color: #e0e0e0; text-decoration: none; font-weight: 500; }
        header nav a:hover, header nav a.active { color: #ff6b35; }
        .brand { font-size: 1.25rem; font-weight: 700; color: #ff6b35; }
        main { padding: 2rem 0; min-height: 60vh; }
        footer { background: #1a1a2e; color: #aaa; text-align: center; padding: 1rem 0; font-size: 0.875rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #ff6b35; color: #fff; }
        .btn-primary:hover { background: #e55a2b; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #1a1a2e; color: #fff; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .grid { display: grid; gap: 1rem; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .text-center { text-align: center; }
        .mt-1 { margin-top: 1rem; }
        .mb-1 { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="/" class="brand">Phoenix</a>
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/users">Users</a>
            </nav>
        </div>
    </header>
    <main>
        <div class="container">
            {{ $content ?? '' }}
        </div>
    </main>
    <footer>
        <div class="container">
            Phoenix Framework v2 &mdash; Rust-inspired PHP
        </div>
    </footer>
</body>
</html>
