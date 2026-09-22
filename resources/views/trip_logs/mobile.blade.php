@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | My Trip Logs</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  :root{ --vmis-navy-900:#0f172a; --vmis-navy-800:#1e293b; --vmis-blue:#3b82f6; --vmis-blue-dark:#1d4ed8; --vmis-green:#16a34a; }

  .trip-page-wrap{max-width:480px;margin:0 auto;padding-bottom:env(safe-area-inset-bottom, 0px);}

  /* ---------- Hero ---------- */
  .trip-hero{
    background:linear-gradient(135deg, var(--vmis-navy-800), var(--vmis-navy-900));
    border-radius:20px; padding:22px 22px 20px; color:#fff; position:relative; overflow:hidden;
    margin-bottom:16px; box-shadow:0 10px 30px rgba(15,23,42,0.18);
  }
  .trip-hero::after{content:'';position:absolute;top:-40%;right:-15%;width:65%;height:65%;background:radial-gradient(circle, rgba(59,130,246,0.28), transparent 65%);}
  .trip-hero-top{display:flex; align-items:center; gap:12px; position:relative; z-index:1;}
  .trip-hero-avatar{
    width:46px;height:46px;border-radius:12px;background:rgba(59,130,246,0.18);border:1px solid rgba(59,130,246,0.35);
    display:flex;align-items:center;justify-content:center;flex:none;font-weight:800;font-size:16px;color:#93c5fd;
  }
  .trip-hero h5{margin:0;font-weight:800;font-size:18px;letter-spacing:-.2px;}
  .trip-hero p{margin:2px 0 0;color:#94a3b8;font-size:12.5px;}
  .trip-hero-stats{display:flex; gap:10px; position:relative; z-index:1; margin-top:16px;}
  .trip-hero-stat{flex:1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.09); border-radius:12px; padding:10px 12px;}
  .trip-hero-stat .num{font-size:19px; font-weight:800; line-height:1.1;}
  .trip-hero-stat .lbl{font-size:10.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; margin-top:2px;}

  /* ---------- Notice / empty-state cards ---------- */
  .trip-notice-card{
    background:#fff; border-radius:16px; border:1px solid #eef1f6; box-shadow:0 1px 3px rgba(15,23,42,0.04);
    padding:22px 20px; text-align:center; margin-bottom:16px;
  }
  .trip-notice-card svg{width:34px;height:34px;color:#cbd5e1;margin-bottom:10px;}
  .trip-notice-card h6{font-weight:800;color:#334155;margin-bottom:6px;font-size:14.5px;}
  .trip-notice-card p{color:#94a3b8;font-size:12.5px;margin:0;line-height:1.5;}

  /* ---------- Form card ---------- */
  .trip-form-card{background:#fff;border-radius:16px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:18px 18px 20px;margin-bottom:16px;}
  .trip-form-card .card-heading{display:flex;align-items:center;gap:9px;margin-bottom:16px;}
  .trip-form-card .card-heading .icon-badge{width:30px;height:30px;border-radius:9px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;flex:none;}
  .trip-form-card .card-heading .icon-badge svg{width:16px;height:16px;color:var(--vmis-blue-dark);}
  .trip-form-card .card-heading strong{font-size:14.5px;color:#0f172a;font-weight:800;}

  .trip-form-card label{font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;}
  .trip-form-card .form-control{
    border:1.5px solid #e2e8f0; border-radius:11px; height:46px; padding:0 14px;
    font-size:14px; color:#0f172a; background:#f8fafc;
  }
  .trip-form-card textarea.form-control{height:auto; padding:12px 14px; min-height:70px;}
  .trip-form-card .form-control:focus{outline:none; border-color:var(--vmis-blue); background:#fff; box-shadow:0 0 0 3.5px rgba(59,130,246,0.12);}
  .trip-form-row{display:flex; gap:10px;}
  .trip-form-row > div{flex:1; min-width:0;}
  .trip-form-group{margin-bottom:14px;}

  .btn-log-trip{
    width:100%; height:50px; border-radius:11px; border:none;
    background:linear-gradient(135deg, var(--vmis-blue), var(--vmis-blue-dark)); color:#fff; font-weight:700; font-size:14.5px;
    display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 10px rgba(59,130,246,0.3);
    transition:transform .15s ease;
  }
  .btn-log-trip:active{transform:scale(0.98);}
  .btn-log-trip:disabled{opacity:.65;}
  .form-submit-status{margin-top:10px;font-size:12.5px;font-weight:600;display:none;text-align:center;}

  /* ---------- History ---------- */
  .trip-history-heading{display:flex;align-items:center;justify-content:space-between;margin:4px 2px 12px;}
  .trip-history-heading strong{font-size:14.5px;color:#0f172a;font-weight:800;}
  .trip-history-heading span{font-size:11.5px;color:#94a3b8;font-weight:600;}

  .trip-card{background:#fff;border-radius:14px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:14px 16px;margin-bottom:10px;}
  .trip-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;}
  .trip-card-date{font-weight:800;font-size:13.5px;color:#0f172a;}
  .trip-card-plate{font-family:'Courier New',monospace;font-weight:800;font-size:11.5px;letter-spacing:.03em;color:#1d4ed8;background:#eff6ff;border-radius:6px;padding:3px 8px;flex:none;}
  .trip-card-route{font-size:13px;color:#334155;font-weight:600;margin-bottom:4px;}
  .trip-card-route svg{width:12px;height:12px;margin:0 3px;color:#94a3b8;position:relative;top:-1px;}
  .trip-card-meta{display:flex;flex-wrap:wrap;font-size:11.5px;color:#94a3b8;gap:4px 12px;}
  .trip-card-meta span{display:inline-flex;align-items:center;gap:4px;}
  .trip-card-meta svg{width:12px;height:12px;flex:none;}
  .trip-card-purpose{margin-top:8px;padding-top:8px;border-top:1px dashed #eef1f6;font-size:12px;color:#64748b;}

  .trip-empty-history{text-align:center; padding:24px 10px; color:#cbd5e1;}
  .trip-empty-history svg{width:30px;height:30px;margin-bottom:8px;}
  .trip-empty-history p{font-size:12.5px;color:#94a3b8;margin:0;}

  .trip-pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:6px;padding:0 2px 8px;}
  .trip-pagination a, .trip-pagination span.disabled{
    font-size:12.5px; font-weight:700; padding:8px 14px; border-radius:9px; border:1.5px solid #e2e8f0; color:#334155; text-decoration:none;
  }
  .trip-pagination span.disabled{color:#cbd5e1;}
  .trip-pagination .page-info{font-size:11.5px;color:#94a3b8;font-weight:600;}

  @media (max-width: 420px){
    .trip-hero h5{font-size:16.5px;}
    .trip-form-row{flex-direction:column; gap:0;}
  }
</style>
@endsection

@section('nav-title', 'VMIS | My Trip Logs')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="trip-page-wrap">

            @php
                $driverFullName = $driver ? trim($driver->firstname . ' ' . $driver->lastname) : null;
                $driverInitials = $driver ? strtoupper(substr($driver->firstname, 0, 1) . substr($driver->lastname, 0, 1)) : '—';
            @endphp

            <div class="trip-hero">
                <div class="trip-hero-top">
                    <div class="trip-hero-avatar">{{ $driverInitials }}</div>
                    <div>
                        <h5>{{ $driverFullName ?? 'My Trip Logs' }}</h5>
                        <p>{{ $driver ? 'PRO5 Fleet Registry — trip logging' : 'Driver profile not linked' }}</p>
                    </div>
                </div>
                @if($driver)
                <div class="trip-hero-stats">
                    <div class="trip-hero-stat">
                        <div class="num">{{ $trips->total() }}</div>
                        <div class="lbl">Total Trips</div>
                    </div>
                    <div class="trip-hero-stat">
                        <div class="num">{{ $vehicles->count() }}</div>
                        <div class="lbl">{{ $vehicles->count() === 1 ? 'Assigned Vehicle' : 'Assigned Vehicles' }}</div>
                    </div>
                </div>
                @endif
            </div>

            @if(! $driver)
                <div class="trip-notice-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round"/></svg>
                    <h6>No Driver Profile Linked</h6>
                    <p>Your account isn't linked to a driver profile yet, so no vehicle is assigned to you. Contact your administrator to have your account linked before you can log trips.</p>
                </div>
            @elseif($vehicles->isEmpty())
                <div class="trip-notice-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 17h14M6 17l1.2-6.4A2 2 0 0 1 9.15 9h5.7a2 2 0 0 1 1.95 1.6L18 17M7 17v2M17 17v2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.5" cy="17" r="1.4"/><circle cx="15.5" cy="17" r="1.4"/></svg>
                    <h6>No Vehicle Assigned</h6>
                    <p>You're not currently assigned to any vehicle. Contact your administrator to get a vehicle assigned before you can log trips.</p>
                </div>
            @else
                <div class="trip-form-card">
                    <div class="card-heading">
                        <div class="icon-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                        </div>
                        <strong>Log a New Trip</strong>
                    </div>

                    <form id="tripLogForm" autocomplete="off">
                        @if($vehicles->count() > 1)
                        <div class="trip-form-group">
                            <label for="vehicle_id">Vehicle</label>
                            <select class="form-control" id="vehicle_id" name="vehicle_id" required>
                                @foreach($vehicles as $v)
                                <option value="{{ $v->id }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make . ' ' . $v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" id="vehicle_id" name="vehicle_id" value="{{ $vehicles->first()->id }}">
                        <div class="trip-form-group">
                            <label>Vehicle</label>
                            <div class="form-control d-flex align-items-center" style="color:#64748b;">
                                {{ strtoupper($vehicles->first()->plate_number) }} — {{ trim($vehicles->first()->make . ' ' . $vehicles->first()->model) }}
                            </div>
                        </div>
                        @endif

                        <div class="trip-form-row">
                            <div class="trip-form-group">
                                <label for="trip_date">Trip Date</label>
                                <input type="date" class="form-control" id="trip_date" name="trip_date" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>

                        <div class="trip-form-row">
                            <div class="trip-form-group">
                                <label for="departure_time">Departure</label>
                                <input type="time" class="form-control" id="departure_time" name="departure_time">
                            </div>
                            <div class="trip-form-group">
                                <label for="arrival_time">Arrival</label>
                                <input type="time" class="form-control" id="arrival_time" name="arrival_time">
                            </div>
                        </div>

                        <div class="trip-form-group">
                            <label for="origin">Origin</label>
                            <input type="text" class="form-control" id="origin" name="origin" placeholder="e.g. Camp Crame" maxlength="150" required>
                        </div>

                        <div class="trip-form-group">
                            <label for="destination">Destination</label>
                            <input type="text" class="form-control" id="destination" name="destination" placeholder="e.g. Quezon City Hall" maxlength="150" required>
                        </div>

                        <div class="trip-form-group">
                            <label for="purpose">Purpose <small class="text-muted font-weight-normal">(optional)</small></label>
                            <input type="text" class="form-control" id="purpose" name="purpose" placeholder="e.g. Official errand" maxlength="255">
                        </div>

                        <div class="trip-form-row">
                            <div class="trip-form-group">
                                <label for="odometer_start">Odometer Start</label>
                                <input type="number" class="form-control" id="odometer_start" name="odometer_start" min="0" inputmode="numeric" placeholder="km">
                            </div>
                            <div class="trip-form-group">
                                <label for="odometer_end">Odometer End</label>
                                <input type="number" class="form-control" id="odometer_end" name="odometer_end" min="0" inputmode="numeric" placeholder="km">
                            </div>
                        </div>

                        <div class="trip-form-group">
                            <label for="passengers">Passengers <small class="text-muted font-weight-normal">(optional)</small></label>
                            <input type="text" class="form-control" id="passengers" name="passengers" placeholder="e.g. Names or count" maxlength="255">
                        </div>

                        <div class="trip-form-group">
                            <label for="remarks">Remarks <small class="text-muted font-weight-normal">(optional)</small></label>
                            <textarea class="form-control" id="remarks" name="remarks" maxlength="1000" placeholder="Any additional notes"></textarea>
                        </div>

                        <button type="submit" class="btn-log-trip" id="tripSubmitBtn">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span>Log Trip</span>
                        </button>
                        <div class="form-submit-status" id="tripSubmitStatus"></div>
                    </form>
                </div>
            @endif

            @if($driver)
                <div class="trip-history-heading">
                    <strong>Recent Trips</strong>
                    <span>{{ $trips->total() }} total</span>
                </div>

                @forelse($trips as $trip)
                    <div class="trip-card">
                        <div class="trip-card-top">
                            <div class="trip-card-date">{{ $trip->trip_date->format('M d, Y') }}</div>
                            @if($trip->vehicle)
                            <div class="trip-card-plate">{{ strtoupper($trip->vehicle->plate_number) }}</div>
                            @endif
                        </div>
                        <div class="trip-card-route">
                            {{ $trip->origin }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $trip->destination }}
                        </div>
                        <div class="trip-card-meta">
                            @if($trip->departure_time || $trip->arrival_time)
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3" stroke-linecap="round"/></svg>
                                {{ $trip->departure_time ? date('h:i A', strtotime($trip->departure_time)) : '—' }} to {{ $trip->arrival_time ? date('h:i A', strtotime($trip->arrival_time)) : '—' }}
                            </span>
                            @endif
                            @if($trip->odometer_start !== null || $trip->odometer_end !== null)
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12h4l3-8 4 16 3-8h4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ number_format((int) $trip->odometer_start) }} → {{ number_format((int) $trip->odometer_end) }}
                            </span>
                            @endif
                        </div>
                        @if($trip->purpose)
                        <div class="trip-card-purpose">{{ $trip->purpose }}</div>
                        @endif
                    </div>
                @empty
                    <div class="trip-empty-history">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 17h14M6 17l1.2-6.4A2 2 0 0 1 9.15 9h5.7a2 2 0 0 1 1.95 1.6L18 17" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <p>No trips logged yet.</p>
                    </div>
                @endforelse

                @if($trips->hasPages())
                <div class="trip-pagination">
                    @if($trips->onFirstPage())
                        <span class="disabled">&larr; Prev</span>
                    @else
                        <a href="{{ $trips->previousPageUrl() }}">&larr; Prev</a>
                    @endif

                    <span class="page-info">Page {{ $trips->currentPage() }} of {{ $trips->lastPage() }}</span>

                    @if($trips->hasMorePages())
                        <a href="{{ $trips->nextPageUrl() }}">Next &rarr;</a>
                    @else
                        <span class="disabled">Next &rarr;</span>
                    @endif
                </div>
                @endif
            @endif

        </div>
    </div>
</section>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('tripLogForm');
    if (!form) return; // no vehicle assigned / no driver profile — form isn't rendered

    const submitBtn = document.getElementById('tripSubmitBtn');
    const statusEl = document.getElementById('tripSubmitStatus');
    const storeUrl = @json(route('trip-logs.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function setStatus(message, kind) {
        statusEl.textContent = message;
        statusEl.style.display = message ? 'block' : 'none';
        statusEl.style.color = kind === 'error' ? '#dc2626' : (kind === 'success' ? '#16a34a' : '#64748b');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const odoStart = document.getElementById('odometer_start').value;
        const odoEnd = document.getElementById('odometer_end').value;
        if (odoStart !== '' && odoEnd !== '' && Number(odoEnd) < Number(odoStart)) {
            setStatus('Odometer end cannot be less than odometer start.', 'error');
            return;
        }

        const payload = {
            vehicle_id: document.getElementById('vehicle_id').value,
            trip_date: document.getElementById('trip_date').value,
            departure_time: document.getElementById('departure_time').value || null,
            arrival_time: document.getElementById('arrival_time').value || null,
            origin: document.getElementById('origin').value,
            destination: document.getElementById('destination').value,
            purpose: document.getElementById('purpose').value || null,
            odometer_start: odoStart === '' ? null : odoStart,
            odometer_end: odoEnd === '' ? null : odoEnd,
            passengers: document.getElementById('passengers').value || null,
            remarks: document.getElementById('remarks').value || null,
        };

        submitBtn.disabled = true;
        setStatus('Saving trip…', 'info');

        fetch(storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then(function (response) { return response.json().then(function (data) { return { status: response.status, data: data }; }); })
            .then(function (result) {
                if (result.status === 200 && result.data && result.data.success) {
                    setStatus(result.data.message || 'Trip logged successfully.', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                    return;
                }

                submitBtn.disabled = false;

                if (result.status === 422 && result.data && result.data.errors) {
                    const firstError = Object.values(result.data.errors)[0];
                    setStatus(Array.isArray(firstError) ? firstError[0] : 'Please check the form and try again.', 'error');
                    return;
                }

                setStatus((result.data && result.data.message) || 'Could not log this trip. Please try again.', 'error');
            })
            .catch(function (err) {
                console.warn('Trip log submit failed:', err);
                submitBtn.disabled = false;
                setStatus('Network error — please try again.', 'error');
            });
    });
});
</script>
@endsection
