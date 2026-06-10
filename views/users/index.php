<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 style="color: #1a1a2e;">Users</h1>
    <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary">+ New User</button>
</div>

@if($success ?? false)
    <div class="alert alert-success">{{ $success }}</div>
@endif

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users ?? [] as $user)
        <tr>
            <td>{{ $user['id'] ?? '' }}</td>
            <td>{{ $user['name'] ?? '' }}</td>
            <td>{{ $user['email'] ?? '' }}</td>
            <td>
                <a href="/users?id={{ $user['id'] ?? '' }}" class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">View</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@if(empty($users ?? []))
    <div class="card text-center mt-1">
        <p style="color: #666;">No users found.</p>
    </div>
@endif

<div id="createModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 100;">
    <div class="card" style="width: 100%; max-width: 400px;">
        <h2 style="margin-bottom: 1rem; color: #1a1a2e;">Create User</h2>
        <form method="POST" action="/users">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.25rem; font-weight: 500;">Name</label>
                <input type="text" name="name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.25rem; font-weight: 500;">Email</label>
                <input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
