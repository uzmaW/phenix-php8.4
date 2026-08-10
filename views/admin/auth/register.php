@if($error ?? false)
    <div class="alert">{{ $error }}</div>
@endif

<form method="POST" action="/admin/register">
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" required placeholder="Full name">
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required placeholder="user@example.com">
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required minlength="6" placeholder="Min 6 characters">
    </div>
    <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="password_confirmation" required placeholder="Repeat password">
    </div>
    <button type="submit" class="btn btn-primary">Create Account</button>
</form>

<p style="text-align:center; margin-top:1rem; font-size:0.9rem;">
    Already have an account? <a href="/admin/login" style="color:#ff6b35;">Sign in</a>
</p>
