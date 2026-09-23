@extends('layouts.app', ['title' => 'My Profile'])

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
  <div class="card-header">
    <h3 class="card-title">User Profile & Security Settings</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('profile.update') }}">
      @csrf
      <div class="form-group mb-3">
        <label class="form-label">Username (Immutable)</label>
        <input type="text" class="form-control" value="{{ $user->username }}" disabled style="opacity:0.7;">
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Role</label>
        <input type="text" class="form-control" value="{{ ucfirst($user->role) }}" disabled style="opacity:0.7;">
      </div>

      <div class="form-group mb-3">
        <label for="full_name" class="form-label">Full Name</label>
        <input type="text" id="full_name" name="full_name" class="form-control" value="{{ old('full_name', $user->full_name) }}" required>
      </div>

      <hr style="border-color: var(--border); margin: 24px 0;">
      <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 12px; color: var(--text);">Change Password (Optional)</h4>

      <div class="form-group mb-3">
        <label for="password" class="form-label">New Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
      </div>

      <div class="form-group mb-4">
        <label for="password_confirmation" class="form-label">Confirm New Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Re-enter new password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;">
        Save Profile Changes
      </button>
    </form>
  </div>
</div>
@endsection
