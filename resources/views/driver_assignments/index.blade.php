@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Driver Assignments</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  /* Same card/toolbar/table look as every other module index page — each
     view keeps its own copy rather than a shared stylesheet, matching the
     existing convention (see trip_registrations/index.blade.php etc.). */
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; transition: transform .15s ease, box-shadow .15s ease; }
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.da-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.da-assigned .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.da-unassigned .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.da-available .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
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
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 160px; }

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
    .fleet-table td:nth-child(2)::before { content: "Unit / Station"; }
    .fleet-table td:nth-child(3)::before { content: "Current Driver"; }
    .fleet-table td:nth-child(4)::before { content: "Assigned Since"; }
    .fleet-table td:nth-child(5)::before { content: "Actions"; }
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
  textarea.form-control-modern { height: auto; resize: vertical; }

  .license-warning-box { background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 10px; padding: 10px 14px; margin-top: -6px; margin-bottom: 18px; font-size: 12.5px; color: #b91c1c; font-weight: 600; display: none; align-items: center; gap: 8px; }
  .license-warning-box i { font-size: 14px; }

  .assign-current-badge { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px; font-size: 13px; color: #334155; }

  /* Assignment history modal — a simple vertical timeline (append-only
     list, no pagination) rather than another DataTable, since one
     vehicle realistically accumulates a handful of reassignments over
     its service life, not hundreds. */
  .history-timeline { max-height: 60vh; overflow-y: auto; padding-right: 4px; }
  .history-item { position: relative; padding: 0 0 18px 26px; border-left: 2px solid #eef1f6; }
  .history-item:last-child { border-left-color: transparent; padding-bottom: 0; }
  .history-item::before { content: ''; position: absolute; left: -6px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; border: 2px solid #fff; box-shadow: 0 0 0 1px #eef1f6; }
  .history-item.is-active::before { background: #16a34a; }
  .history-item-driver { font-weight: 800; font-size: 13.5px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
  .history-active-badge { font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 20px; }
  .history-item-range { font-size: 12px; color: #475569; margin-top: 3px; }
  .history-item-meta { font-size: 11px; color: #94a3b8; margin-top: 3px; }
  .history-item-notes { font-size: 12px; color: #334155; background: #f8fafc; border-radius: 8px; padding: 6px 10px; margin-top: 6px; }
  .history-empty { text-align: center; color: #94a3b8; font-size: 12.5px; padding: 30px 10px; }
</style>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern da-total">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total'] }}</div>
                    <div class="stat-label-title">Total Vehicles</div>
                </div>
            </div>
            <div class="stat-card-modern da-assigned">
                <div class="stat-icon-wrapper"><i class="fas fa-id-badge"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['assigned'] }}</div>
                    <div class="stat-label-title">Currently Assigned</div>
                </div>
            </div>
            <div class="stat-card-modern da-unassigned">
                <div class="stat-icon-wrapper"><i class="fas fa-triangle-exclamation"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['unassigned'] }}</div>
                    <div class="stat-label-title">Unassigned Vehicles</div>
                </div>
            </div>
            <div class="stat-card-modern da-available">
                <div class="stat-icon-wrapper"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['available_drivers'] }}</div>
                    <div class="stat-label-title">Drivers Without a Vehicle</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-exchange-alt text-primary"></i> Driver Assignments</h5>
                    <p>Who currently has custody of which vehicle{{ $hasBroadVisibility ? ' (fleet-wide)' : ' (within your assigned scope)' }} — click History for the full assignment trail.</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="daSearchBox" class="form-control" placeholder="Search plate or driver...">
                    </div>
                    <select id="filterAssignStatus" class="form-control custom-filter-select">
                        <option value="">All Vehicles</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                    <select id="filterUnit" class="form-control custom-filter-select">
                        <option value="">All Units</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                        @endforeach
                    </select>
                    <select id="filterStation" class="form-control custom-filter-select">
                        <option value="">All Stations</option>
                        @foreach($stations as $station)
                            <option value="{{ $station->id }}">{{ $station->station_name }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="resetDaFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table w-100" id="driverAssignTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Unit / Station</th>
                            <th>Current Driver</th>
                            <th>Assigned Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- ASSIGN / REASSIGN MODAL -->
<div class="modal fade" id="assignDriverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-exchange-alt mr-2"></i> Assign Driver — <span id="assignModalPlate"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="assignDriverForm">
                @csrf
                <div class="modal-body p-4">
                    <div id="assignCurrentBadge" class="assign-current-badge" style="display:none;">
                        Currently assigned to <strong id="assignCurrentDriverName"></strong> — submitting below will close that assignment and open a new one.
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Driver <span class="text-danger">*</span></label>
                        <select name="driver_id" id="assign_driver_id" class="form-control form-control-modern" required>
                            <option value="">Select driver...</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" data-expired="{{ $driver->isLicenseExpired() ? '1' : '0' }}">{{ $driver->lastname }}, {{ $driver->firstname }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="assignLicenseWarning" class="license-warning-box">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>This driver's license has expired. They cannot be assigned until it's updated.</span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Notes (optional)</label>
                        <textarea name="notes" class="form-control form-control-modern" rows="2" maxlength="255" placeholder="e.g. temporary coverage while regular driver is on leave"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" id="btnSubmitAssignDriver" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ASSIGNMENT HISTORY MODAL -->
<div class="modal fade" id="assignmentHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history mr-2"></i> Assignment History — <span id="historyModalPlate"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div id="historyTimeline" class="history-timeline"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document.body).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const daTable = $('#driverAssignTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('driver-assignments.index') }}",
            data: function(d) {
                d.assignment_status = $('#filterAssignStatus').val();
                d.unit_id = $('#filterUnit').val();
                d.station_id = $('#filterStation').val();
                d.search = { value: $('#daSearchBox').val() };
            }
        },
        columns: [
            { data: 'vehicle_html', orderable: false, searchable: false },
            { data: 'scope_html', orderable: false, searchable: false },
            { data: 'driver_html', orderable: false, searchable: false },
            { data: 'since_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: { processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading assignments...' }
    });

    let searchTimeout;
    $('#daSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { daTable.draw(); }, 300);
    });
    $('#filterAssignStatus, #filterUnit, #filterStation').on('change', function() {
        daTable.draw();
    });
    $('#resetDaFiltersBtn').on('click', function() {
        $('#daSearchBox, #filterAssignStatus, #filterUnit, #filterStation').val('');
        daTable.draw();
    });

    // ---------------- Assign / Reassign ----------------
    const assignUrlTemplate = @json(route('driver-assignments.assign', ['vehicle' => ':id']));
    let assignVehicleId = null;

    $(document).on('click', '.assign-reg-btn', function() {
        assignVehicleId = $(this).data('id');
        const plate = $(this).data('plate');
        const currentDriverId = $(this).data('current-driver');

        $('#assignModalPlate').text(plate);
        $('#assignDriverForm')[0].reset();
        $('#assignLicenseWarning').hide();
        $('#btnSubmitAssignDriver').prop('disabled', false);

        if (currentDriverId) {
            const currentOpt = $('#assign_driver_id option[value="' + currentDriverId + '"]');
            $('#assignCurrentDriverName').text(currentOpt.length ? currentOpt.text() : 'another driver');
            $('#assignCurrentBadge').show();
        } else {
            $('#assignCurrentBadge').hide();
        }

        $('#assignDriverModal').modal('show');
    });

    // Inline expired-license warning — same pattern as the Register Trip
    // modal on Trip Registrations. data-expired is rendered server-side
    // from Driver::isLicenseExpired() for every option.
    $('#assign_driver_id').on('change', function() {
        const opt = $(this).find('option:selected');
        const expired = opt.data('expired') == 1 || opt.data('expired') === '1';
        $('#assignLicenseWarning').toggle(expired);
        $('#btnSubmitAssignDriver').prop('disabled', expired);
    });

    $('#assignDriverForm').on('submit', function(e) {
        e.preventDefault();
        if (!assignVehicleId) return;
        $.ajax({
            url: assignUrlTemplate.replace(':id', assignVehicleId),
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    Swal.fire('Assigned!', res.message, 'success').then(() => {
                        $('#assignDriverModal').modal('hide');
                        daTable.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({ icon: 'warning', title: 'Cannot Assign', text: res.message, confirmButtonColor: '#d33' });
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong.';
                Swal.fire('Error!', msg, 'error');
            }
        });
    });

    // ---------------- Unassign ----------------
    const unassignUrlTemplate = @json(route('driver-assignments.unassign', ['vehicle' => ':id']));
    $(document).on('click', '.unassign-reg-btn', function() {
        const id = $(this).data('id');
        const plate = $(this).data('plate');
        Swal.fire({
            title: 'Unassign driver from ' + plate + '?',
            text: 'This vehicle will show as Unassigned until a new driver is assigned.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Unassign'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(unassignUrlTemplate.replace(':id', id), {}, function(res) {
                if (res.success) { Swal.fire('Unassigned', res.message, 'success').then(() => daTable.ajax.reload(null, false)); }
                else { Swal.fire('Error!', res.message, 'error'); }
            }).fail(function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong.';
                Swal.fire('Error!', msg, 'error');
            });
        });
    });

    // ---------------- Assignment History ----------------
    const historyUrlTemplate = @json(route('driver-assignments.history', ['vehicle' => ':id']));

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    $(document).on('click', '.history-reg-btn', function() {
        const id = $(this).data('id');
        const plate = $(this).data('plate');
        $('#historyModalPlate').text(plate);
        $('#historyTimeline').html('<div class="history-empty"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>');
        $('#assignmentHistoryModal').modal('show');

        $.getJSON(historyUrlTemplate.replace(':id', id), function(res) {
            const records = res.records || [];
            if (records.length === 0) {
                $('#historyTimeline').html('<div class="history-empty"><i class="fas fa-history mb-2" style="font-size:22px; display:block;"></i>No assignment history yet for this vehicle.</div>');
                return;
            }

            let html = '';
            records.forEach(function(r) {
                html += '<div class="history-item' + (r.is_active ? ' is-active' : '') + '">' +
                    '<div class="history-item-driver">' + escapeHtml(r.driver_name) +
                        (r.is_active ? ' <span class="history-active-badge">Active</span>' : '') +
                    '</div>' +
                    '<div class="history-item-range">' + escapeHtml(r.assigned_at) + ' &rarr; ' + (r.unassigned_at ? escapeHtml(r.unassigned_at) : 'present') + ' <span class="text-muted">(' + escapeHtml(r.duration) + ')</span></div>' +
                    '<div class="history-item-meta">Assigned by ' + escapeHtml(r.assigned_by) + (r.unassigned_by ? ' &middot; Unassigned by ' + escapeHtml(r.unassigned_by) : '') + '</div>' +
                    (r.notes ? '<div class="history-item-notes">' + escapeHtml(r.notes) + '</div>' : '') +
                    '</div>';
            });
            $('#historyTimeline').html(html);
        }).fail(function() {
            $('#historyTimeline').html('<div class="history-empty">Could not load assignment history.</div>');
        });
    });
});
</script>
@endsection
