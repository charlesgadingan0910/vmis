@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | System Users</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03); display: flex; align-items: center; gap: 16px; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  
  .stat-card-modern.c-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.c-active .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.c-inactive .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  .stat-card-modern.c-admins .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
  
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }
  
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6;}
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 220px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 160px; }
  
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none;}
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }
  
  .user-avatar-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
  .user-main-name { font-weight: 700; color: #0f172a; font-size: 14px; margin-bottom: 2px;}
  .user-sub-info { font-size: 12px; color: #64748b; }
  
  .status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; }
  .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; }
  .status-active { background: #eaf6ef; color: #16a34a; } .status-active .dot { background: #16a34a; }
  .status-inactive { background: #fcedec; color: #dc2626; } .status-inactive .dot { background: #dc2626; }
  
  .modal-content-premium { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px 24px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }
  
  .modal-section-divider { font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; margin: 18px 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid #f1f5f9; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
  .field-label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; display: block; }
</style>
@endsection

@section('nav-title', 'VMIS | System Users Management')

@section('nav-actions')
@if(!empty($allowedTypes))
<button type="button" id="btnAddNewUser" class="btn btn-primary font-weight-bold shadow-sm" style="border-radius:8px;">
    <i class="fas fa-plus"></i> <span class="btn-label">Add New User</span>
</button>
@endif
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <!-- Dynamic Metrics Grid -->
        <div class="fleet-stats-grid">
            <div class="stat-card-modern c-total">
                <div class="stat-icon-wrapper"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-total"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Total Users</div>
                </div>
            </div>
            <div class="stat-card-modern c-active">
                <div class="stat-icon-wrapper"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-active"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Active Users</div>
                </div>
            </div>
            <div class="stat-card-modern c-inactive">
                <div class="stat-icon-wrapper"><i class="fas fa-user-times"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-inactive"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Inactive Users</div>
                </div>
            </div>
            <div class="stat-card-modern c-admins">
                <div class="stat-icon-wrapper"><i class="fas fa-user-shield"></i></div>
                <div>
                    <div class="stat-num-value" id="stat-admins"><i class="fas fa-spinner fa-spin font-size-16"></i></div>
                    <div class="stat-label-title">Administrators</div>
                </div>
            </div>
        </div>

        <!-- Main Datatable -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title"><h5>User Directory</h5></div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search accounts...">
                    </div>
                    
                    <select id="filterAccountType" class="form-control custom-filter-select">
                        <option value="">All Account Types</option>
                        @foreach($accountTypes as $type)
                            <option value="{{ $type->type }}">{{ $type->type }}</option>
                        @endforeach
                    </select>

                    <select id="filterStatus" class="form-control custom-filter-select" style="width:130px;">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>

                    <button type="button" id="resetFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="usersTable" class="table fleet-table w-100">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Personnel Profile</th>
                            <th>Account Type</th>
                            <th>Badge #</th>
                            <th>Unit/Station</th>
                            <th>Account Status</th>
                            <th class="text-center">Connection</th>
                            <th class="text-center">Actions</th>
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

<!-- REGISTER USER MODAL -->
<div class="modal fade" id="registerUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-user-plus mr-2 text-primary"></i> Create System User</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="registerUserForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 mb-4" style="background:#eff6ff; color:#1e40af; border-radius: 8px;">
                        <i class="fas fa-info-circle mr-2"></i> New user accounts will be created with the system default password: <strong style="font-family: monospace; font-size: 15px;">P@ssw0rd12345</strong>
                    </div>

                    <div class="modal-section-divider mt-0">Personal Identity</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Middle Name</label>
                            <input type="text" name="middlename" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Qualifier (e.g. Jr, Sr)</label>
                            <input type="text" name="qlfr" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-divider">Credentials & Contact</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Badge Number <span class="text-danger">*</span></label>
                            <input type="text" name="badge_number" class="form-control form-control-modern" required autocomplete="off">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-modern" required autocomplete="off">
                        </div>
                    </div>

                    <div class="modal-section-divider">Role & Assignment</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Account Type <span class="text-danger">*</span></label>
                            <select name="account_type" class="form-control form-control-modern" required>
                                <option value="">Select Account Type...</option>
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type->type }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Rank</label>
                            <select name="rank" class="form-control form-control-modern">
                                <option value="">Select Rank...</option>
                                @foreach($ranks as $rank)
                                    <option value="{{ $rank->rank_abbvr }}">{{ $rank->rank_abbvr }} - {{ $rank->rank_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Unit Assignment</label>
                            <select name="unit_id" class="form-control form-control-modern">
                                <option value="">Select Unit...</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Station Assignment</label>
                            <select name="station_id" class="form-control form-control-modern">
                                <option value="">Select Station...</option>
                                @foreach($stations as $station)
                                    <option value="{{ $station->id }}">{{ $station->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save User Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit System User</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="editUserForm">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" id="edit_id">
                
                <div class="modal-body p-4">
                    <div class="modal-section-divider mt-0">Personal Identity</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_firstname" name="firstname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Middle Name</label>
                            <input type="text" id="edit_middlename" name="middlename" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_lastname" name="lastname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Qualifier (e.g. Jr, Sr)</label>
                            <input type="text" id="edit_qlfr" name="qlfr" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-divider">Credentials & Contact</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Badge Number <span class="text-danger">*</span></label>
                            <input type="text" id="edit_badge_number" name="badge_number" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="edit_email" name="email" class="form-control form-control-modern" required>
                        </div>
                    </div>

                    <div class="modal-section-divider">Role & Assignment</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Account Type <span class="text-danger">*</span></label>
                            <select id="edit_account_type" name="account_type" class="form-control form-control-modern" required>
                                <option value="">Select Account Type...</option>
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type->type }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Rank</label>
                            <select id="edit_rank" name="rank" class="form-control form-control-modern">
                                <option value="">Select Rank...</option>
                                @foreach($ranks as $rank)
                                    <option value="{{ $rank->rank_abbvr }}">{{ $rank->rank_abbvr }} - {{ $rank->rank_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Unit Assignment</label>
                            <select id="edit_unit_id" name="unit_id" class="form-control form-control-modern">
                                <option value="">Select Unit...</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Station Assignment</label>
                            <select id="edit_station_id" name="station_id" class="form-control form-control-modern">
                                <option value="">Select Station...</option>
                                @foreach($stations as $station)
                                    <option value="{{ $station->id }}">{{ $station->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <h5><i class="fas fa-shield-alt mr-2"></i> Security Verification</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="margin-top: -20px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="resetPasswordForm">
                @csrf
                <input type="hidden" id="resetUserId">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0" style="background:#fffbeb; color:#92400e;">
                        <i class="fas fa-info-circle mr-1"></i> You are resetting this user's password to the default: <strong>P@ssw0rd12345</strong>
                    </div>
                    <label class="field-label mt-3">Confirm YOUR Admin Password</label>
                    <input type="password" class="form-control form-control-modern" name="admin_password" id="admin_password" placeholder="Enter your current password" required>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold text-white"><i class="fas fa-sync-alt mr-1"></i> Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const table = $('#usersTable').DataTable({
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
            "url": "{{ route('users.index') }}",
            "data": function(d) {
                d.account_type = $('#filterAccountType').val();
                d.status = $('#filterStatus').val();
                d.search = { value: $('#customSearchBox').val() };
            },
            "dataSrc": function(json) {
                if(json.stats) {
                    $('#stat-total').text(json.stats.total);
                    $('#stat-active').text(json.stats.active);
                    $('#stat-inactive').text(json.stats.inactive);
                    $('#stat-admins').text(json.stats.admins);
                }
                return json.data;
            }
        },
        "columns": [
            { "data": "rank", "name": "rank" },
            { "data": "profile", "name": "firstname" },
            { "data": "account_type", "name": "account_type" },
            { "data": "badge_number", "name": "badge_number" },
            { "data": "unit_station", "name": "unit_id" },
            { "data": "status", "name": "is_active" },
            { "data": "connection", "name": "is_online", "orderable": false, "searchable": false },
            { "data": "actions", "name": "actions", "orderable": false, "searchable": false }
        ],
        "columnDefs": [
            { "orderable": false, "targets": [7] }
        ]
    });

    // Debounced so typing at speed doesn't fire a server round-trip on every
    // keystroke — matters once the table holds thousands of rows.
    let searchDebounce;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(function() { table.draw(); }, 300);
    });
    $('#filterAccountType, #filterStatus').on('change', function() { table.draw(); });
    $('#resetFiltersBtn').on('click', function() {
        $('#customSearchBox, #filterAccountType, #filterStatus').val('');
        table.draw();
    });

    $('#btnAddNewUser').on('click', function() {
        $('#registerUserForm')[0].reset();
        $('#registerUserModal').modal('show');
    });

    $('#registerUserForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Create Account?',
            text: "Are you sure you want to save this new user profile?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Save Record'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('users.store') }}",
                    type: "POST",
                    data: $(this).serialize(),
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Saved!', res.message, 'success').then(() => { 
                                $('#registerUserModal').modal('hide');
                                table.ajax.reload(null, false); 
                            });
                        } else {
                            if(res.clear_form) $('#registerUserForm')[0].reset();
                            Swal.fire('Error!', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    });

    $('#usersTable').on('click', '.btn-edit-user', function() {
        let id = $(this).data('id');
        
        $.get("{{ url('users') }}/" + id + "/edit-data", function(res) {
            if(res.success) {
                let d = res.data;
                $('#edit_id').val(d.id);
                $('#edit_firstname').val(d.firstname);
                $('#edit_middlename').val(d.middlename);
                $('#edit_lastname').val(d.lastname);
                $('#edit_qlfr').val(d.qlfr);
                $('#edit_badge_number').val(d.badge_number);
                $('#edit_email').val(d.email);
                $('#edit_account_type').val(d.account_type);
                $('#edit_rank').val(d.rank);
                $('#edit_unit_id').val(d.unit_id);
                $('#edit_station_id').val(d.station_id);
                $('#editUserModal').modal('show');
            }
        });
    });

    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Save Changes?',
            text: "Update this user's profile information?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Update Record'
        }).then((result) => {
            if (result.isConfirmed) {
                let id = $('#edit_id').val();
                $.ajax({
                    url: "{{ url('users') }}/" + id,
                    type: "POST",
                    data: $(this).serialize(),
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Updated!', res.message, 'success').then(() => { 
                                $('#editUserModal').modal('hide');
                                table.ajax.reload(null, false); 
                            });
                        } else {
                            Swal.fire('Error!', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    });

    $('#usersTable').on('click', '.btn-delete-user', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Deactivate Account?',
            text: "This will revoke the user's access to the system.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Deactivate!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('users') }}/" + id,
                    type: "POST",
                    data: { _method: "DELETE", _token: "{{ csrf_token() }}" },
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Deactivated!', res.message, 'success').then(() => { 
                                table.ajax.reload(null, false); 
                            });
                        }
                    }
                });
            }
        });
    });

    $('#usersTable').on('click', '.btn-reset-password', function() {
        let id = $(this).data('id');
        $('#resetUserId').val(id);
        $('#admin_password').val('');
        $('#resetPasswordModal').modal('show');
    });

    $('#resetPasswordForm').on('submit', function(e) {
        e.preventDefault();
        let id = $('#resetUserId').val();

        Swal.fire({
            title: 'Verify Password Reset',
            text: "Are you certain you wish to overwrite this user's password?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Confirm Reset'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('users') }}/" + id + "/reset-password",
                    type: "POST",
                    data: $(this).serialize(),
                    success: function(res) {
                        if(res.success) {
                            $('#resetPasswordModal').modal('hide');
                            Swal.fire('Reset Complete!', res.message, 'success');
                        } else {
                            Swal.fire('Authentication Failed!', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    });
});
</script>
@endsection