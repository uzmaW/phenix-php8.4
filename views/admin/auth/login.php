@if($error ?? false)
    <div class="alert">{{ $error }}</div>
@endif

<form method="POST" action="/admin/login">
    <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus placeholder="Enter your username">
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter your password">
    </div>
    <button type="submit" class="btn btn-primary">Sign In</button>
</form>
