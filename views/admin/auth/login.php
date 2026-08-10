@if($error ?? false)
    <div class="alert">{{ $error }}</div>
@endif

<form method="POST" action="/admin/login">
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required autofocus placeholder="user@example.com">
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter your password">
    </div>
    <button type="submit" class="btn btn-primary">Sign In</button>
</form>

<p style="text-align:center; margin-top:1rem; font-size:0.9rem;">
    Don't have an account? <a href="/admin/register" style="color:#ff6b35;">Register</a>
</p>
