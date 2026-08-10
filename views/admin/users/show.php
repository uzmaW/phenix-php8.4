@if($success ?? false)
    <div class="alert alert-success">{{ $success }}</div>
@endif

@if($error ?? false)
    <div class="alert alert-error">{{ $error }}</div>
@endif

<div class="flex justify-between items-center mb-1">
    <a href="/admin/users" class="btn btn-ghost btn-sm">&larr; Back to Users</a>
</div>

<div class="flex gap-1">
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h2>User Profile</h2>
            <button onclick="document.getElementById('editModal').style.display='flex'" class="btn btn-primary btn-sm">Edit User</button>
        </div>

        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.75rem; font-size: 0.9rem;">
            <div class="text-muted" style="font-weight: 500;">ID</div>
            <div>{{ $user['id'] }}</div>

            <div class="text-muted" style="font-weight: 500;">Name</div>
            <div><strong>{{ $user['name'] }}</strong></div>

            <div class="text-muted" style="font-weight: 500;">Email</div>
            <div>{{ $user['email'] }}</div>

            <div class="text-muted" style="font-weight: 500;">Created</div>
            <div>{{ $user['created_at'] ?? 'N/A' }}</div>

            <div class="text-muted" style="font-weight: 500;">Updated</div>
            <div>{{ $user['updated_at'] ?? 'N/A' }}</div>
        </div>
    </div>

    <div style="flex: 1;">
        <div class="card">
            <div class="card-header">
                <h2>Actions</h2>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <button onclick="document.getElementById('editModal').style.display='flex'" class="btn btn-primary btn-sm" style="justify-content: center;">Edit User</button>
                <form method="POST" action="/admin/users/delete" onsubmit="return confirm('Are you sure you want to delete this user?')">
                    <input type="hidden" name="id" value="{{ $user['id'] }}">
                    <button type="submit" class="btn btn-danger btn-sm" style="width: 100%; justify-content: center;">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="/admin/users/update">
            <div class="modal-body">
                <input type="hidden" name="id" value="{{ $user['id'] }}">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="{{ $user['name'] }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ $user['email'] }}" required>
                </div>
                <div class="form-group">
                    <label>New Password <span class="text-muted text-sm">(leave blank to keep current)</span></label>
                    <input type="password" name="password" minlength="6" placeholder="Min 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
