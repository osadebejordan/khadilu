@extends('layouts.app', ['title' => 'System User Accounts'])

@section('content')
<div class="card mb-4">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h3 class="card-title">Staff & Admin User Accounts</h3>
    <button class="btn btn-primary" onclick="document.getElementById('userModal').style.display='flex'">
      + Create User Account
    </button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created At</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $u)
          <tr>
            <td><strong>{{ $u->full_name }}</strong></td>
            <td><code>{{ $u->username }}</code></td>
            <td>
              @if($u->role === 'admin')
                <span class="badge badge-purple">Admin</span>
              @elseif($u->role === 'sub-admin')
                <span class="badge badge-blue">Sub-Admin</span>
              @else
                <span class="badge badge-outline">Staff</span>
              @endif
            </td>
            <td>{{ $u->created_at->format('d M Y') }}</td>
            <td style="text-align:right;">
              @if($u->id !== auth()->id())
                <form action="{{ route('users.destroy', $u->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this user account?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              @else
                <span class="text-dim" style="font-size:0.8rem;">(Logged in)</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Create User -->
<div id="userModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Create New User Account</h3>
      <button onclick="document.getElementById('userModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="form-group mb-3">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Doe">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Username *</label>
          <input type="text" name="username" class="form-control" required placeholder="johndoe">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters">
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Role Access *</label>
          <select name="role" class="form-control" required>
            <option value="staff" selected>Staff (Sales & POS Access)</option>
            <option value="sub-admin">Sub-Admin (Managerial Access)</option>
            <option value="admin">Administrator (Full Access)</option>
          </select>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('userModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
