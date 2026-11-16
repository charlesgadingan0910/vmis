@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Approvals</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('nav-title', 'VMIS | Approvals')

@section('content')
@php
  $statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
  $who = fn ($u) => $u ? ($u->fullname ?: trim($u->firstname . ' ' . $u->lastname)) : '—';
@endphp
<section class="content pt-3">
  <div class="container-fluid">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;">
        <h5 class="mb-0 font-weight-bold">Waiting for your approval <span class="badge badge-warning">{{ $waiting->count() }}</span></h5>
        <small class="text-muted">Nothing changes until you approve. Approving applies the change exactly as if you had made it yourself.</small>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table mb-0">
          <thead><tr><th>Request</th><th>Vehicle</th><th>Requested by</th><th>When</th><th class="text-right">Decision</th></tr></thead>
          <tbody>
          @forelse($waiting as $r)
            <tr>
              <td class="font-weight-bold">{{ $r->summary }} <div><span class="badge badge-light border">{{ ucfirst($r->category) }}</span></div></td>
              <td>{{ optional($r->vehicle)->plate_number ? strtoupper($r->vehicle->plate_number) : '—' }}</td>
              <td>{{ $who($r->requester) }}<div class="small text-muted">{{ ucwords(strtolower($r->requester_role)) }}</div></td>
              <td>{{ $r->created_at->diffForHumans() }}</td>
              <td class="text-right">
                <form method="POST" action="{{ route('approvals.approve', $r) }}" class="d-inline">@csrf
                  <button class="btn btn-sm btn-success">Approve</button>
                </form>
                <form method="POST" action="{{ route('approvals.reject', $r) }}" class="d-inline-flex align-items-center ml-1">@csrf
                  <input type="text" name="note" maxlength="255" class="form-control form-control-sm mr-1" placeholder="Reason (optional)" style="width:150px;">
                  <button class="btn btn-sm btn-outline-danger">Reject</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Nothing is waiting for your approval.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;">
        <h5 class="mb-0 font-weight-bold">My requests</h5>
        <small class="text-muted">Changes you asked for. A pending request can be cancelled.</small>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table mb-0">
          <thead><tr><th>Request</th><th>Vehicle</th><th>Status</th><th>Decided by</th><th>When</th><th></th></tr></thead>
          <tbody>
          @forelse($mine as $r)
            <tr>
              <td>{{ $r->summary }}</td>
              <td>{{ optional($r->vehicle)->plate_number ? strtoupper($r->vehicle->plate_number) : '—' }}</td>
              <td><span class="badge badge-{{ $statusBadge[$r->status] ?? 'light' }}">{{ ucfirst($r->status) }}</span>
                @if($r->decision_note)<div class="small text-muted">{{ $r->decision_note }}</div>@endif</td>
              <td>{{ $r->status === 'pending' ? '—' : $who($r->decider) }}</td>
              <td>{{ $r->created_at->diffForHumans() }}</td>
              <td class="text-right">
                @if($r->isPending())
                  <form method="POST" action="{{ route('approvals.cancel', $r) }}" class="d-inline">@csrf
                    <button class="btn btn-sm btn-light border">Cancel</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">You have not requested any changes.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;">
        <h5 class="mb-0 font-weight-bold">Recent decisions</h5>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table mb-0">
          <thead><tr><th>Request</th><th>Vehicle</th><th>Requested by</th><th>Result</th><th>Decided by</th><th>When</th></tr></thead>
          <tbody>
          @forelse($history as $r)
            <tr>
              <td>{{ $r->summary }}</td>
              <td>{{ optional($r->vehicle)->plate_number ? strtoupper($r->vehicle->plate_number) : '—' }}</td>
              <td>{{ $who($r->requester) }}</td>
              <td><span class="badge badge-{{ $statusBadge[$r->status] ?? 'light' }}">{{ ucfirst($r->status) }}</span>
                @if($r->decision_note)<div class="small text-muted">{{ $r->decision_note }}</div>@endif</td>
              <td>{{ $who($r->decider) }}</td>
              <td>{{ optional($r->decided_at)->diffForHumans() }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No decisions yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
@endsection
