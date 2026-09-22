@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | My Profile</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  /* ---------- Profile hero banner ---------- */
  .profile-hero {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 14px; padding: 26px 28px; color: #fff; margin-bottom: 22px;
    position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
  }
  .profile-hero::after { content:''; position:absolute; top:-60%; right:-10%; width:50%; height:220%; background:radial-gradient(circle,rgba(59,130,246,0.18),transparent 65%); }
  .profile-avatar-lg { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.15); position: relative; z-index: 1; flex-shrink: 0; }
  .profile-hero-body { position: relative; z-index: 1; min-width: 0; flex: 1; }
  .profile-hero-body h4 { margin: 0; font-weight: 800; letter-spacing: -0.3px; }
  .profile-hero-body p { margin: 3px 0 10px; color: #94a3b8; font-size: 13px; }
  .profile-chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
  .profile-chip {
    display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700;
    padding: 5px 12px; border-radius: 20px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); color: #e2e8f0;
  }
  .profile-chip.badge-role { background: rgba(59,130,246,0.15); color: #60a5fa; border-color: rgba(59,130,246,0.3); text-transform: uppercase; letter-spacing: 0.04em; }
  .profile-chip i { font-size: 10.5px; }

  /* ---------- Form cards ---------- */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 6px 20px rgba(15, 23, 42, 0.04); overflow: hidden; margin-bottom: 22px; }
  .toolbar-header { padding: 20px 24px; background: #ffffff; border-bottom: 1px solid #eef1f6; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 15.5px; display: flex; align-items: center; gap: 8px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }
  .card-body-padded { padding: 22px 24px; }

  .field-label { font-weight: 700; font-size: 11.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 7px; display: block; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; height: 46px; padding: 10px 14px; color: #0f172a; transition: all 0.2s ease; background: #f8fafc; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); background: #ffffff; outline: none; }

  .readonly-field { background: #f8fafc; border: 1.5px dashed #e2e8f0; border-radius: 10px; height: 46px; display: flex; align-items: center; padding: 0 14px; font-size: 14px; font-weight: 600; color: #64748b; }
  .readonly-note { font-size: 11px; color: #94a3b8; margin-top: 14px; display: flex; align-items: center; gap: 6px; }

  .btn-save-modern { border-radius: 9px; font-weight: 700; font-size: 13.5px; padding: 10px 22px; border: none; color: #fff; background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
  .btn-save-modern:hover { color: #fff; opacity: 0.92; }
  .btn-save-modern:disabled { opacity: 0.6; cursor: not-allowed; }

  .pw-match-hint { font-size: 11.5px; margin-top: 6px; font-weight: 600; display: none; }
  .pw-match-hint.match { color: #16a34a; display: block; }
  .pw-match-hint.mismatch { color: #dc2626; display: block; }

  /* ---------- My Activity table (mirrors System Activity Logs styling) ---------- */
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 220px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }

  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none;}
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }
  .log-when { font-weight: 700; color: #0f172a; font-size: 13px; }
  .log-description { color: #334155; max-width: 480px; }

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

  .dataTables_wrapper .row { padding: 0 24px; align-items: center; }
  .dataTables_info { font-size: 12.5px; color: #64748b; padding-top: 20px !important; }
  .dataTables_paginate { padding-top: 15px !important; padding-bottom: 20px !important; }
  .pagination .page-link { border-radius: 8px; margin: 0 3px; font-size: 13px; border: 1px solid #e2e8f0; color: #334155; }
  .pagination .page-item.active .page-link { background: #3b82f6; border-color: #3b82f6; color: #fff; }

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
    .fleet-table td:last-child { padding-bottom: 14px !important; text-align: right !important; border-top: 1px dashed #eef1f6 !important; margin-top: 2px; }
    .fleet-table td:nth-child(2)::before { content: "Action"; }
    .fleet-table td:nth-child(3)::before { content: "Module"; }
    .fleet-table td:nth-child(4)::before { content: "Description"; }
    .fleet-table td::before { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px; }
  }
</style>
@endsection

@section('nav-title', 'VMIS | My Profile')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <img src="{{ $user->profile_image ?? null ? asset($user->profile_image) : asset('dist/img/user2-160x160.jpg') }}" class="profile-avatar-lg" alt="Profile photo">
            <div class="profile-hero-body">
                <h4>{{ trim(($user->rank ? $user->rank.' ' : '').$user->firstname.' '.($user->middlename ? substr($user->middlename,0,1).'. ' : '').$user->lastname.' '.($user->qlfr ?? '')) }}</h4>
                <p>{{ $user->email ?: 'No email address on file' }}</p>
                <div class="profile-chip-row">
                    <span class="profile-chip badge-role"><i class="fas fa-shield-alt"></i> {{ $user->account_type }}</span>
                    <span class="profile-chip"><i class="fas fa-id-badge"></i> Badge #{{ $user->badge_number }}</span>
                    @if($unit)
                    <span class="profile-chip"><i class="fas fa-building"></i> {{ $unit->unit_name }}</span>
                    @endif
                    @if($station)
                    <span class="profile-chip"><i class="fas fa-map-marker-alt"></i> {{ $station->station_name }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <!-- PERSONAL INFORMATION -->
            <div class="col-lg-6">
                <div class="fleet-card-container">
                    <div class="toolbar-header">
                        <div class="toolbar-title">
                            <h5><i class="fas fa-user-circle text-primary"></i> Personal Information</h5>
                            <p>Keep your contact details up to date</p>
                        </div>
                    </div>
                    <div class="card-body-padded">
                        <form id="profileForm" action="{{ route('profile.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label class="field-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="firstname" class="form-control form-control-modern" value="{{ old('firstname', $user->firstname) }}" required maxlength="255">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="field-label">Middle Name</label>
                                    <input type="text" name="middlename" class="form-control form-control-modern" value="{{ old('middlename', $user->middlename) }}" maxlength="255">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-8 form-group">
                                    <label class="field-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="lastname" class="form-control form-control-modern" value="{{ old('lastname', $user->lastname) }}" required maxlength="255">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="field-label">Suffix</label>
                                    <input type="text" name="qlfr" class="form-control form-control-modern" value="{{ old('qlfr', $user->qlfr) }}" maxlength="50" placeholder="Jr, Sr, III">
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <label class="field-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-modern" value="{{ old('email', $user->email) }}" required maxlength="255">
                            </div>

                            <div class="readonly-note">
                                <i class="fas fa-lock"></i> Badge number, rank, account type, unit and station are managed by an administrator and can't be changed here.
                            </div>

                            <div class="text-right mt-3">
                                <button type="submit" class="btn-save-modern"><i class="fas fa-check mr-1"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="col-lg-6">
                <div class="fleet-card-container">
                    <div class="toolbar-header">
                        <div class="toolbar-title">
                            <h5><i class="fas fa-key text-primary"></i> Change Password</h5>
                            <p>Use a password only you know</p>
                        </div>
                    </div>
                    <div class="card-body-padded">
                        <form id="passwordForm" action="{{ route('profile.password') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label class="field-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" id="current_password" class="form-control form-control-modern" required autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label class="field-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" id="new_password" class="form-control form-control-modern" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="form-group mb-1">
                                <label class="field-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control form-control-modern" required minlength="8" autocomplete="new-password">
                                <div class="pw-match-hint" id="pwMatchHint"></div>
                            </div>

                            <div class="readonly-note">
                                <i class="fas fa-circle-info"></i> Must be at least 8 characters and different from your current password.
                            </div>

                            <div class="text-right mt-3">
                                <button type="submit" class="btn-save-modern" id="passwordSubmitBtn"><i class="fas fa-key mr-1"></i> Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY ACTIVITY -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-history text-primary"></i> My Activity</h5>
                    <p>A history of actions taken on your account and by you in the system</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search my activity...">
                    </div>
                    <button type="button" id="resetFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="myActivityTable" class="table fleet-table w-100">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Description</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody><!-- Populated by DataTables Server-Side AJAX --></tbody>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    @if(session('success')) toastr.success("{{ session('success') }}"); @endif
    @if($errors->any()) toastr.error("{{ $errors->first() }}"); @endif

    // ================= PERSONAL INFO FORM =================
    $('#profileForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Save Changes?',
            text: "Are you sure you want to update your profile information?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });

    // ================= PASSWORD FORM =================
    function checkPasswordMatch() {
        const pw = $('#new_password').val();
        const confirm = $('#new_password_confirmation').val();
        const hint = $('#pwMatchHint');
        if (!confirm) { hint.removeClass('match mismatch').hide(); return; }
        if (pw === confirm) {
            hint.removeClass('mismatch').addClass('match').text('Passwords match.').show();
        } else {
            hint.removeClass('match').addClass('mismatch').text('Passwords do not match.').show();
        }
    }
    $('#new_password, #new_password_confirmation').on('keyup input', checkPasswordMatch);

    $('#passwordForm').submit(function(e) {
        e.preventDefault();
        if ($('#new_password').val() !== $('#new_password_confirmation').val()) {
            Swal.fire({
                title: 'Passwords Do Not Match',
                text: 'Please make sure both new password fields match.',
                icon: 'error',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'modal-content-premium' }
            });
            return;
        }
        let form = this;
        Swal.fire({
            title: 'Update Password?',
            text: "You'll need to use this new password the next time you log in.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, update it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });

    // ================= MY ACTIVITY TABLE =================
    // Server-side + paginated from the start (never a client-side/full-history
    // fetch), and the query behind it filters on activity_logs.user_id, which
    // is already indexed — so this stays fast whether the account has a dozen
    // entries or hundreds of thousands.
    const table = $('#myActivityTable').DataTable({
        "dom": '<"row"<"col-sm-12"tr>><"row pt-3"<"col-sm-5"i><"col-sm-7"p>>',
        "processing": true,
        "serverSide": true,
        "paging": true,
        "lengthChange": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "ajax": {
            "url": "{{ route('profile.activity') }}",
            "data": function(d) {
                d.search = { value: $('#customSearchBox').val() };
            }
        },
        "columns": [
            { "data": "when", "name": "created_at" },
            { "data": "action", "name": "action" },
            { "data": "module", "name": "module" },
            { "data": "description", "name": "description", "orderable": false },
            { "data": "details", "name": "details", "orderable": false, "searchable": false, "className": "text-center" }
        ],
        "order": [[0, 'desc']],
        "language": {
            "processing": '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading your activity...'
        }
    });

    let searchDebounce;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(function() { table.draw(); }, 300);
    });

    $('#resetFiltersBtn').on('click', function() {
        $('#customSearchBox').val('');
        table.draw();
    });

    $('#myActivityTable').on('click', '.btn-view-log-details', function() {
        const id = $(this).data('id');
        $('#logDetailsModal').modal('show');
        $('#logDetailsBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');

        $.get("{{ url('profile/activity') }}/" + id, function(res) {
            if (!res.success) return;

            let html = '<div class="diff-meta-row">'
                + '<div class="diff-meta-item"><div class="label">When</div><div class="value">' + (res.created_at || '—') + '</div></div>'
                + '<div class="diff-meta-item"><div class="label">IP Address</div><div class="value">' + (res.ip_address || '—') + '</div></div>'
                + '</div>'
                + '<p class="mb-3">' + $('<div>').text(res.description).html() + '</p>';

            const changes = res.changes || {};
            const before = changes.before || {};
            const after = changes.after || {};
            const hasBefore = Object.keys(before).length > 0;
            const hasAfter = Object.keys(after).length > 0;
            const fields = Array.from(new Set(Object.keys(before).concat(Object.keys(after))));

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
