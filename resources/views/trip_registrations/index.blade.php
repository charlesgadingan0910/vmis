@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Trip Registrations</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; transition: transform .15s ease, box-shadow .15s ease; }
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.tr-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.tr-pending .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.tr-active .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.tr-completed .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 200px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select, .custom-filter-date { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; }
  .custom-filter-select { width: 148px; }
  .custom-filter-date { width: 138px; }
  .btn-add-entity { border-radius: 9px; font-weight: 700; font-size: 13px; padding: 8px 16px; border: none; color: #fff; background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
  .btn-add-entity:hover { color: #fff; opacity: 0.92; }

  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }
  .fleet-table tbody tr { transition: background-color .12s ease; }
  .fleet-table tbody tr:hover { background-color: #f8fafc; }

  @media (max-width: 767.98px) {
    .fleet-table thead { display: none !important; }
    .fleet-table, .fleet-table tbody, .fleet-table tr, .fleet-table td {
      display: block !important; width: 100% !important;
    }
    .fleet-table tr {
      background: #fff !important; border: 1px solid #eef1f6 !important; border-radius: 14px !important;
      box-shadow: 0 1px 3px rgba(15,23,42,0.04) !important; margin-bottom: 12px !important; padding: 4px 16px !important;
    }
    .fleet-table td { padding: 10px 0 !important; border-top: 1px solid #f8fafc !important; text-align: left !important; }
    .fleet-table td:first-child { border-top: none !important; padding-top: 14px !important; }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }
    .fleet-table td:nth-child(1)::before { content: "Vehicle"; }
    .fleet-table td:nth-child(2)::before { content: "Personnel"; }
    .fleet-table td:nth-child(3)::before { content: "Trip"; }
    .fleet-table td:nth-child(4)::before { content: "Scheduled"; }
    .fleet-table td:nth-child(5)::before { content: "Status"; }
    .fleet-table td:nth-child(6)::before { content: "Actions"; }
  }

  .plate-badge { display: inline-block; font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #0f172a; color: #fff; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.5px; }
  .vehicle-main-name { font-weight: 700; color: #0f172a; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; }

  .dataTables_wrapper .row { padding: 0 24px; align-items: center; }
  .dataTables_info { font-size: 12.5px; color: #64748b; padding-top: 20px !important; }
  .dataTables_paginate { padding-top: 15px !important; padding-bottom: 20px !important; }
  .pagination .page-link { border-radius: 8px; margin: 0 3px; font-size: 13px; border: 1px solid #e2e8f0; color: #334155; }
  .pagination .page-item.active .page-link { background: #3b82f6; border-color: #3b82f6; color: #fff; }

  .modal-content-premium { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25); }
  .modal-header-slate { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 22px 26px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }
  .modal-header-slate .close { color: #94a3b8; opacity: 1; text-shadow: none; }
  .modal-header-slate .close:hover { color: #fff; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; height: 46px; padding: 10px 14px; color: #0f172a; background: #f8fafc; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); background: #fff; outline: none; }

  /* Round trip: mirrors the mobile app's manual-entry toggle — only ever a
     flag of intent at registration time; the actual two-leg trip_logs
     rows are still created when the trip is logged/ended, same as always. */
  .round-trip-check-box { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; }
  .round-trip-check-box .custom-control-label { cursor: pointer; }
  .round-trip-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; padding: 2px 7px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

  /* Inline expired-license warning — shown the instant a driver/assistant
     with an expired license is picked, ahead of the server-side block in
     TripRegistrationController::store(). */
  .license-warning-box { background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 10px; padding: 10px 14px; margin-top: -6px; margin-bottom: 18px; font-size: 12.5px; color: #b91c1c; font-weight: 600; display: none; align-items: center; gap: 8px; }
  .license-warning-box i { font-size: 14px; }
</style>
@endsection

@if($canManage)
@section('nav-actions')
<button type="button" class="btn-add-entity" data-toggle="modal" data-target="#registerTripModal">
    <i class="fas fa-calendar-plus mr-1"></i> <span class="btn-label">Register Trip</span>
</button>
@endsection
@endif

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern tr-total">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total'] }}</div>
                    <div class="stat-label-title">Total Registrations</div>
                </div>
            </div>
            <div class="stat-card-modern tr-pending">
                <div class="stat-icon-wrapper"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['pending'] }}</div>
                    <div class="stat-label-title">Pending Approval</div>
                </div>
            </div>
            <div class="stat-card-modern tr-active">
                <div class="stat-icon-wrapper"><i class="fas fa-route"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['active'] }}</div>
                    <div class="stat-label-title">Approved / In Progress</div>
                </div>
            </div>
            <div class="stat-card-modern tr-completed">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['completed_month'] }}</div>
                    <div class="stat-label-title">Completed This Month</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-calendar-check text-primary"></i> Trip Registrations</h5>
                    <p>Register → Assign Personnel → Approve, ahead of a scheduled or multi-personnel trip{{ $hasBroadVisibility ? ' (fleet-wide)' : ' (within your assigned scope)' }}</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="regSearchBox" class="form-control" placeholder="Search registrations...">
                    </div>
                    <select id="filterStatus" class="form-control custom-filter-select">
                        <option value="">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved — Not Started</option>
                        <option value="active">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select id="filterTripType" class="form-control custom-filter-select">
                        <option value="">All Trip Types</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="unscheduled">Unscheduled</option>
                        <option value="emergency">Emergency</option>
                    </select>
                    <select id="filterVehicle" class="form-control custom-filter-select" style="width:150px;">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }}</option>
                        @endforeach
                    </select>
                    <select id="filterDriver" class="form-control custom-filter-select" style="width:160px;">
                        <option value="">All Drivers</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                        @endforeach
                    </select>
                    <input type="date" id="filterDateFrom" class="form-control custom-filter-date" title="Scheduled from">
                    <input type="date" id="filterDateTo" class="form-control custom-filter-date" title="Scheduled to">
                    <button type="button" id="resetRegFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table w-100" id="tripRegTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Personnel</th>
                            <th>Trip</th>
                            <th>Scheduled</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@if($canManage)
<!-- REGISTER TRIP MODAL -->
<div class="modal fade" id="registerTripModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-calendar-check mr-2"></i> Register a Trip</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="registerTripForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Trip Type <span class="text-danger">*</span></label>
                        <select name="trip_type" id="reg_trip_type" class="form-control form-control-modern" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="unscheduled">Unscheduled (sudden)</option>
                            <option value="emergency">Emergency — auto-approved</option>
                        </select>
                        <small class="text-muted d-block mt-1" id="regTypeHint">Needs approval before the driver can start tracking.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Vehicle <span class="text-danger">*</span></label>
                        <select name="vehicle_id" class="form-control form-control-modern" required>
                            <option value="">Select vehicle...</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Driver <span class="text-danger">*</span></label>
                            <select name="driver_id" id="reg_driver_id" class="form-control form-control-modern" required>
                                <option value="">Select driver...</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" data-expired="{{ $driver->isLicenseExpired() ? '1' : '0' }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Assistant (optional)</label>
                            <select name="assistant_driver_id" id="reg_assistant_driver_id" class="form-control form-control-modern">
                                <option value="">None</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" data-expired="{{ $driver->isLicenseExpired() ? '1' : '0' }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="regLicenseWarning" class="license-warning-box">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span id="regLicenseWarningText">This driver's license has expired. The trip cannot be registered until it's updated.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Origin <span class="text-danger">*</span></label>
                            <input type="text" name="origin" class="form-control form-control-modern" required maxlength="150">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Destination <span class="text-danger">*</span></label>
                            <input type="text" name="destination" class="form-control form-control-modern" required maxlength="150">
                        </div>
                    </div>

                    <div class="round-trip-check-box">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="reg_round_trip" name="round_trip" value="1">
                            <label class="custom-control-label font-weight-bold" for="reg_round_trip">
                                <i class="fas fa-exchange-alt mr-1 text-primary"></i> Round trip — the driver will also return from Destination back to Origin
                            </label>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Purpose</label>
                        <input type="text" name="purpose" class="form-control form-control-modern" maxlength="255">
                    </div>

                    <div id="regScheduleSection" class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" class="form-control form-control-modern">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Departure Time</label>
                            <input type="time" name="scheduled_departure_time" class="form-control form-control-modern">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" id="btnSubmitRegisterTrip" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Register Trip</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document.body).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const regTable = $('#tripRegTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('trip-registrations.index') }}",
            data: function(d) {
                d.status = $('#filterStatus').val();
                d.vehicle_id = $('#filterVehicle').val();
                d.trip_type = $('#filterTripType').val();
                d.driver_id = $('#filterDriver').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.search = { value: $('#regSearchBox').val() };
            }
        },
        columns: [
            { data: 'vehicle_html', orderable: false, searchable: false },
            { data: 'personnel_html', orderable: false, searchable: false },
            { data: 'trip_html', orderable: false, searchable: false },
            { data: 'schedule_html', orderable: false, searchable: false },
            { data: 'status_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: { processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading registrations...' }
    });

    // Debounced search + instant filter redraws — same pattern used on
    // every other DataTables-backed page in VMIS. serverSide:true keeps
    // this scaling to thousands of rows without ever rendering them all
    // client-side, so filtering stays snappy regardless of table size.
    let searchTimeout;
    $('#regSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { regTable.draw(); }, 300);
    });
    $('#filterStatus, #filterVehicle, #filterTripType, #filterDriver, #filterDateFrom, #filterDateTo').on('change', function() {
        regTable.draw();
    });
    $('#resetRegFiltersBtn').on('click', function() {
        $('#regSearchBox, #filterStatus, #filterVehicle, #filterTripType, #filterDriver, #filterDateFrom, #filterDateTo').val('');
        regTable.draw();
    });

    // ---------------- Approve / Reject / Cancel (delegated — rows are AJAX-rendered) ----------------
    $(document).on('click', '.approve-reg-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Approve this trip?',
            text: 'The driver will be able to start GPS tracking immediately once approved.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: 'Yes, Approve'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post('{{ url("trip-registrations") }}/' + id + '/approve', {}, function(res) {
                if (res.success) { Swal.fire('Approved!', res.message, 'success').then(() => regTable.ajax.reload(null, false)); }
                else { Swal.fire('Error!', res.message, 'error'); }
            }).fail(function() { Swal.fire('Error!', 'Something went wrong.', 'error'); });
        });
    });

    $(document).on('click', '.reject-reg-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Reject this trip?',
            input: 'text',
            inputPlaceholder: 'Reason (optional)',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Reject'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post('{{ url("trip-registrations") }}/' + id + '/reject', { rejection_reason: result.value || null }, function(res) {
                if (res.success) { Swal.fire('Rejected', res.message, 'success').then(() => regTable.ajax.reload(null, false)); }
                else { Swal.fire('Error!', res.message, 'error'); }
            }).fail(function() { Swal.fire('Error!', 'Something went wrong.', 'error'); });
        });
    });

    $(document).on('click', '.cancel-reg-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Cancel this registration?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Cancel It'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post('{{ url("trip-registrations") }}/' + id + '/cancel', {}, function(res) {
                if (res.success) { Swal.fire('Cancelled', res.message, 'success').then(() => regTable.ajax.reload(null, false)); }
                else { Swal.fire('Error!', res.message, 'error'); }
            }).fail(function() { Swal.fire('Error!', 'Something went wrong.', 'error'); });
        });
    });

    @if($canManage)
    // ---------------- Register Trip modal ----------------
    function updateScheduleVisibility() {
        const type = $('#reg_trip_type').val();
        $('#regScheduleSection').toggle(type === 'scheduled');
        $('#regScheduleSection [name="scheduled_date"]').prop('required', type === 'scheduled');
        $('#regTypeHint').text(
            type === 'emergency'
                ? 'Auto-approved immediately — the driver can start tracking right away.'
                : 'Needs approval before the driver can start tracking.'
        );
    }
    $('#reg_trip_type').on('change', updateScheduleVisibility);
    updateScheduleVisibility();

    // Inline expired-license warning — checked the instant either driver
    // dropdown changes, well ahead of the authoritative server-side block
    // in TripRegistrationController::store(). data-expired is rendered
    // server-side from Driver::isLicenseExpired() for every option.
    function checkLicenseWarning() {
        const driverOpt = $('#reg_driver_id option:selected');
        const assistantOpt = $('#reg_assistant_driver_id option:selected');
        const driverExpired = driverOpt.data('expired') == 1 || driverOpt.data('expired') === '1';
        const assistantExpired = assistantOpt.data('expired') == 1 || assistantOpt.data('expired') === '1';

        if (driverExpired) {
            $('#regLicenseWarningText').text("This driver's license has expired. The trip cannot be registered until it's updated.");
            $('#regLicenseWarning').css('display', 'flex');
        } else if (assistantExpired) {
            $('#regLicenseWarningText').text("The assistant driver's license has expired. The trip cannot be registered until it's updated.");
            $('#regLicenseWarning').css('display', 'flex');
        } else {
            $('#regLicenseWarning').hide();
        }

        $('#btnSubmitRegisterTrip').prop('disabled', driverExpired || assistantExpired);
    }
    $('#reg_driver_id, #reg_assistant_driver_id').on('change', checkLicenseWarning);

    $('#registerTripModal').on('hidden.bs.modal', function() {
        $('#registerTripForm')[0].reset();
        updateScheduleVisibility();
        checkLicenseWarning();
    });

    $('#registerTripForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('trip-registrations.store') }}",
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    Swal.fire('Registered!', res.message, 'success').then(() => {
                        $('#registerTripModal').modal('hide');
                        regTable.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({ icon: 'warning', title: 'Cannot Register Trip', text: res.message, confirmButtonColor: '#d33' });
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong.';
                Swal.fire('Error!', msg, 'error');
            }
        });
    });
    @endif
});
</script>
@endsection
