@extends('layouts.app', ['title' => 'System Action Audit Log'])

@section('content')
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('audit.index') }}" style="display:flex; gap:12px; align-items:center;">
      <input type="text" name="search" class="form-control" placeholder="Search action, details, user..." value="{{ request('search') }}" style="max-width:300px;">
      <button type="submit" class="btn btn-secondary">Search Audit Log</button>
      @if(request('search'))
        <a href="{{ route('audit.index') }}" class="btn btn-outline">Clear</a>
      @endif
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Table / Record</th>
            <th>Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
          <tr>
            <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
            <td><strong>{{ $log->user->full_name ?? 'System' }}</strong></td>
            <td><span class="badge badge-purple">{{ $log->action }}</span></td>
            <td>
              @if($log->table_name)
                <code>{{ $log->table_name }} #{{ $log->record_id }}</code>
              @else
                <span class="text-dim">—</span>
              @endif
            </td>
            <td>{{ $log->details ?? 'N/A' }}</td>
            <td><code style="font-size:0.8rem;">{{ $log->ip_address }}</code></td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-dim p-4">No audit logs recorded.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $logs->links() }}
    </div>
  </div>
</div>
@endsection
