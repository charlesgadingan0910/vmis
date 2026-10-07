@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | My Fuel Logs</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  :root{ --vmis-navy-900:#0f172a; --vmis-navy-800:#1e293b; --vmis-amber:#f59e0b; --vmis-amber-dark:#d97706; --vmis-green:#16a34a; }

  .fuel-page-wrap{max-width:480px;margin:0 auto;padding-bottom:env(safe-area-inset-bottom, 0px);}

  /* ---------- Hero ---------- */
  .fuel-hero{
    background:linear-gradient(135deg, var(--vmis-navy-800), var(--vmis-navy-900));
    border-radius:20px; padding:22px 22px 20px; color:#fff; position:relative; overflow:hidden;
    margin-bottom:16px; box-shadow:0 10px 30px rgba(15,23,42,0.18);
  }
  .fuel-hero::after{content:'';position:absolute;top:-40%;right:-15%;width:65%;height:65%;background:radial-gradient(circle, rgba(245,158,11,0.28), transparent 65%);}
  .fuel-hero-top{display:flex; align-items:center; gap:12px; position:relative; z-index:1;}
  .fuel-hero-avatar{
    width:46px;height:46px;border-radius:12px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.35);
    display:flex;align-items:center;justify-content:center;flex:none;font-weight:800;font-size:16px;color:#fcd34d;
  }
  .fuel-hero h5{margin:0;font-weight:800;font-size:18px;letter-spacing:-.2px;}
  .fuel-hero p{margin:2px 0 0;color:#94a3b8;font-size:12.5px;}
  .fuel-hero-stats{display:flex; gap:10px; position:relative; z-index:1; margin-top:16px;}
  .fuel-hero-stat{flex:1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.09); border-radius:12px; padding:10px 12px;}
  .fuel-hero-stat .num{font-size:19px; font-weight:800; line-height:1.1;}
  .fuel-hero-stat .lbl{font-size:10.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; margin-top:2px;}

  /* ---------- Notice / empty-state cards ---------- */
  .fuel-notice-card{
    background:#fff; border-radius:16px; border:1px solid #eef1f6; box-shadow:0 1px 3px rgba(15,23,42,0.04);
    padding:22px 20px; text-align:center; margin-bottom:16px;
  }
  .fuel-notice-card svg{width:34px;height:34px;color:#cbd5e1;margin-bottom:10px;}
  .fuel-notice-card h6{font-weight:800;color:#334155;margin-bottom:6px;font-size:14.5px;}
  .fuel-notice-card p{color:#94a3b8;font-size:12.5px;margin:0;line-height:1.5;}

  /* ---------- Form card ---------- */
  .fuel-form-card{background:#fff;border-radius:16px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:18px 18px 20px;margin-bottom:16px;}
  .fuel-form-card .card-heading{display:flex;align-items:center;gap:9px;margin-bottom:16px;}
  .fuel-form-card .card-heading .icon-badge{width:30px;height:30px;border-radius:9px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex:none;}
  .fuel-form-card .card-heading .icon-badge svg{width:16px;height:16px;color:var(--vmis-amber-dark);}
  .fuel-form-card .card-heading strong{font-size:14.5px;color:#0f172a;font-weight:800;}

  .fuel-form-card label{font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;}
  .fuel-form-card .form-control{
    border:1.5px solid #e2e8f0; border-radius:11px; height:46px; padding:0 14px;
    font-size:14px; color:#0f172a; background:#f8fafc;
  }
  .fuel-form-card .form-control:focus{outline:none; border-color:var(--vmis-amber); background:#fff; box-shadow:0 0 0 3.5px rgba(245,158,11,0.12);}
  .fuel-form-row{display:flex; gap:10px;}
  .fuel-form-row > div{flex:1; min-width:0;}
  .fuel-form-group{margin-bottom:14px;}

  .receipt-upload-box{
    border:1.5px dashed #e2e8f0; border-radius:11px; padding:16px 14px; text-align:center; background:#f8fafc;
    cursor:pointer; transition:border-color .15s ease, background .15s ease;
  }
  .receipt-upload-box.has-file{border-color:var(--vmis-amber); background:#fffbeb;}
  .receipt-upload-box svg{width:22px;height:22px;color:#94a3b8;margin-bottom:6px;}
  .receipt-upload-box .upload-label{font-size:12.5px;font-weight:700;color:#334155;}
  .receipt-upload-box .upload-sub{font-size:11px;color:#94a3b8;margin-top:2px;}
  .receipt-upload-box .file-name{font-size:12px;font-weight:700;color:var(--vmis-amber-dark);margin-top:4px;word-break:break-all;}

  .btn-log-fuel{
    width:100%; height:50px; border-radius:11px; border:none;
    background:linear-gradient(135deg, var(--vmis-amber), var(--vmis-amber-dark)); color:#fff; font-weight:700; font-size:14.5px;
    display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 10px rgba(245,158,11,0.3);
    transition:transform .15s ease;
  }
  .btn-log-fuel:active{transform:scale(0.98);}
  .btn-log-fuel:disabled{opacity:.65;}
  .form-submit-status{margin-top:10px;font-size:12.5px;font-weight:600;display:none;text-align:center;}

  /* ---------- History ---------- */
  .fuel-history-heading{display:flex;align-items:center;justify-content:space-between;margin:4px 2px 12px;}
  .fuel-history-heading strong{font-size:14.5px;color:#0f172a;font-weight:800;}
  .fuel-history-heading span{font-size:11.5px;color:#94a3b8;font-weight:600;}

  .fuel-card{background:#fff;border-radius:14px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:14px 16px;margin-bottom:10px;}
  .fuel-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;}
  .fuel-card-date{font-weight:800;font-size:13.5px;color:#0f172a;}
  .fuel-card-plate{font-family:'Courier New',monospace;font-weight:800;font-size:11.5px;letter-spacing:.03em;color:#d97706;background:#fffbeb;border-radius:6px;padding:3px 8px;flex:none;}
  .fuel-card-amounts{font-size:13px;color:#334155;font-weight:600;margin-bottom:6px;}
  .fuel-card-meta{display:flex;flex-wrap:wrap;font-size:11.5px;color:#94a3b8;gap:4px 12px;}
  .fuel-card-meta span{display:inline-flex;align-items:center;gap:4px;}
  .fuel-card-meta svg{width:12px;height:12px;flex:none;}
  .fuel-card-receipt{margin-top:8px;padding-top:8px;border-top:1px dashed #eef1f6;}
  .fuel-card-receipt a{font-size:12px;font-weight:700;color:#d97706;text-decoration:none;}

  .fuel-empty-history{text-align:center; padding:24px 10px; color:#cbd5e1;}
  .fuel-empty-history svg{width:30px;height:30px;margin-bottom:8px;}
  .fuel-empty-history p{font-size:12.5px;color:#94a3b8;margin:0;}

  .fuel-pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:6px;padding:0 2px 8px;}
  .fuel-pagination a, .fuel-pagination span.disabled{
    font-size:12.5px; font-weight:700; padding:8px 14px; border-radius:9px; border:1.5px solid #e2e8f0; color:#334155; text-decoration:none;
  }
  .fuel-pagination span.disabled{color:#cbd5e1;}
  .fuel-pagination .page-info{font-size:11.5px;color:#94a3b8;font-weight:600;}

  @media (max-width: 420px){
    .fuel-hero h5{font-size:16.5px;}
    .fuel-form-row{flex-direction:column; gap:0;}
  }
</style>
@endsection

@section('nav-title', 'VMIS | My Fuel Logs')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="fuel-page-wrap">

            @php
                $driverFullName = $driver ? trim($driver->firstname . ' ' . $driver->lastname) : null;
                $driverInitials = $driver ? strtoupper(substr($driver->firstname, 0, 1) . substr($driver->lastname, 0, 1)) : '—';
                $latestLog = $driver ? $logs->first() : null;
            @endphp

            <div class="fuel-hero">
                <div class="fuel-hero-top">
                    <div class="fuel-hero-avatar">{{ $driverInitials }}</div>
                    <div>
                        <h5>{{ $driverFullName ?? 'My Fuel Logs' }}</h5>
                        <p>{{ $driver ? 'PRO5 Fleet Registry — fuel monitoring' : 'Driver profile not linked' }}</p>
                    </div>
                </div>
                @if($driver)
                <div class="fuel-hero-stats">
                    <div class="fuel-hero-stat">
                        <div class="num">{{ $logs->total() }}</div>
                        <div class="lbl">Refuels Logged</div>
                    </div>
                    <div class="fuel-hero-stat">
                        <div class="num">{{ $latestLog ? number_format($latestLog->kmPerLiter() ?? 0, 1) : '—' }}</div>
                        <div class="lbl">Latest km/L</div>
                    </div>
                </div>
                @endif
            </div>

            @if(! $driver)
                <div class="fuel-notice-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round"/></svg>
                    <h6>No Driver Profile Linked</h6>
                    <p>Your account isn't linked to a driver profile yet, so no vehicle is assigned to you. Contact your administrator to have your account linked before you can log refuels.</p>
                </div>
            @elseif($vehicles->isEmpty())
                <div class="fuel-notice-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 17h14M6 17l1.2-6.4A2 2 0 0 1 9.15 9h5.7a2 2 0 0 1 1.95 1.6L18 17M7 17v2M17 17v2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.5" cy="17" r="1.4"/><circle cx="15.5" cy="17" r="1.4"/></svg>
                    <h6>No Vehicle Assigned</h6>
                    <p>You're not currently assigned to any vehicle. Contact your administrator to get a vehicle assigned before you can log refuels.</p>
                </div>
            @else
                <div class="fuel-form-card">
                    <div class="card-heading">
                        <div class="icon-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 22h12M6 22V8a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v14M6 10h6M16 7l2.5 2.5a1.5 1.5 0 0 1 .5 1.1V18a1.5 1.5 0 0 1-3 0v-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <strong>Log a Refuel</strong>
                    </div>

                    <form id="fuelLogForm" autocomplete="off" enctype="multipart/form-data">
                        @if($vehicles->count() > 1)
                        <div class="fuel-form-group">
                            <label for="vehicle_id">Vehicle</label>
                            <select class="form-control" id="vehicle_id" name="vehicle_id" required>
                                @foreach($vehicles as $v)
                                <option value="{{ $v->id }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make . ' ' . $v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" id="vehicle_id" name="vehicle_id" value="{{ $vehicles->first()->id }}">
                        <div class="fuel-form-group">
                            <label>Vehicle</label>
                            <div class="form-control d-flex align-items-center" style="color:#64748b;">
                                {{ strtoupper($vehicles->first()->plate_number) }} — {{ trim($vehicles->first()->make . ' ' . $vehicles->first()->model) }}
                            </div>
                        </div>
                        @endif

                        <div class="fuel-form-group">
                            <label for="refuel_date">Date of Refueling</label>
                            <input type="date" class="form-control" id="refuel_date" name="refuel_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </div>

                        <div class="fuel-form-group">
                            <label for="odometer_reading">Odometer Reading</label>
                            <input type="number" class="form-control" id="odometer_reading" name="odometer_reading" min="0" inputmode="numeric" placeholder="km" required>
                        </div>

                        <div class="fuel-form-row">
                            <div class="fuel-form-group">
                                <label for="liters">Liters</label>
                                <input type="number" class="form-control" id="liters" name="liters" min="0.01" max="9999.99" step="0.01" inputmode="decimal" placeholder="e.g. 40.00" required>
                            </div>
                            <div class="fuel-form-group">
                                <label for="total_cost">Total Cost (&#8369;)</label>
                                <input type="number" class="form-control" id="total_cost" name="total_cost" min="0" max="9999999.99" step="0.01" inputmode="decimal" placeholder="e.g. 2500.00" required>
                            </div>
                        </div>

                        <div class="fuel-form-group">
                            <label for="receipt">Refuel Receipt <small class="text-muted font-weight-normal">(optional)</small></label>
                            <div class="receipt-upload-box" id="receiptUploadBox">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0-12 4 4m-4-4-4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <div class="upload-label">Tap to attach receipt</div>
                                <div class="upload-sub">PDF, JPG or PNG — up to 4MB</div>
                                <div class="file-name" id="receiptFileName"></div>
                            </div>
                            <input type="file" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                        </div>

                        <button type="submit" class="btn-log-fuel" id="fuelSubmitBtn">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span>Log Refuel</span>
                        </button>
                        <div class="form-submit-status" id="fuelSubmitStatus"></div>
                    </form>
                </div>
            @endif

            @if($driver)
                <div class="fuel-history-heading">
                    <strong>Recent Refuels</strong>
                    <span>{{ $logs->total() }} total</span>
                </div>

                @forelse($logs as $log)
                    <div class="fuel-card">
                        <div class="fuel-card-top">
                            <div class="fuel-card-date">{{ $log->refuel_date->format('M d, Y') }}</div>
                            @if($log->vehicle)
                            <div class="fuel-card-plate">{{ strtoupper($log->vehicle->plate_number) }}</div>
                            @endif
                        </div>
                        <div class="fuel-card-amounts">
                            {{ number_format((float) $log->liters, 2) }} L &middot; &#8369;{{ number_format((float) $log->total_cost, 2) }}
                        </div>
                        <div class="fuel-card-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12h4l3-8 4 16 3-8h4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ number_format((int) $log->odometer_reading) }} km
                            </span>
                            @php $kml = $log->kmPerLiter(); $dist = $log->distanceSinceLastRefuel(); @endphp
                            @if($kml !== null && $dist !== null)
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 3 14h7l-1 8 11-14h-7l1-6z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ number_format($dist) }} km since last &middot; {{ number_format($kml, 2) }} km/L
                            </span>
                            @else
                            <span class="text-muted">First logged refuel</span>
                            @endif
                        </div>
                        @if($log->receipt_path)
                        <div class="fuel-card-receipt">
                            <a href="{{ route('fuel-logs.document', ['path' => $log->receipt_path]) }}" target="_blank">
                                <i class="fas fa-receipt"></i> View Receipt
                            </a>
                        </div>
                        @endif
                    </div>
                @empty
                    <div class="fuel-empty-history">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 17h14M6 17l1.2-6.4A2 2 0 0 1 9.15 9h5.7a2 2 0 0 1 1.95 1.6L18 17" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <p>No refuels logged yet.</p>
                    </div>
                @endforelse

                @if($logs->hasPages())
                <div class="fuel-pagination">
                    @if($logs->onFirstPage())
                        <span class="disabled">&larr; Prev</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}">&larr; Prev</a>
                    @endif

                    <span class="page-info">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>

                    @if($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}">Next &rarr;</a>
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
    const form = document.getElementById('fuelLogForm');
    if (!form) return; // no vehicle assigned / no driver profile — form isn't rendered

    const submitBtn = document.getElementById('fuelSubmitBtn');
    const statusEl = document.getElementById('fuelSubmitStatus');
    const storeUrl = @json(route('fuel-logs.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function setStatus(message, kind) {
        statusEl.textContent = message;
        statusEl.style.display = message ? 'block' : 'none';
        statusEl.style.color = kind === 'error' ? '#dc2626' : (kind === 'success' ? '#16a34a' : '#64748b');
    }

    // ---------------- Receipt upload: tap the box to open the file picker ----------------
    const uploadBox = document.getElementById('receiptUploadBox');
    const fileInput = document.getElementById('receipt');
    const fileNameEl = document.getElementById('receiptFileName');

    uploadBox.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        if (this.files && this.files.length) {
            uploadBox.classList.add('has-file');
            fileNameEl.textContent = this.files[0].name;
        } else {
            uploadBox.classList.remove('has-file');
            fileNameEl.textContent = '';
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        setStatus('Saving refuel…', 'info');

        // FormData, not a plain JSON body — this form carries a file (the
        // receipt), same reasoning as maintenance/repairs' upload handling.
        const formData = new FormData(form);

        fetch(storeUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        })
            .then(function (response) { return response.json().then(function (data) { return { status: response.status, data: data }; }); })
            .then(function (result) {
                if (result.status === 200 && result.data && result.data.success) {
                    setStatus(result.data.message || 'Refuel logged successfully.', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                    return;
                }

                submitBtn.disabled = false;

                if (result.status === 422 && result.data && result.data.errors) {
                    const firstError = Object.values(result.data.errors)[0];
                    setStatus(Array.isArray(firstError) ? firstError[0] : 'Please check the form and try again.', 'error');
                    return;
                }

                setStatus((result.data && result.data.message) || 'Could not log this refuel. Please try again.', 'error');
            })
            .catch(function (err) {
                console.warn('Fuel log submit failed:', err);
                submitBtn.disabled = false;
                setStatus('Network error — please try again.', 'error');
            });
    });
});
</script>
@endsection
