@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | System Activity Logs</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
@endsection

@section('css')
<style>
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03); display: flex; align-items: center; gap: 16px; }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }

  .stat-card-modern.c-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.c-today .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.c-logins .stat-icon-wrapper { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
  .stat-card-modern.c-failed .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }

  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6;}
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 220px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; }
  .date-filter-input { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 150px; }

  /* Default select2 height matches the toolbar's plain filter dropdowns (38px). */
  .select2-container .select2-selection--single { height: 38px !important; border: 1.5px solid #e2e8f0 !important; border-radius: 9px !important; }
  .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 13px; }
  .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow { height: 36px !important; }

  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none;}
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }

  .log-when { font-weight: 700; color: #0f172a; font-size: 13px; }
  .log-description { color: #334155; max-width: 480px; }
  .user-main-name { font-weight: 700; color: #0f172a; font-size: 13.5px; }

  .modal-content-premium { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px 24px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }

  .diff-table { width: 100%; font-size: 13px; border-collapse: collapse; }
  .diff-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; padding: 6px 10px; border-bottom: 1.5px solid #f1f5f9; }
  .diff-table td { padding: 8px 10px; border-bottom: 1px solid #f8fafc; vertical-align: top; word-break: break-word; }
  .diff-field { font-weight: 700; color: #334155; }
  .diff-before { color: #dc2626; text-decoration: line-through; opacity: 0.8; }
  .diff-after { color: #16a34a; font-weight: 600; }
  .diff-meta-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1.5px solid #f1f5f9; }
  .diff-meta-item .label { font-size: 10.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700; }
  .diff-meta-item .value { font-size: 13.5px; color: #0f172a; font-weight: 600; }

  /* MOBILE: table rows become stacked cards, not a cramped horizontal scroll —
     same pattern used across every other list page in the app. */
  @media (max-width: 767.98px) {
    .fleet-table thead { display: none !important; }
    .fleet-table, .fleet-table tbody, .fleet-table tr, .fleet-table td {
      display: block !important; width: 100% !important;
    }
    .fleet-table tr {
      background: #fff !important; border: 1px solid #eef1f6 !important; border-radius: 14px !important;
      box-shadow: 0 1px 3px rgba(15,23,42,0.04) !important; margin-bottom: 12px !important; padding: 4px 16px !important;
    }
    .fleet-table td {
      padding: 10px 0 !important; border-top: 1px solid #f8fafc !important; text-align: left !important;
    }
    .fleet-table td:first-child { border-top: none !important; padding-top: 14px !important; }
    .fleet-table td:last-child {
      padding-bottom: 14px !important; text-align: right !important;
      border-top: 1px dashed #eef1f6 !important; margin-top: 2px;
    }
    .fleet-table td:nth-child(2)::before { content: "User"; }
    .fleet-table td:nth-child(3)::before { content: "Action"; }
    .fleet-table td:nth-child(4)::before { content: "Module"; }
    .fleet-table td:nth-child(5)::before { content: "Description"; }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }
  }
</style>
@endsection

@section('nav-title', 'VMIS | System Activity Logs')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern c-total">
                <div class="stat-icon-wrapper"><i class="fas fa-list-alt"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-total"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Total Log Entries</div>
                </div>
            </div>
            <div class="stat-card-modern c-today">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-today"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Activity Today</div>
                </div>
            </div>
            <div class="stat-card-modern c-logins">
                <div class="stat-icon-wrapper"><i class="fas fa-sign-in-alt"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-logins"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Logins Today</div>
                </div>
            </div>
            <div class="stat-card-modern c-failed">
                <div class="stat-icon-wrapper"><i class="fas fa-triangle-exclamation"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-failed"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Failed Logins Today</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5>System Activity Log</h5>
                    <p>Every record created/edited/deactivated, plus every login, logout and failed login attempt.</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search logs...">
                    </div>

                    <select id="filterModule" class="form-control custom-filter-select" style="width:170px;">
                        <option value="">All Modules</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}">{{ $module }}</option>
                        @endforeach
                    </select>

                    <select id="filterAction" class="form-control custom-filter-select" style="width:150px;">
                        <option value="">All Actions</option>
                        <option value="created">Created</option>
                        <option value="updated">Updated</option>
                        <option value="deleted">Deleted</option>
                        <option value="login">Login</option>
                        <option value="logout">Logout</option>
                        <option value="login_failed">Failed Login</option>
                    </select>

                    <select id="filterUser" class="custom-filter-select" style="width:200px;">
                        <option value="">All Users</option>
                        @foreach($actors as $actor)
                            <option value="{{ $actor->id }}">{{ trim(($actor->rank ? $actor->rank.' ' : '').($actor->fullname ?: trim($actor->firstname.' '.$actor->lastname))) }}</option>
                        @endforeach
                    </select>

                    <input type="date" id="filterDateFrom" class="form-control date-filter-input" title="From date">
                    <input type="date" id="filterDateTo" class="form-control date-filter-input" title="To date">

                    <button type="button" id="resetFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="logsTable" class="table fleet-table w-100">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Description</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by DataTables Server-Side AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- LOG DETAILS MODAL -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-clipboard-list mr-2 text-primary"></i> Activity Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="logDetailsBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer border-top p-3 bg-light">
                <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    if ($.fn.select2) {
        $('#filterUser').select2({
            theme: 'bootstrap4',
            width: 'resolve',
            placeholder: 'All Users',
            allowClear: true,
        });
    }

    const table = $('#logsTable').DataTable({
        "dom": '<"row"<"col-sm-12"tr>><"row pt-3"<"col-sm-5"i><"col-sm-7"p>>',
        "processing": true,
        "serverSide": true,
        "paging": true,
        "lengthChange": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "ajax": {
            "url": "{{ route('activity-logs.index') }}",
            "data": function(d) {
                d.module = $('#filterModule').val();
                d.action = $('#filterAction').val();
                d.user_id = $('#filterUser').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.search = { value: $('#customSearchBox').val() };
            },
            "dataSrc": function(json) {
                if (json.stats) {
                    $('#stat-total').text(json.stats.total);
                    $('#stat-today').text(json.stats.today);
                    $('#stat-logins').text(json.stats.logins_today);
                    $('#stat-failed').text(json.stats.failed_today);
                }
                return json.data;
            }
        },
        "columns": [
            { "data": "when", "name": "created_at" },
            { "data": "who", "name": "user_name" },
            { "data": "action", "name": "action" },
            { "data": "module", "name": "module" },
            { "data": "description", "name": "description", "orderable": false },
            { "data": "details", "name": "details", "orderable": false, "searchable": false, "className": "text-center" }
        ],
        "order": [[0, 'desc']],
    });

    // Debounced so typing at speed doesn't fire a server round-trip on every
    // keystroke — this table only grows over time, so it matters here more
    // than almost anywhere else in the app.
    let searchDebounce;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(function() { table.draw(); }, 300);
    });
    $('#filterModule, #filterAction, #filterDateFrom, #filterDateTo').on('change', function() { table.draw(); });
    $('#filterUser').on('change', function() { table.draw(); });

    $('#resetFiltersBtn').on('click', function() {
        $('#customSearchBox, #filterModule, #filterAction, #filterDateFrom, #filterDateTo').val('');
        $('#filterUser').val('').trigger('change');
        table.draw();
    });

    $('#logsTable').on('click', '.btn-view-log-details', function() {
        const id = $(this).data('id');
        $('#logDetailsModal').modal('show');
        $('#logDetailsBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');

        $.get("{{ url('activity-logs') }}/" + id, function(res) {
            if (!res.success) return;

            let html = '<div class="diff-meta-row">'
                + '<div class="diff-meta-item"><div class="label">When</div><div class="value">' + (res.created_at || '—') + '</div></div>'
                + '<div class="diff-meta-item"><div class="label">By</div><div class="value">' + (res.user_name || 'System / Unauthenticated') + '</div></div>'
                + '<div class="diff-meta-item"><div class="label">IP Address</div><div class="value">' + (res.ip_address || '—') + '</div></div>'
                + '</div>'
                + '<p class="mb-3">' + $('<div>').text(res.description).html() + '</p>';

            const changes = res.changes || {};
            const before = changes.before || {};
            const after = changes.after || {};
            const hasBefore = Object.keys(before).length > 0;
            const hasAfter = Object.keys(after).length > 0;
            const fields = Array.from(new Set(Object.keys(before).concat(Object.keys(after))));

            // Three shapes: a create only has "after" (what was entered), a
            // delete only has "before" (a snapshot of what existed), an
            // update has both and renders as an actual before→after diff.
            if (fields.length > 0) {
                let headerHtml, mode;
                if (hasBefore && hasAfter) {
                    headerHtml = '<th>Field</th><th>Before</th><th>After</th>';
                    mode = 'diff';
                } else if (hasBefore) {
                    headerHtml = '<th>Field</th><th>Value (at time of deletion)</th>';
                    mode = 'before-only';
                } else {
                    headerHtml = '<th>Field</th><th>Value</th>';
                    mode = 'after-only';
                }

                html += '<table class="diff-table"><thead><tr>' + headerHtml + '</tr></thead><tbody>';
                fields.forEach(function(field) {
                    const beforeVal = formatVal(before[field]);
                    const afterVal = formatVal(after[field]);
                    html += '<tr><td class="diff-field">' + escapeHtml(field) + '</td>';
                    if (mode === 'diff') {
                        html += '<td class="diff-before">' + beforeVal + '</td><td class="diff-after">' + afterVal + '</td>';
                    } else if (mode === 'before-only') {
                        html += '<td>' + beforeVal + '</td>';
                    } else {
                        html += '<td>' + afterVal + '</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }

            $('#logDetailsBody').html(html);
        });
    });

    function formatVal(v) {
        if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
        if (typeof v === 'object') return escapeHtml(JSON.stringify(v));
        return escapeHtml(String(v));
    }

    function escapeHtml(str) {
        return $('<div>').text(str).html();
    }
});
</script>
@endsection
