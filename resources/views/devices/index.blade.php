@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Mobile Devices</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('nav-title', 'VMIS | Mobile Devices')

@section('content')
<section class="content pt-3">
  <div class="container-fluid">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;">
        <h5 class="mb-0 font-weight-bold">Driver app logins</h5>
        <small class="text-muted">Every phone signed in to the VMIS Driver app. Revoke a device that is lost or stolen — it is signed out on its next request. Logins also expire on their own after {{ (int) config('services.mobile.token_days', 90) }} days without use.</small>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>User</th><th>Account</th><th>Device</th><th>Last used</th><th>Expires</th><th>Status</th><th class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            @forelse($tokens as $token)
              @php($expired = $token->expires_at && $token->expires_at->isPast())
              <tr>
                <td class="font-weight-bold">{{ optional($token->user)->fullname ?: '—' }}</td>
                <td>{{ optional($token->user)->badge_number ?: '—' }}</td>
                <td>{{ $token->device_name ?: 'Unnamed device' }}</td>
                <td>{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}</td>
                <td>{{ $token->expires_at ? $token->expires_at->format('M d, Y') : '—' }}</td>
                <td>
                  @if($expired)<span class="badge badge-secondary">Expired</span>
                  @elseif($token->last_used_at && $token->last_used_at->gt(now()->subDays(7)))<span class="badge badge-success">Active</span>
                  @else<span class="badge badge-warning">Idle</span>@endif
                </td>
                <td class="text-right text-nowrap">
                  <form method="POST" action="{{ route('devices.revoke', $token) }}" class="d-inline" onsubmit="return confirm('Revoke this device? It will be signed out.');">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Revoke</button>
                  </form>
                  @if($token->user)
                  <form method="POST" action="{{ route('devices.revoke-user', $token->user) }}" class="d-inline" onsubmit="return confirm('Revoke ALL devices of this user?');">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-light border">Revoke all of user</button>
                  </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No mobile logins yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
@endsection
