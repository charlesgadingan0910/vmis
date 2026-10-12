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
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .custom-filter-select, .custom-filter-date { height: 38px; font-size: 12.5px; border-radius: 8px; border: 1px solid #e2e8f0; }
  .search-input-shell { position: relative; }
  .search-input-shell i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
  .search-input-shell input { padding-left: 32px; height: 38px; font-size: 12.5px; border-radius: 8px; border: 1px solid #e2e8f0; }

  .fleet-table .plate-badge { background: #eff6ff; color: #1d4ed8; font-weight: 800; font-size: 11.5px; padding: 3px 8px; border-radius: 6px; display: inline-block; }
  .fleet-table .vehicle-main-name { font-weight: 700; color: #0f172a; }
  .fleet-table .vehicle-sub-info { font-size: 11px; color: #94a3b8; }

  .modal-content-premium { border-radius: 16px; border: none; overflow: hidden; }
  .modal-header-slate { background: #0f172a; color: #fff; padding: 16px 22px; }
  .modal-header-slate h5 { color: #fff; font-weight: 800; font-size: 15px; margin: 0; }
  .form-control-modern { border-radius: 9px; border: 1px solid #e2e8f0; font-size: 13px; }
</style>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

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
                    <select id="filterVehicle" class="form-control custom-filter-select">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="resetRegFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                    @if($canManage)
                    <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#registerTripModal" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;">
                        <i class="fas fa-plus mr-1"></i> Register Trip
                    </button>
                    @endif
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
                            <select name="driver_id" class="form-control form-control-modern" required>
                                <option value="">Select driver...</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Assistant (optional)</label>
                            <select name="assistant_driver_id" class="form-control form-control-modern">
                                <option value="">None</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
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
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Register Trip</button>
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

    let searchTimeout;
    $('#regSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { regTable.draw(); }, 300);
    });
    $('#filterStatus, #filterVehicle').on('change', function() { regTable.draw(); });
    $('#resetRegFiltersBtn').on('click', function() {
        $('#regSearchBox, #filterStatus, #filterVehicle').val('');
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

    $('#registerTripModal').on('hidden.bs.modal', function() {
        $('#registerTripForm')[0].reset();
        updateScheduleVisibility();
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
                    Swal.fire('Error!', res.message, 'error');
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
