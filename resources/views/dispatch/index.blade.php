@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Dispatch</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('nav-title', 'VMIS | Dispatch')

@section('content')
@php
  $who = fn ($u) => $u ? ($u->fullname ?: trim($u->firstname . ' ' . $u->lastname)) : '—';
  $dname = fn ($d) => $d ? trim($d->firstname . ' ' . $d->lastname) : 'No driver yet';
  $statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
  $prev = $month->copy()->subMonth()->format('Y-m');
  $next = $month->copy()->addMonth()->format('Y-m');
  $q = fn ($m) => route('dispatch.index', array_filter(['month' => $m, 'vehicle_id' => $vehicleFilter]));
@endphp
<section class="content pt-3">
  <div class="container-fluid">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if($waiting->count())
    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;">
        <h5 class="mb-0 font-weight-bold">Waiting for your approval <span class="badge badge-warning">{{ $waiting->count() }}</span></h5>
        <small class="text-muted">You can change the vehicle or driver when approving. A double-booked vehicle or driver cannot be approved.</small>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table mb-0">
          <thead><tr><th>Trip</th><th>When</th><th>Requested by</th><th class="text-right">Decision</th></tr></thead>
          <tbody>
          @foreach($waiting as $r)
            <tr>
              <td><b>{{ $r->purpose }}</b><div class="small text-muted">{{ $r->origin ? $r->origin . ' → ' : '' }}{{ $r->destination }}{{ $r->passengers ? ' · ' . $r->passengers : '' }}</div></td>
              <td>{{ $r->start_at->format('M d, Y h:i A') }}<div class="small text-muted">to {{ $r->end_at->format('M d, Y h:i A') }}</div></td>
              <td>{{ $who($r->requester) }}<div class="small text-muted">{{ ucwords(strtolower($r->requester_role)) }}</div></td>
              <td class="text-right">
                <form method="POST" action="{{ route('dispatch.approve', $r) }}" class="form-inline justify-content-end mb-1">@csrf
                  <select name="vehicle_id" class="form-control form-control-sm mr-1" style="max-width:150px;">
                    @foreach($allVehicles as $v)<option value="{{ $v->id }}" @selected($v->id == $r->vehicle_id)>{{ strtoupper($v->plate_number) }}</option>@endforeach
                  </select>
                  <select name="driver_id" class="form-control form-control-sm mr-1" style="max-width:170px;">
                    <option value="">No driver yet</option>
                    @foreach($drivers as $d)<option value="{{ $d->id }}" @selected($d->id == $r->driver_id)>{{ $dname($d) }}</option>@endforeach
                  </select>
                  <button class="btn btn-sm btn-success">Approve</button>
                </form>
                <form method="POST" action="{{ route('dispatch.reject', $r) }}" class="form-inline justify-content-end">@csrf
                  <input type="text" name="note" maxlength="255" class="form-control form-control-sm mr-1" placeholder="Reason (optional)" style="width:180px;">
                  <button class="btn btn-sm btn-outline-danger">Reject</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between" style="border-radius:16px 16px 0 0;">
        <div>
          <a href="{{ $q($prev) }}" class="btn btn-sm btn-outline-secondary">‹</a>
          <span class="font-weight-bold mx-2" style="font-size:1.1rem;">{{ $month->format('F Y') }}</span>
          <a href="{{ $q($next) }}" class="btn btn-sm btn-outline-secondary">›</a>
          <a href="{{ route('dispatch.index', array_filter(['vehicle_id' => $vehicleFilter])) }}" class="btn btn-sm btn-link">Today</a>
        </div>
        <form method="GET" class="form-inline">
          <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
          <select name="vehicle_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <option value="">All vehicles</option>
            @foreach($allVehicles as $v)<option value="{{ $v->id }}" @selected($vehicleFilter == $v->id)>{{ strtoupper($v->plate_number) }}</option>@endforeach
          </select>
          @if($canRequest)<button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#requestModal"><i class="fas fa-plus"></i> Request a vehicle</button>@endif
        </form>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered mb-0" style="table-layout:fixed;">
          <thead><tr>@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<th class="text-center small">{{ $d }}</th>@endforeach</tr></thead>
          <tbody>
          @for($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
            @if($day->dayOfWeek === 0)<tr>@endif
            @php
              $dayStart = $day->copy()->startOfDay(); $dayEnd = $day->copy()->endOfDay();
              $todays = $events->filter(fn ($e) => $e->start_at->lte($dayEnd) && $e->end_at->gt($dayStart));
            @endphp
            <td style="height:96px;vertical-align:top;{{ $day->month !== $month->month ? 'background:#f8f9fa;' : '' }}">
              <div class="small {{ $day->month !== $month->month ? 'text-muted' : 'font-weight-bold' }}">{{ $day->day }}</div>
              @foreach($todays as $e)
                <div class="small mb-1 px-1 rounded text-white {{ $e->status === 'approved' ? 'bg-success' : 'bg-warning' }}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"
                     title="{{ strtoupper(optional($e->vehicle)->plate_number) }} · {{ $e->purpose }} · {{ $e->start_at->format('M d h:i A') }}–{{ $e->end_at->format('M d h:i A') }} · {{ $dname($e->driver) }} · {{ ucfirst($e->status) }}">
                  {{ strtoupper(optional($e->vehicle)->plate_number) }} {{ $e->purpose }}
                </div>
              @endforeach
            </td>
            @if($day->dayOfWeek === 6)</tr>@endif
          @endfor
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white small text-muted"><span class="badge badge-success">&nbsp;</span> Approved booking &nbsp; <span class="badge badge-warning">&nbsp;</span> Waiting for approval (does not block the vehicle until approved)</div>
    </div>

    @if($canRequest)
    <div class="card" style="border-radius:16px;">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0;"><h5 class="mb-0 font-weight-bold">My requests</h5></div>
      <div class="card-body table-responsive p-0">
        <table class="table mb-0">
          <thead><tr><th>Vehicle</th><th>Trip</th><th>When</th><th>Status</th><th></th></tr></thead>
          <tbody>
          @forelse($mine as $r)
            <tr>
              <td>{{ strtoupper(optional($r->vehicle)->plate_number) }}<div class="small text-muted">{{ $dname($r->driver) }}</div></td>
              <td>{{ $r->purpose }}<div class="small text-muted">{{ $r->destination }}</div></td>
              <td>{{ $r->start_at->format('M d, Y h:i A') }}<div class="small text-muted">to {{ $r->end_at->format('M d, Y h:i A') }}</div></td>
              <td><span class="badge badge-{{ $statusBadge[$r->status] ?? 'light' }}">{{ ucfirst($r->status) }}</span>@if($r->decision_note)<div class="small text-muted">{{ $r->decision_note }}</div>@endif</td>
              <td class="text-right">@if(in_array($r->status, ['pending','approved']))
                <form method="POST" action="{{ route('dispatch.cancel', $r) }}" onsubmit="return confirm('Cancel this request?')">@csrf<button class="btn btn-sm btn-outline-secondary">Cancel</button></form>@endif</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">You have not requested a vehicle yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @endif
  </div>
</section>

@if($canRequest)
<div class="modal fade" id="requestModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('dispatch.store') }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Request a vehicle</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6"><label>Vehicle *</label>
            <select name="vehicle_id" class="form-control" required>
              <option value="">Select…</option>
              @foreach($vehicles as $v)<option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>{{ strtoupper($v->plate_number) }} — {{ $v->make }} {{ $v->model }}</option>@endforeach
            </select></div>
          <div class="form-group col-md-6"><label>Driver (optional)</label>
            <select name="driver_id" class="form-control"><option value="">To be assigned</option>
              @foreach($drivers as $d)<option value="{{ $d->id }}" @selected(old('driver_id') == $d->id)>{{ $dname($d) }}</option>@endforeach
            </select></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>From *</label><input type="datetime-local" name="start_at" class="form-control" value="{{ old('start_at') }}" required></div>
          <div class="form-group col-md-6"><label>Until *</label><input type="datetime-local" name="end_at" class="form-control" value="{{ old('end_at') }}" required></div>
        </div>
        <div class="form-group"><label>Purpose *</label><input type="text" name="purpose" maxlength="255" class="form-control" value="{{ old('purpose') }}" required></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>Origin</label><input type="text" name="origin" maxlength="150" class="form-control" value="{{ old('origin') }}"></div>
          <div class="form-group col-md-6"><label>Destination *</label><input type="text" name="destination" maxlength="150" class="form-control" value="{{ old('destination') }}" required></div>
        </div>
        <div class="form-group"><label>Passengers</label><input type="text" name="passengers" maxlength="255" class="form-control" value="{{ old('passengers') }}"></div>
        <small class="text-muted">A vehicle or driver already booked for overlapping times cannot be requested.</small>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button class="btn btn-primary">Submit request</button></div>
    </form>
  </div></div>
</div>
@if($errors->any() || session('error'))<script>window.addEventListener('load',function(){ if(window.jQuery){ jQuery('#requestModal').modal('show'); } });</script>@endif
@endif
@endsection
