@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Driver Management</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  /* Stats Cards */
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; transition: transform 0.2s;}
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
  
  .stat-card-modern.total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.active .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.expiring .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.expired .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  /* Table & Toolbar */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 20px 24px; background: #ffffff; border-bottom: 1px solid #eef1f6; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
  
  .filter-bar { display: flex; flex-wrap: nowrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 240px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; width: 170px; }

  .fleet-table { width: 100% !important; margin: 0 !important; }
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; border: none; padding: 14px 24px; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  
  .driver-chip-wrapper { display: flex; align-items: center; gap: 12px; }
  .driver-avatar-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .driver-name-text { font-size: 14px; font-weight: 700; color: #0f172a; }
  
  /* Status Badges */
  .badge-monitor { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
  .monitor-expired { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  .monitor-expiring { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
  .monitor-valid { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

  /* DataTables Pagination */
  .dataTables_wrapper .row { padding: 0 24px; align-items: center; }
  .dataTables_info { font-size: 12.5px; color: #64748b; padding-top: 20px !important; }
  .dataTables_paginate { padding-top: 15px !important; padding-bottom: 20px !important; }
  .pagination .page-link { border-radius: 8px; margin: 0 3px; font-size: 13px; border: 1px solid #e2e8f0; color: #334155; }
  .pagination .page-item.active .page-link { background: #3b82f6; border-color: #3b82f6; color: #fff; }

  /* Modals */
  .modal-backdrop.show { opacity: 0.65; backdrop-filter: blur(4px); }
  .modal-content-premium { border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); background: #ffffff; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 20px 24px; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px;}
  .modal-section-title { font-size: 12px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.5px; margin: 15px 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }

  .smart-capture-container { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; }
  @media (max-width: 768px) { .smart-capture-container { grid-template-columns: 1fr; } }
  
  .smart-capture-box { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; background: #f8fafc; transition: all 0.2s; cursor: pointer; }
  .smart-capture-box:hover { border-color: #3b82f6; background: #eff6ff; }
  .smart-capture-icon { font-size: 24px; color: #3b82f6; margin-bottom: 6px; }
  .progress-bar-scanner { height: 6px; border-radius: 3px; background: #e2e8f0; overflow: hidden; margin-top: 10px; display: none; }
  .progress-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #3b82f6, #1d4ed8); transition: width 0.2s; }
</style>
@endsection

@section('nav-title', 'VMIS | Drivers Directory')

@section('nav-actions')
<button class="btn-nav-action btn-nav-action-primary" data-toggle="modal" data-target="#createModal">
  <i class="fas fa-plus"></i> Register Driver
</button>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <!-- License Monitoring Stats -->
        <div class="fleet-stats-grid">
            <div class="stat-card-modern total">
                <div class="stat-icon-wrapper"><i class="fas fa-id-card"></i></div>
                <div><div class="stat-num-value">{{ $stats['total'] }}</div><div class="stat-label-title">Total Personnel</div></div>
            </div>
            <div class="stat-card-modern active">
                <div class="stat-icon-wrapper"><i class="fas fa-user-check"></i></div>
                <div><div class="stat-num-value">{{ $stats['active'] }}</div><div class="stat-label-title">Active Drivers</div></div>
            </div>
            <div class="stat-card-modern expiring">
                <div class="stat-icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-num-value">{{ $stats['expiring'] }}</div><div class="stat-label-title">Expiring (30 Days)</div></div>
            </div>
            <div class="stat-card-modern expired">
                <div class="stat-icon-wrapper"><i class="fas fa-ban"></i></div>
                <div><div class="stat-num-value">{{ $stats['expired'] }}</div><div class="stat-label-title">Expired Licenses</div></div>
            </div>
        </div>

        <!-- Data Table Container -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div><h5 class="font-weight-bold m-0">Driver Profiles</h5></div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search name or license...">
                    </div>
                    <select id="filterStatus" class="form-control custom-filter-select">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button type="button" id="resetFiltersBtn" class="btn btn-light btn-sm font-weight-bold border text-secondary" style="border-radius:9px; height:38px; display:inline-flex; align-items:center; padding: 0 12px;">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="driversTable">
                    <thead>
                        <tr>
                            <th>Personnel Identity</th>
                            <th>License Detail</th>
                            <th>Expiration Status</th>
                            <th>Contact Info</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- CREATE MODAL WITH SMART CAPTURE & LIVE WEBCAM -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-user-plus mr-2 text-primary"></i> Register Driver</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('drivers.store') }}" method="POST" id="createForm">
                @csrf
                <div class="modal-body p-4">
                    
                    <div class="smart-capture-container">
                        <div class="smart-capture-box" id="smartCaptureArea">
                            <i class="fas fa-upload smart-capture-icon"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Upload License Image</h6>
                            <p class="text-muted font-size-11 mb-0">Select file from device</p>
                            <input type="file" id="licenseImage" accept="image/*" class="d-none">
                        </div>

                        <div class="smart-capture-box" id="openCameraBtn">
                            <i class="fas fa-camera smart-capture-icon text-success"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Live Camera Scan</h6>
                            <p class="text-muted font-size-11 mb-0">Capture card via webcam</p>
                        </div>
                    </div>

                    <div class="progress-bar-scanner mb-3" id="scanProgressBox">
                        <div class="progress-bar-fill" id="scanProgressBar"></div>
                    </div>
                    <div id="scanStatusText" class="font-size-11 text-primary mb-3 font-weight-bold text-center d-none">Scanning document...</div>

                    <div class="modal-section-title mt-0">Personal Information</div>
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Rank</label>
                            <select name="rank" id="c_rank" class="form-control form-control-modern">
                                <option value="">Select...</option>
                                @foreach($ranks as $rank)
                                <option value="{{ $rank->rank_abbvr }}">{{ $rank->rank_abbvr }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" id="c_fn" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Middle Name</label>
                            <input type="text" name="middlename" id="c_mn" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" id="c_ln" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-1 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Qlfr</label>
                            <input type="text" name="qlfr" id="c_qlfr" class="form-control form-control-modern" placeholder="Jr.">
                        </div>
                    </div>

                    <div class="modal-section-title">License & Contact Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">License Number</label>
                            <input type="text" name="license_number" id="c_lic_no" class="form-control form-control-modern" placeholder="e.g. N01-23-456789">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Expiration Date</label>
                            <input type="date" name="license_expiration_date" id="c_lic_exp" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">License Type</label>
                            <select name="license_type" id="c_lic_type" class="form-control form-control-modern">
                                <option value="">Select Type...</option>
                                <option value="Professional">Professional</option>
                                <option value="Non-Professional">Non-Professional</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Contact Number</label>
                            <input type="text" name="contact_number" id="c_contact" class="form-control form-control-modern" placeholder="+63 9...">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control form-control-modern" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- LIVE WEBCAM MODAL -->
<div class="modal fade" id="cameraModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-camera mr-2 text-success"></i> Live License Scanner</h5>
                <button type="button" class="close text-white close-camera-modal">&times;</button>
            </div>
            <div class="modal-body text-center p-3 bg-black">
                <div style="position:relative; width:100%; max-height:360px; overflow:hidden; background:#000; border-radius:10px;">
                    <video id="webcamVideo" autoplay playsinline style="width:100%; height:auto; display:block;"></video>
                </div>
                <p class="text-muted font-size-12 mt-2 mb-0">Position the driver's license clearly in front of the camera and click capture.</p>
            </div>
            <div class="modal-footer justify-content-between bg-light border-0">
                <button type="button" class="btn btn-light font-weight-bold close-camera-modal">Cancel</button>
                <button type="button" id="takeSnapshotBtn" class="btn btn-success font-weight-bold px-4">
                    <i class="fas fa-camera mr-1"></i> Capture & Scan
                </button>
            </div>
        </div>
    </div>
</div>

<canvas id="snapshotCanvas" class="d-none"></canvas>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-user-edit mr-2 text-primary"></i> Update Profile</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body p-4">
                    <div class="modal-section-title mt-0">Personal Information</div>
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Rank</label>
                            <select id="e_rank" name="rank" class="form-control form-control-modern">
                                <option value="">Select...</option>
                                @foreach($ranks as $rank)
                                <option value="{{ $rank->rank_abbvr }}">{{ $rank->rank_abbvr }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">First Name</label>
                            <input type="text" id="e_fn" name="firstname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Middle Name</label>
                            <input type="text" id="e_mn" name="middlename" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Last Name</label>
                            <input type="text" id="e_ln" name="lastname" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-1 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Qlfr</label>
                            <input type="text" id="e_qlfr" name="qlfr" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-title">License & Contact Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">License Number</label>
                            <input type="text" id="e_lic_no" name="license_number" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Expiration Date</label>
                            <input type="date" id="e_lic_exp" name="license_expiration_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">License Type</label>
                            <select id="e_lic_type" name="license_type" class="form-control form-control-modern">
                                <option value="">Select Type...</option>
                                <option value="Professional">Professional</option>
                                <option value="Non-Professional">Non-Professional</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Contact Number</label>
                            <input type="text" id="e_contact" name="contact_number" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Status</label>
                            <select id="e_status" name="status" class="form-control form-control-modern" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<!-- Tesseract.js, DataTables, & SweetAlert2 JS -->
<script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document.body).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    @if(session('success')) toastr.success("{{ session('success') }}"); @endif
    @if($errors->any()) toastr.error("{{ $errors->first() }}"); @endif

    // INITIALIZE FAST AJAX DATATABLE FOR DRIVERS
    const table = $('#driversTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('drivers.index') }}",
            data: function (d) {
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'identity_html', name: 'firstname' },
            { data: 'license_html', name: 'license_number' },
            { data: 'expiry_html', name: 'license_expiration_date' },
            { data: 'contact_html', name: 'contact_number' },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading drivers...'
        }
    });

    // Debounced Search
    let searchTimeout;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        let val = $(this).val();
        searchTimeout = setTimeout(function() {
            table.search(val).draw();
        }, 300);
    });

    // Status Filter Dropdown
    $('#filterStatus').change(function() {
        table.draw();
    });

    // Reset Filters
    $('#resetFiltersBtn').click(function() {
        $('#customSearchBox').val('');
        $('#filterStatus').val('');
        table.search('').draw();
    });

    // Event delegation for dynamically loaded Edit buttons
    $('#driversTable').on('click', '.edit-btn', function() {
        let data = $(this).data('driver');
        $('#e_rank').val(data.rank);
        $('#e_fn').val(data.firstname);
        $('#e_mn').val(data.middlename);
        $('#e_ln').val(data.lastname);
        $('#e_qlfr').val(data.qlfr);
        $('#e_lic_no').val(data.license_number);
        $('#e_lic_type').val(data.license_type);
        $('#e_contact').val(data.contact_number);
        $('#e_status').val(data.status);
        
        if(data.license_expiration_date) {
            $('#e_lic_exp').val(data.license_expiration_date.substring(0, 10));
        }

        let updateUrl = "{{ route('drivers.update', ':id') }}".replace(':id', data.id);
        $('#editForm').attr('action', updateUrl);
        
        $('#editModal').modal('show');
    });

    // Event delegation for Delete buttons with SweetAlert2 Confirmation
    $('#driversTable').on('click', '.delete-btn', function() {
        let driverId = $(this).data('id');
        let driverName = $(this).data('name');

        Swal.fire({
            title: 'Delete ' + driverName + '?',
            text: "This action is permanent and will remove the driver profile from the system.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                let deleteUrl = "{{ route('drivers.destroy', ':id') }}".replace(':id', driverId);
                let form = $('<form>', {
                    method: 'POST',
                    action: deleteUrl
                });
                form.append($('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }));
                form.append($('<input>', { type: 'hidden', name: '_method', value: 'DELETE' }));
                $('body').append(form);
                form.submit();
            }
        });
    });

    // SweetAlert2 Confirmation for Saving New Driver
    $('#createForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Register New Driver?',
            text: "Are you sure you want to save this personnel profile?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, register record!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // SweetAlert2 Confirmation for Editing Driver Profile
    $('#editForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Update Driver Profile?',
            text: "Are you sure you want to save these changes?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, update changes!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Smart Capture File Upload
    const smartBox = document.getElementById('smartCaptureArea');
    const fileInput = document.getElementById('licenseImage');

    smartBox.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async (e) => {
        if(e.target.files.length === 0) return;
        processImageForOCR(e.target.files[0]);
    });

    // Live Webcam Scanner Logic
    let videoStream = null;
    const videoElem = document.getElementById('webcamVideo');

    $('#openCameraBtn').click(async function() {
        $('#cameraModal').modal('show');
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'environment' } 
            });
            videoElem.srcObject = videoStream;
        } catch (err) {
            console.error("Webcam Error:", err);
            toastr.error('Unable to access webcam.');
            $('#cameraModal').modal('hide');
        }
    });

    $('.close-camera-modal').click(function() {
        stopWebcamStream();
        $('#cameraModal').modal('hide');
    });

    function stopWebcamStream() {
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
            videoStream = null;
        }
    }

    $('#takeSnapshotBtn').click(function() {
        if (!videoStream) return;
        const canvas = document.getElementById('snapshotCanvas');
        canvas.width = videoElem.videoWidth || 640;
        canvas.height = videoElem.videoHeight || 480;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElem, 0, 0, canvas.width, canvas.height);

        stopWebcamStream();
        $('#cameraModal').modal('hide');

        canvas.toBlob(function(blob) {
            processImageForOCR(blob);
        }, 'image/jpeg', 0.95);
    });

    // OCR Processing Engine
    const progressBarBox = document.getElementById('scanProgressBox');
    const progressBar = document.getElementById('scanProgressBar');
    const statusText = document.getElementById('scanStatusText');

    async function processImageForOCR(imageSource) {
        progressBarBox.style.display = 'block';
        statusText.classList.remove('d-none');
        statusText.innerText = "Initializing OCR Engine...";
        progressBar.style.width = '15%';

        try {
            const worker = await Tesseract.createWorker("eng");
            progressBar.style.width = '40%';
            statusText.innerText = "Analyzing ID layout...";

            const { data: { text } } = await worker.recognize(imageSource);
            await worker.terminate();

            progressBar.style.width = '85%';
            statusText.innerText = "Extracting details...";
            
            parseOCRText(text);

            progressBar.style.width = '100%';
            setTimeout(() => {
                progressBarBox.style.display = 'none';
                statusText.classList.add('d-none');
                toastr.success('Scan complete! Please review and fill any missing fields.');
            }, 600);

        } catch (error) {
            console.error("OCR Error:", error);
            toastr.error('Could not parse image.');
            progressBarBox.style.display = 'none';
            statusText.classList.add('d-none');
        }
    }

    function parseOCRText(text) {
        const fullCleanText = text.replace(/\s+/g, ' ');

        const licMatch = fullCleanText.match(/[A-Z]\d{2}[-\s]?\d{2}[-\s]?\d{6}/i);
        if (licMatch) {
            let cleanLic = licMatch[0].toUpperCase().replace(/[\s]/g, '-');
            if(!cleanLic.includes('-')) {
                cleanLic = cleanLic.slice(0,3) + '-' + cleanLic.slice(3,5) + '-' + cleanLic.slice(5);
            }
            document.getElementById('c_lic_no').value = cleanLic;
        }

        const dateMatches = fullCleanText.match(/\d{4}[-/]\d{2}[-/]\d{2}/g);
        if (dateMatches && dateMatches.length > 0) {
            let expiry = dateMatches[dateMatches.length - 1].replace(/\//g, '-');
            document.getElementById('c_lic_exp').value = expiry;
        }

        if (fullCleanText.toUpperCase().includes('NON-PROFESSIONAL') || fullCleanText.toUpperCase().includes('NON PROFESSIONAL')) {
            document.getElementById('c_lic_type').value = 'Non-Professional';
        } else if (fullCleanText.toUpperCase().includes('PROFESSIONAL')) {
            document.getElementById('c_lic_type').value = 'Professional';
        }

        const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            const upperLine = line.toUpperCase();

            // Identify the data line by shape, not by requiring the whole line to already
            // be perfect uppercase — OCR case is unreliable, but the shape is consistent:
            // comma-separated, no digits, and explicitly not the field's own caption text
            // ("Last Name, First Name, Middle Name") printed just above the real data.
            const looksLikeNameLine = line.includes(',')
                && !/\d/.test(line)
                && !upperLine.includes('LAST NAME')
                && !upperLine.includes('FIRST NAME')
                && !upperLine.includes('MIDDLE NAME')
                && line.replace(/[^A-Za-z]/g, '').length > 8;

            if (looksLikeNameLine) {
                let parts = line.split(',').map(p => p.trim());
                if (parts.length >= 2) {
                    document.getElementById('c_ln').value = parts[0].replace(/[^a-zA-Z\s]/g, '');

                    // Common placeholder words some licenses print when there's genuinely
                    // no middle name, instead of just leaving the space blank.
                    const noMiddleNamePlaceholders = new Set(['NONE', 'NIL', 'NA']);

                    let remainingNames = parts[1]
                        .split(' ')
                        .map(p => p.trim())
                        // Filter by length, not case — OCR case varies scan to scan, but a
                        // genuine first/middle name is essentially never 1-2 letters, while
                        // a stray misread fragment (like "ki") usually is exactly that short.
                        .filter(p => /^[A-Za-z]+$/.test(p) && p.length >= 3)
                        .filter(p => !noMiddleNamePlaceholders.has(p.toUpperCase()));

                    if (remainingNames.length > 0) {
                        document.getElementById('c_fn').value = remainingNames[0];
                    }
                    // Explicitly clear (not just "leave alone") when there's no middle name —
                    // otherwise a middle name from a previous scan in the same modal session
                    // would incorrectly linger on a license that doesn't have one.
                    document.getElementById('c_mn').value = remainingNames.length > 1
                        ? remainingNames.slice(1).join(' ')
                        : '';
                    break;
                }
            }
        }
    }
});
</script>
@endsection