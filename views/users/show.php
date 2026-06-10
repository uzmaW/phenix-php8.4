@if($user ?? null)
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
        <div>
            <h1 style="color: #1a1a2e; margin-bottom: 0.25rem;">{{ $user['name'] ?? 'Unknown' }}</h1>
            <p style="color: #666;">{{ $user['email'] ?? '' }}</p>
        </div>
        <a href="/users" class="btn btn-secondary">Back to Users</a>
    </div>

    <div class="grid grid-2 mt-1">
        <div class="card" style="background: #f8f9fa;">
            <h3 style="color: #1a1a2e; margin-bottom: 0.5rem;">Profile Details</h3>
            <table style="box-shadow: none;">
                <tr>
                    <td style="font-weight: 600; width: 120px;">ID</td>
                    <td>{{ $user['id'] ?? '' }}</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Name</td>
                    <td>{{ $user['name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Email</td>
                    <td>{{ $user['email'] ?? '' }}</td>
                </tr>
            </table>
        </div>

        <div class="card" style="background: #f8f9fa;">
            <h3 style="color: #1a1a2e; margin-bottom: 0.5rem;">Actions</h3>
            <div class="grid" style="gap: 0.5rem;">
                <a href="/users?id={{ $user['id'] ?? '' }}" class="btn btn-primary" style="text-align: center;">Edit User</a>
                <button class="btn btn-danger" style="width: 100%;">Delete User</button>
            </div>
        </div>
    </div>
</div>
@else
<div class="card text-center">
    <h2 style="color: #1a1a2e; margin-bottom: 0.5rem;">User Not Found</h2>
    <p style="color: #666; margin-bottom: 1rem;">The requested user could not be found.</p>
    <a href="/users" class="btn btn-primary">Back to Users</a>
</div>
@endif
