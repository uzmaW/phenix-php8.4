@if($success ?? false)
    <div class="alert alert-success">{{ $success }}</div>
@endif

@if($error ?? false)
    <div class="alert alert-error">{{ $error }}</div>
@endif

<div class="card">
    <div class="card-header">
        <h2>All Users</h2>
        <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary btn-sm">+ New User</button>
    </div>

    @if($users)
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Created</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user['id'] }}</td>
                <td><strong>{{ $user['name'] }}</strong></td>
                <td>{{ $user['email'] }}</td>
                <td class="text-sm text-muted">{{ $user['created_at'] ?? 'N/A' }}</td>
                <td style="text-align: right;">
                    <button onclick="openEditModal({{ $user['id'] }}, '{{ $user['name'] }}', '{{ $user['email'] }}')" class="btn btn-ghost btn-sm">Edit</button>
                    <form method="POST" action="/admin/users/delete" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                        <input type="hidden" name="id" value="{{ $user['id'] }}">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <p>No users found. Create your first user!</p>
    </div>
    @endif
</div>

<div id="createModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>Create User</h3>
            <button class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="/admin/users">
            <div class="modal-body">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required placeholder="Full name">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="user@example.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
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
                <input type="hidden" name="id" id="editUserId">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="editUserName" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="editUserEmail" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, email) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserName').value = name;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
