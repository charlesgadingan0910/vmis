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
  
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 240px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; width: 170px; }

  .fleet-table { width: 100% !important; margin: 0 !important; }
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; border: none; padding: 14px 24px; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  
  .driver-chip-wrapper { display: flex; align-items: center; gap: 12px; }
  .driver-avatar-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; object-fit: cover; object-position: center; }
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

  /* DL Codes / Restriction Codes checkbox grid */
  .code-checkbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px; }
  @media (max-width: 768px) { .code-checkbox-grid { grid-template-columns: repeat(2, 1fr); } }
  .code-checkbox-item { border: 1.5px solid #e2e8f0; border-radius: 9px; padding: 8px 10px; display: flex; align-items: flex-start; gap: 8px; cursor: pointer; transition: all 0.15s; background: #fff; }
  .code-checkbox-item:hover { border-color: #93c5fd; background: #f8fafc; }
  .code-checkbox-item input[type="checkbox"] { margin-top: 3px; flex-shrink: 0; }
  .code-checkbox-item .code-label { font-weight: 800; font-size: 12.5px; color: #0f172a; }
  .code-checkbox-item .code-desc { font-size: 10.5px; color: #64748b; line-height: 1.3; display: block; }

  .smart-capture-container { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; }
  @media (max-width: 768px) { .smart-capture-container { grid-template-columns: 1fr; } }
  
  .smart-capture-box { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; background: #f8fafc; transition: all 0.2s; cursor: pointer; }
  .smart-capture-box:hover { border-color: #3b82f6; background: #eff6ff; }
  .smart-capture-icon { font-size: 24px; color: #3b82f6; margin-bottom: 6px; }
  .progress-bar-scanner { height: 6px; border-radius: 3px; background: #e2e8f0; overflow: hidden; margin-top: 10px; display: none; }
  .progress-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #3b82f6, #1d4ed8); transition: width 0.2s; }

  /* Driver photo capture */
  #photoPreviewWrap { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; background: #f1f5f9; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; flex: none; }
  #photoPreviewImg { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }

  /* ============================================================ */
  /* MOBILE: table rows become stacked cards, not a cramped scroll */
  /* ============================================================ */
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
    .fleet-table td:last-child { padding-bottom: 14px !important; }

    /* Column order is fixed (Personnel Identity, License Detail, Expiration
       Status, Contact Info, Actions) — Identity is already self-descriptive
       (avatar + name + status inline), so it skips a label; the rest get one
       so a bare phone number or date doesn't sit there with no context. */
    .fleet-table td:nth-child(2)::before { content: "License"; }
    .fleet-table td:nth-child(3)::before { content: "Expiration"; }
    .fleet-table td:nth-child(4)::before { content: "Contact"; }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }

    .fleet-table td:last-child {
      text-align: right !important; border-top: 1px dashed #eef1f6 !important; margin-top: 2px;
    }
  }
</style>
@endsection

@section('nav-title', 'VMIS | Drivers Directory')

@section('nav-actions')
<button class="btn-nav-action btn-nav-action-primary" data-toggle="modal" data-target="#createModal">
  <i class="fas fa-plus"></i> <span class="btn-label">Register Driver</span>
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

<!-- CREATE MODAL WITH SMART CAPTURE, DRIVER PHOTO & LIVE WEBCAM -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-user-plus mr-2 text-primary"></i> Register Driver</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('drivers.store') }}" method="POST" id="createForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    
                    <p class="text-uppercase font-weight-bold font-size-11 text-muted mb-1" style="letter-spacing:0.5px;">Front of License</p>
                    <div class="smart-capture-container">
                        <div class="smart-capture-box" id="smartCaptureArea">
                            <i class="fas fa-upload smart-capture-icon"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Upload Front Image</h6>
                            <p class="text-muted font-size-11 mb-0">Auto-fills name, license no. &amp; DL Codes/Conditions</p>
                            <input type="file" id="licenseImage" accept="image/*" class="d-none">
                        </div>

                        <div class="smart-capture-box" id="openCameraBtn">
                            <i class="fas fa-camera smart-capture-icon text-success"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Live Camera Scan</h6>
                            <p class="text-muted font-size-11 mb-0">Capture card via webcam</p>
                        </div>
                    </div>

                    <p class="text-uppercase font-weight-bold font-size-11 text-muted mb-1" style="letter-spacing:0.5px;">Back of License</p>
                    <div class="smart-capture-container">
                        <div class="smart-capture-box" id="smartCaptureAreaBack">
                            <i class="fas fa-upload smart-capture-icon"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Upload Back Image</h6>
                            <p class="text-muted font-size-11 mb-0">Auto-fills serial no. &amp; emergency contact</p>
                            <input type="file" id="licenseImageBack" accept="image/*" class="d-none">
                        </div>

                        <div class="smart-capture-box" id="openCameraBtnBack">
                            <i class="fas fa-camera smart-capture-icon text-success"></i>
                            <h6 class="font-weight-bold text-dark m-0" style="font-size:13.5px;">Live Camera Scan</h6>
                            <p class="text-muted font-size-11 mb-0">Capture card via webcam</p>
                        </div>
                    </div>

                    <div class="progress-bar-scanner mb-3" id="scanProgressBox">
                        <div class="progress-bar-fill" id="scanProgressBar"></div>
                    </div>
                    <div id="scanStatusText" class="font-size-11 text-primary mb-3 font-weight-bold text-center d-none">Scanning document...</div>
                    <p class="font-size-11 text-muted mb-3" style="margin-top:-10px;"><i class="fas fa-info-circle mr-1"></i>Scan <strong>both sides</strong> for everything to auto-fill: the <strong>front</strong> fills Name/License No./DL Codes/Conditions — the <strong>back</strong> only fills Serial No. &amp; Emergency Contact (its DL Codes/Conditions text is just a generic legend, same on every license). Please double-check the auto-filled fields before saving.</p>

                    <div class="modal-section-title mt-0">Driver Photo</div>
                    <div class="d-flex align-items-center mb-2" style="gap:16px;">
                        <div id="photoPreviewWrap">
                            <i class="fas fa-user text-muted" id="photoPreviewPlaceholder" style="font-size:28px;"></i>
                            <img id="photoPreviewImg" class="d-none" alt="Driver photo preview">
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap" style="gap:8px;">
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold" id="photoLiveCaptureBtn"><i class="fas fa-camera mr-1 text-success"></i> Live Capture</button>
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold" id="photoUploadBtn"><i class="fas fa-upload mr-1 text-primary"></i> Upload</button>
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold d-none" id="photoClearBtn"><i class="fas fa-times mr-1 text-danger"></i> Clear</button>
                            </div>
                            <small class="text-muted d-block mt-2" id="photoSourceNote">No photo yet — auto-captured from the license scan, or add one manually.</small>
                            <input type="file" id="photoUploadInput" accept="image/*" class="d-none">
                        </div>
                    </div>
                    <input type="file" name="photo" id="c_photo" class="d-none">

                    <div class="modal-section-title">Personal Information</div>
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Rank <span class="text-danger">*</span></label>
                            <select name="rank" id="c_rank" class="form-control form-control-modern" required>
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
                            <label class="font-weight-bold font-size-12 text-secondary">License Type <span class="text-danger">*</span></label>
                            <select name="license_type" id="c_lic_type" class="form-control form-control-modern" required>
                                <option value="">Select Type...</option>
                                <option value="Professional">Professional</option>
                                <option value="Non-Professional">Non-Professional</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="contact_number" id="c_contact" class="form-control form-control-modern" placeholder="+63 9..." required>
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control form-control-modern" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">License Serial Number</label>
                            <input type="text" name="license_serial_number" id="c_lic_serial" class="form-control form-control-modern" placeholder="Card serial no.">
                        </div>
                    </div>

                    <div class="modal-section-title">DL Codes <small class="text-muted" style="text-transform:none; font-weight:600; letter-spacing:normal;">(vehicle classes authorized)</small></div>
                    <div class="code-checkbox-grid">
                        @foreach($dlCodes as $code => $desc)
                        <label class="code-checkbox-item">
                            <input type="checkbox" class="c-dl-code" name="dl_codes[]" value="{{ $code }}">
                            <span><span class="code-label">{{ $code }}</span><span class="code-desc">{{ $desc }}</span></span>
                        </label>
                        @endforeach
                    </div>

                    <div class="modal-section-title">Driving Conditions / Restrictions</div>
                    <div class="code-checkbox-grid">
                        @foreach($restrictionCodes as $code => $desc)
                        <label class="code-checkbox-item">
                            <input type="checkbox" class="c-restriction-code" name="restriction_codes[]" value="{{ $code }}">
                            <span><span class="code-label">{{ $code }}</span><span class="code-desc">{{ $desc }}</span></span>
                        </label>
                        @endforeach
                    </div>

                    <div class="modal-section-title">Emergency Contact</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Name</label>
                            <input type="text" name="emergency_contact_name" id="c_ec_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-5 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Address</label>
                            <input type="text" name="emergency_contact_address" id="c_ec_address" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Tel No.</label>
                            <input type="text" name="emergency_contact_number" id="c_ec_number" class="form-control form-control-modern">
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

<!-- LIVE WEBCAM MODAL (license scanner) -->
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

<!-- LIVE PHOTO CAPTURE MODAL (driver profile photo — separate from the license scanner above) -->
<div class="modal fade" id="photoCameraModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-camera mr-2 text-success"></i> Capture Driver Photo</h5>
                <button type="button" class="close text-white close-photo-camera-modal">&times;</button>
            </div>
            <div class="modal-body text-center p-3 bg-black">
                <div style="position:relative; width:100%; max-height:360px; overflow:hidden; background:#000; border-radius:10px;">
                    <video id="photoWebcamVideo" autoplay playsinline style="width:100%; height:auto; display:block;"></video>
                </div>
                <p class="text-muted font-size-12 mt-2 mb-0">Center the driver's face and click capture.</p>
            </div>
            <div class="modal-footer justify-content-between bg-light border-0">
                <button type="button" class="btn btn-light font-weight-bold close-photo-camera-modal">Cancel</button>
                <button type="button" id="takePhotoSnapshotBtn" class="btn btn-success font-weight-bold px-4">
                    <i class="fas fa-camera mr-1"></i> Capture
                </button>
            </div>
        </div>
    </div>
</div>
<canvas id="photoSnapshotCanvas" class="d-none"></canvas>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-user-edit mr-2 text-primary"></i> Update Profile</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-body p-4">
                    <div class="modal-section-title mt-0">Driver Photo</div>
                    <div class="d-flex align-items-center mb-2" style="gap:16px;">
                        <div id="editPhotoPreviewWrap" style="width:84px;height:84px;border-radius:50%;overflow:hidden;background:#f1f5f9;border:2px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;flex:none;">
                            <i class="fas fa-user text-muted" id="editPhotoPreviewPlaceholder" style="font-size:28px;"></i>
                            <img id="editPhotoPreviewImg" class="d-none" style="width:100%;height:100%;object-fit:cover;object-position:center;display:block;" alt="Driver photo preview">
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap" style="gap:8px;">
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold" id="editPhotoLiveCaptureBtn"><i class="fas fa-camera mr-1 text-success"></i> Live Capture</button>
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold" id="editPhotoUploadBtn"><i class="fas fa-upload mr-1 text-primary"></i> Upload</button>
                                <button type="button" class="btn btn-sm btn-light border font-weight-bold d-none" id="editPhotoClearBtn"><i class="fas fa-times mr-1 text-danger"></i> Remove</button>
                            </div>
                            <small class="text-muted d-block mt-2" id="editPhotoSourceNote">No photo on file.</small>
                            <input type="file" id="editPhotoUploadInput" accept="image/*" class="d-none">
                        </div>
                    </div>
                    <input type="file" name="photo" id="e_photo" class="d-none">
                    <input type="hidden" name="remove_photo" id="e_remove_photo" value="0">

                    <div class="modal-section-title">Personal Information</div>
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Rank <span class="text-danger">*</span></label>
                            <select id="e_rank" name="rank" class="form-control form-control-modern" required>
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
                            <label class="font-weight-bold font-size-12 text-secondary">License Type <span class="text-danger">*</span></label>
                            <select id="e_lic_type" name="license_type" class="form-control form-control-modern" required>
                                <option value="">Select Type...</option>
                                <option value="Professional">Professional</option>
                                <option value="Non-Professional">Non-Professional</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" id="e_contact" name="contact_number" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Status</label>
                            <select id="e_status" name="status" class="form-control form-control-modern" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">License Serial Number</label>
                            <input type="text" id="e_lic_serial" name="license_serial_number" class="form-control form-control-modern" placeholder="Card serial no.">
                        </div>
                    </div>

                    <div class="modal-section-title">DL Codes <small class="text-muted" style="text-transform:none; font-weight:600; letter-spacing:normal;">(vehicle classes authorized)</small></div>
                    <div class="code-checkbox-grid">
                        @foreach($dlCodes as $code => $desc)
                        <label class="code-checkbox-item">
                            <input type="checkbox" class="e-dl-code" name="dl_codes[]" value="{{ $code }}">
                            <span><span class="code-label">{{ $code }}</span><span class="code-desc">{{ $desc }}</span></span>
                        </label>
                        @endforeach
                    </div>

                    <div class="modal-section-title">Driving Conditions / Restrictions</div>
                    <div class="code-checkbox-grid">
                        @foreach($restrictionCodes as $code => $desc)
                        <label class="code-checkbox-item">
                            <input type="checkbox" class="e-restriction-code" name="restriction_codes[]" value="{{ $code }}">
                            <span><span class="code-label">{{ $code }}</span><span class="code-desc">{{ $desc }}</span></span>
                        </label>
                        @endforeach
                    </div>

                    <div class="modal-section-title">Emergency Contact</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Name</label>
                            <input type="text" id="e_ec_name" name="emergency_contact_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-5 form-group">
                            <label class="font-weight-bold font-size-12 text-secondary">Address</label>
                            <input type="text" id="e_ec_address" name="emergency_contact_address" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group mb-0">
                            <label class="font-weight-bold font-size-12 text-secondary">Tel No.</label>
                            <input type="text" id="e_ec_number" name="emergency_contact_number" class="form-control form-control-modern">
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
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
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
        // See vehicles/index.blade.php for why this matters: without it,
        // DataTables locks the <table> to an inline pixel width at init time,
        // which overrides our CSS width:100% and breaks the mobile "stack
        // into cards" layout below.
        autoWidth: false,
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
        $('#e_lic_serial').val(data.license_serial_number);
        $('#e_ec_name').val(data.emergency_contact_name);
        $('#e_ec_address').val(data.emergency_contact_address);
        $('#e_ec_number').val(data.emergency_contact_number);

        // DL Codes / Restriction Codes: check only the boxes this driver has
        // on file, clearing whatever was left checked from the modal's
        // previous use (the model casts both columns to arrays, so a driver
        // with none on file comes through here as null/[]).
        let dlCodes = data.dl_codes || [];
        let restrictionCodes = data.restriction_codes || [];
        $('.e-dl-code').each(function() {
            $(this).prop('checked', dlCodes.includes($(this).val()));
        });
        $('.e-restriction-code').each(function() {
            $(this).prop('checked', restrictionCodes.includes($(this).val()));
        });

        if(data.license_expiration_date) {
            $('#e_lic_exp').val(data.license_expiration_date.substring(0, 10));
        }

        // Photo: show the current one if this driver has one, otherwise a clean
        // empty state — either way, no pending file/removal flag carries over
        // from whatever was last open in this modal.
        if (data.photo_path) {
            let photoUrl = "{{ route('drivers.photo', ':id') }}".replace(':id', data.id);
            document.getElementById('e_photo').value = '';
            document.getElementById('e_remove_photo').value = '0';
            document.getElementById('editPhotoPreviewImg').src = photoUrl;
            document.getElementById('editPhotoPreviewImg').classList.remove('d-none');
            document.getElementById('editPhotoPreviewPlaceholder').classList.add('d-none');
            document.getElementById('editPhotoClearBtn').classList.remove('d-none');
            document.getElementById('editPhotoSourceNote').innerText = 'Current profile photo on file.';
        } else {
            resetEditPhotoWidgetEmpty();
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

    // Smart Capture File Upload — Front of License
    const smartBox = document.getElementById('smartCaptureArea');
    const fileInput = document.getElementById('licenseImage');

    smartBox.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async (e) => {
        if(e.target.files.length === 0) return;
        processImageForOCR(e.target.files[0], 'front');
    });

    // Smart Capture File Upload — Back of License (serial no. & emergency contact only;
    // DL Codes/Conditions aren't reliably OCR-detectable as checked/unchecked, so those
    // stay manual).
    const smartBoxBack = document.getElementById('smartCaptureAreaBack');
    const fileInputBack = document.getElementById('licenseImageBack');

    smartBoxBack.addEventListener('click', () => fileInputBack.click());

    fileInputBack.addEventListener('change', async (e) => {
        if(e.target.files.length === 0) return;
        processImageForOCR(e.target.files[0], 'back');
    });

    // Live Webcam Scanner Logic (license) — shared by the front and back capture
    // tiles; `scanTarget` records which side was requested so the snapshot handler
    // knows which parser to run.
    let videoStream = null;
    let scanTarget = 'front';
    const videoElem = document.getElementById('webcamVideo');

    async function startLicenseWebcam() {
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
    }

    $('#openCameraBtn').click(function() {
        scanTarget = 'front';
        startLicenseWebcam();
    });

    $('#openCameraBtnBack').click(function() {
        scanTarget = 'back';
        startLicenseWebcam();
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

        let capturedTarget = scanTarget;
        canvas.toBlob(function(blob) {
            processImageForOCR(blob, capturedTarget);
        }, 'image/jpeg', 0.95);
    });

    // ---------------- Driver Photo Capture (shared between Create & Edit modals) ----------------
    let photoManuallySet = false;       // Create-only: blocks auto-crop from overwriting a deliberate choice
    let photoWebcamStream = null;
    let activePhotoTarget = 'create';   // which modal's photo widget Live Capture is currently acting on
    const photoVideoElem = document.getElementById('photoWebcamVideo');

    // Every DOM id the photo widget touches, keyed by which modal it belongs to —
    // lets setDriverPhoto/clearDriverPhoto work for both without duplicating logic.
    const photoTargets = {
        create: {
            fileInput: 'c_photo',
            previewImg: 'photoPreviewImg',
            placeholder: 'photoPreviewPlaceholder',
            clearBtn: 'photoClearBtn',
            note: 'photoSourceNote',
        },
        edit: {
            fileInput: 'e_photo',
            previewImg: 'editPhotoPreviewImg',
            placeholder: 'editPhotoPreviewPlaceholder',
            clearBtn: 'editPhotoClearBtn',
            note: 'editPhotoSourceNote',
        },
    };

    function setDriverPhoto(blob, source, detail, target) {
        target = target || 'create';
        const t = photoTargets[target];

        const file = new File([blob], 'driver-photo.jpg', { type: blob.type || 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById(t.fileInput).files = dt.files;

        const url = URL.createObjectURL(blob);
        document.getElementById(t.previewImg).src = url;
        document.getElementById(t.previewImg).classList.remove('d-none');
        document.getElementById(t.placeholder).classList.add('d-none');
        document.getElementById(t.clearBtn).classList.remove('d-none');

        const faceDetected = detail && detail.faceDetected;
        document.getElementById(t.note).innerText =
            source === 'auto' ? (faceDetected ? 'Face detected — auto-cropped from the license scan.' : 'Approximate crop (no face clearly detected) — try Live Capture or Upload for a tighter result.') :
            source === 'live' ? (faceDetected ? 'Face detected and centered from the live capture.' : 'Captured via live camera (face not clearly detected — consider recapturing).') :
            'Uploaded manually.';

        if (target === 'create' && source !== 'auto') { photoManuallySet = true; }
        if (target === 'edit') { document.getElementById('e_remove_photo').value = '0'; }
    }

    // User explicitly removing a photo — for Edit, this also flags the existing
    // server-side file for deletion on save (setDriverPhoto with a fresh capture
    // clears that flag again, since a new photo always wins over a removal).
    function clearDriverPhoto(target) {
        target = target || 'create';
        const t = photoTargets[target];

        document.getElementById(t.fileInput).value = '';
        document.getElementById(t.previewImg).src = '';
        document.getElementById(t.previewImg).classList.add('d-none');
        document.getElementById(t.placeholder).classList.remove('d-none');
        document.getElementById(t.clearBtn).classList.add('d-none');
        document.getElementById(t.note).innerText = target === 'edit'
            ? 'No photo on file.'
            : 'No photo yet — auto-captured from the license scan, or add one manually.';

        if (target === 'create') {
            photoManuallySet = false;
        } else {
            document.getElementById('e_remove_photo').value = '1';
        }
    }

    // Quietly resets the Edit modal's photo widget when it's populated for a driver
    // that has no photo on file yet — distinct from clearDriverPhoto('edit') because
    // this must NOT flag an existing photo for removal (there isn't one).
    function resetEditPhotoWidgetEmpty() {
        document.getElementById('e_photo').value = '';
        document.getElementById('e_remove_photo').value = '0';
        const t = photoTargets.edit;
        document.getElementById(t.previewImg).src = '';
        document.getElementById(t.previewImg).classList.add('d-none');
        document.getElementById(t.placeholder).classList.remove('d-none');
        document.getElementById(t.clearBtn).classList.add('d-none');
        document.getElementById(t.note).innerText = 'No photo on file — add one via Live Capture or Upload.';
    }

    document.getElementById('photoClearBtn').addEventListener('click', () => clearDriverPhoto('create'));
    document.getElementById('editPhotoClearBtn').addEventListener('click', () => clearDriverPhoto('edit'));

    // Manual upload — one file input per modal, both routed through the same setDriverPhoto.
    document.getElementById('photoUploadBtn').addEventListener('click', () => document.getElementById('photoUploadInput').click());
    document.getElementById('photoUploadInput').addEventListener('change', function (e) {
        if (e.target.files.length === 0) return;
        setDriverPhoto(e.target.files[0], 'upload', null, 'create');
    });

    document.getElementById('editPhotoUploadBtn').addEventListener('click', () => document.getElementById('editPhotoUploadInput').click());
    document.getElementById('editPhotoUploadInput').addEventListener('change', function (e) {
        if (e.target.files.length === 0) return;
        setDriverPhoto(e.target.files[0], 'upload', null, 'edit');
    });

    // Live capture — a single shared camera/modal for both Create and Edit (and
    // separate from the license scanner above, so capturing a face photo never
    // accidentally triggers OCR). activePhotoTarget records which modal asked.
    async function openPhotoCameraModal(target) {
        activePhotoTarget = target;
        $('#photoCameraModal').modal('show');
        try {
            photoWebcamStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
            });
            photoVideoElem.srcObject = photoWebcamStream;
        } catch (err) {
            console.error('Photo webcam error:', err);
            toastr.error('Unable to access webcam.');
            $('#photoCameraModal').modal('hide');
        }
    }

    document.getElementById('photoLiveCaptureBtn').addEventListener('click', () => openPhotoCameraModal('create'));
    document.getElementById('editPhotoLiveCaptureBtn').addEventListener('click', () => openPhotoCameraModal('edit'));

    $('.close-photo-camera-modal').click(function () {
        stopPhotoWebcamStream();
        $('#photoCameraModal').modal('hide');
    });

    function stopPhotoWebcamStream() {
        if (photoWebcamStream) {
            photoWebcamStream.getTracks().forEach(track => track.stop());
            photoWebcamStream = null;
        }
    }

    $('#takePhotoSnapshotBtn').click(function () {
        if (!photoWebcamStream) return;
        const canvas = document.getElementById('photoSnapshotCanvas');
        canvas.width = photoVideoElem.videoWidth || 640;
        canvas.height = photoVideoElem.videoHeight || 480;
        canvas.getContext('2d').drawImage(photoVideoElem, 0, 0, canvas.width, canvas.height);

        stopPhotoWebcamStream();
        $('#photoCameraModal').modal('hide');

        const target = activePhotoTarget;
        canvas.toBlob(function (blob) {
            extractFaceSquare(blob)
                .then(function (result) { setDriverPhoto(result.blob, 'live', result, target); })
                .catch(function () { setDriverPhoto(blob, 'live', { faceDetected: false }, target); }); // raw snapshot if detection itself errors
        }, 'image/jpeg', 0.92);
    });

    // ---------------- Real face detection (face-api.js) ----------------
    // Replaces the old fixed-position guess with an actual detected face bounding box,
    // so the crop centers on where the face really is instead of an assumed position.
    // TinyFaceDetector is used deliberately — it's small (~190KB) and fast, and all we
    // need is a bounding box, not full landmarks/recognition.
    let faceApiModelsLoaded = false;
    let faceApiModelsLoading = null;

    function ensureFaceApiModelsLoaded() {
        if (faceApiModelsLoaded) return Promise.resolve();
        if (faceApiModelsLoading) return faceApiModelsLoading;

        const MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
        faceApiModelsLoading = faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL)
            .then(function () { faceApiModelsLoaded = true; })
            .catch(function (err) {
                console.warn('Face detection models failed to load — falling back to approximate crop.', err);
                faceApiModelsLoading = null; // allow a retry on the next scan/capture
            });
        return faceApiModelsLoading;
    }

    // Kick off model loading in the background as soon as the page is ready, so it's
    // very likely already loaded by the time someone actually scans or captures —
    // avoids a visible delay on the first use.
    ensureFaceApiModelsLoaded();

    function loadImageElement(imageSource) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            const objectUrl = URL.createObjectURL(imageSource);
            img.onload = function () {
                resolve(img);
                setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 3000);
            };
            img.onerror = function (e) {
                URL.revokeObjectURL(objectUrl);
                reject(e);
            };
            img.src = objectUrl;
        });
    }

    function detectFaceBox(img) {
        if (typeof faceapi === 'undefined' || !faceApiModelsLoaded) return Promise.resolve(null);
        return faceapi
            .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 }))
            .then(function (detection) { return detection ? detection.box : null; })
            .catch(function () { return null; });
    }

    function buildCroppedBlob(img, box) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const OUTPUT_SIZE = 360; // fixed output resolution — keeps stored photos a consistent size
        canvas.width = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;

        if (box) {
            // Pad around the detected face for a proper ID-photo framing (head + a
            // little shoulder room) — NOT the whole card. 0.35x padding on each side
            // gives a final crop roughly 1.7x the face width, which is the actual
            // culprit behind the "captures the whole card" issue: the previous 0.9x
            // padding produced a crop nearly 3x the face width, pulling in most of
            // the card's surrounding text/background along with the face.
            const padding = box.width * 0.35;
            const cropSize = Math.min(img.width, img.height, box.width + padding * 2);
            let cropX = box.x + box.width / 2 - cropSize / 2;
            let cropY = box.y + box.height / 2 - cropSize / 2;
            cropX = Math.max(0, Math.min(cropX, img.width - cropSize));
            cropY = Math.max(0, Math.min(cropY, img.height - cropSize));
            ctx.drawImage(img, cropX, cropY, cropSize, cropSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        } else {
            // No face detected (models still loading, or genuinely no face found) —
            // fall back to the old approximate fixed-position crop as a safety net
            // rather than leaving the photo empty.
            const squareSize = Math.min(img.width * 0.34, img.height * 0.58);
            const cropX = Math.max(img.width * 0.05, 0);
            const cropY = Math.max((img.height * 0.53) - (squareSize / 2), 0);
            ctx.drawImage(img, cropX, cropY, squareSize, squareSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        }

        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) { resolve(blob); } else { reject(new Error('Crop failed')); }
            }, 'image/jpeg', 0.92);
        });
    }

    // Used by both the license auto-crop and Live Capture — loads the image, tries to
    // detect a real face, and crops around it. Resolves { blob, faceDetected } so the
    // caller can tell the user whether a face was actually found or we fell back.
    function extractFaceSquare(imageSource) {
        return loadImageElement(imageSource).then(function (img) {
            return ensureFaceApiModelsLoaded()
                .then(function () { return detectFaceBox(img); })
                .then(function (box) {
                    return buildCroppedBlob(img, box).then(function (blob) {
                        return { blob: blob, faceDetected: !!box };
                    });
                });
        });
    }

    // Resets the whole Register Driver form, including photo state — used by the
    // duplicate-license alert below, and automatically every time the modal closes,
    // so a previous registration's leftover data never bleeds into the next one.
    function resetCreateForm() {
        document.getElementById('createForm').reset();
        clearDriverPhoto('create');
    }

    $('#createModal').on('hidden.bs.modal', resetCreateForm);

    // ---------------- Live duplicate check (fires right after OCR reads a license number) ----------------
    function checkLicenseDuplicate(licenseNumber) {
        if (!licenseNumber) return;
        $.post("{{ route('drivers.check-availability') }}", { license_number: licenseNumber })
            .done(function (res) {
                if (res.exists) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Driver Already Registered',
                        html: 'This license number is already on file for <b>' + res.driver_name + '</b>.',
                        confirmButtonText: 'OK, Clear Form',
                        confirmButtonColor: '#3b82f6',
                        customClass: { popup: 'modal-content-premium' }
                    }).then(function () {
                        resetCreateForm();
                    });
                }
            });
    }

    // OCR Processing Engine
    const progressBarBox = document.getElementById('scanProgressBox');
    const progressBar = document.getElementById('scanProgressBar');
    const statusText = document.getElementById('scanStatusText');

    async function processImageForOCR(imageSource, side = 'front') {
        progressBarBox.style.display = 'block';
        statusText.classList.remove('d-none');
        statusText.innerText = "Initializing OCR Engine...";
        progressBar.style.width = '15%';

        try {
            const worker = await Tesseract.createWorker("eng");
            // The license's printed fields (codes, names, numbers) aren't
            // natural-language prose, so Tesseract's English dictionary
            // "correcting" a short all-caps token (like the DL code "BE")
            // into a real word it knows works against us here — turn that
            // correction off for more literal character recognition.
            await worker.setParameters({
                load_system_dawg: '0',
                load_freq_dawg: '0',
            });

            progressBar.style.width = '25%';
            statusText.innerText = "Enhancing image for OCR...";
            // Grayscale + contrast stretch reads noticeably better than a raw
            // color phone photo on a busy, watermarked card background — falls
            // back to the original image if preprocessing itself fails.
            const ocrSource = await preprocessImageForOCR(imageSource).catch(() => imageSource);

            progressBar.style.width = '40%';
            statusText.innerText = side === 'back' ? "Analyzing back of license..." : "Analyzing ID layout...";

            const { data: { text } } = await worker.recognize(ocrSource);

            progressBar.style.width = '85%';
            statusText.innerText = "Extracting details...";

            if (side === 'back') {
                // Logged (not shown to the user) so a failed extraction can be
                // debugged from the raw OCR text via the browser console,
                // instead of guessing blind at why a field didn't fill.
                console.log('[Back scan OCR text, 0°]:', text);
                parseBackOCRText(text);

                // On this card layout, the Serial Number/DL Codes legend block
                // and the Emergency Contact block are printed at 90° to each
                // other — so in any one photo, at most one of them is actually
                // horizontal and OCR-readable; the other reads as garbage.
                // Retry on the same image rotated both ways and fill in
                // whichever of the two blocks is still missing from whichever
                // rotation reads cleanly (parseBackOCRText only fills blank
                // fields, so a later pass never overwrites a good earlier read).
                const backFieldsIncomplete = () =>
                    !document.getElementById('c_lic_serial').value ||
                    (!document.getElementById('c_ec_name').value &&
                     !document.getElementById('c_ec_address').value &&
                     !document.getElementById('c_ec_number').value);

                if (backFieldsIncomplete()) {
                    statusText.innerText = "Checking a sideways-printed section...";
                    for (const degrees of [90, 270]) {
                        if (!backFieldsIncomplete()) break;
                        try {
                            const rotatedBlob = await rotateImageBlob(ocrSource, degrees);
                            const { data: { text: rotatedText } } = await worker.recognize(rotatedBlob);
                            console.log('[Back scan OCR text, ' + degrees + '°]:', rotatedText);
                            parseBackOCRText(rotatedText);
                        } catch (rotateErr) {
                            console.error('Rotated OCR pass failed:', rotateErr);
                        }
                    }
                }
            } else {
                // Logged (not shown to the user) so a failed DL Codes/Conditions
                // extraction can be debugged from the raw OCR text via the
                // browser console, instead of guessing blind at why it didn't fill.
                console.log('[Front scan OCR text]:', text);
                parseOCRText(text);

                if (!photoManuallySet) {
                    extractFaceSquare(imageSource)
                        .then(result => setDriverPhoto(result.blob, 'auto', result, 'create'))
                        .catch(() => { /* auto-crop is best-effort; silently skip on failure */ });
                }
            }

            await worker.terminate();

            progressBar.style.width = '100%';
            setTimeout(() => {
                progressBarBox.style.display = 'none';
                statusText.classList.add('d-none');
                toastr.success(side === 'back'
                    ? 'Back scan complete! Please review the serial number and emergency contact fields.'
                    : 'Scan complete! Please review the fields and the auto-checked DL Codes/Conditions below.');
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
            checkLicenseDuplicate(cleanLic);
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

        // DL Codes & Conditions — on the actual card these are printed on the
        // FRONT next to Blood Type/Eyes Color as plain values in a two-column
        // row ("DL Codes" | "Conditions" header, then "A,A1,B,B1,B2" | "NONE"
        // values beneath). The back of the card only carries the generic code
        // legend/key, which is identical on every license and can't tell us
        // which codes THIS driver actually holds — that's why this lives in
        // the front parser, not the back one.
        //
        // Real-world OCR output on this font/size routinely drops the commas
        // between codes entirely (e.g. "A,A1,B,B1,B2" reads as "AA1BB2"), so
        // this can't require a comma-separated list. Instead, search a window
        // right after the "DL Codes" label for the longest run of back-to-back
        // valid codes — commas/spaces are allowed *between* codes within a
        // run (so a clean comma-separated read still works), but any other
        // character ends the run. That naturally outscores a stray 1-letter
        // coincidental match from surrounding OCR noise (e.g. the "C" that
        // starts "CONDITIONS" itself, which is masked out below for exactly
        // that reason), since a real code list runs several codes deep.
        // Re-detected on every front scan, so clear stale checks first.
        document.querySelectorAll('.c-dl-code').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('.c-restriction-code').forEach(cb => { cb.checked = false; });

        const knownDlCodes = @json(array_keys($dlCodes));
        const sortedDlCodes = [...knownDlCodes].sort((a, b) => b.length - a.length);

        // Scans a window for the longest run of back-to-back valid codes,
        // allowing comma/space between codes within a run (see the comment
        // above) — factored out so it can be tried against both the raw
        // window text and an OCR-confusable-normalized variant below.
        function decodeBestCodeRun(windowText) {
            let bestCodes = [];
            let bestEndIndex = -1;
            for (let start = 0; start < windowText.length; start++) {
                let i = start;
                const codes = [];
                while (i < windowText.length) {
                    if (codes.length > 0 && /[,\s]/.test(windowText[i])) {
                        i++;
                        continue;
                    }
                    let matched = false;
                    for (const code of sortedDlCodes) {
                        if (windowText.substr(i, code.length) === code) {
                            codes.push(code);
                            i += code.length;
                            matched = true;
                            break;
                        }
                    }
                    if (!matched) break;
                }
                if (codes.length > bestCodes.length) {
                    bestCodes = codes;
                    bestEndIndex = i;
                }
            }
            return { bestCodes, bestEndIndex };
        }

        const dlLabelMatch = fullCleanText.match(/DL\s*CODES?/i);
        if (dlLabelMatch) {
            const searchStart = dlLabelMatch.index + dlLabelMatch[0].length;
            let windowText = fullCleanText.slice(searchStart, searchStart + 70).toUpperCase();
            // Mask the "Conditions" label itself out of the window (same-length
            // space replacement to keep character offsets intact) so its own
            // "C" doesn't get mistaken for the single-letter DL code "C".
            windowText = windowText.replace(/CONDITIONS?/g, (m) => ' '.repeat(m.length));

            // "A1" is a common OCR misread as "AI" or "Al" (capital I / lowercase
            // L instead of the digit 1) — try a normalized variant too and keep
            // whichever decodes more codes, so a clean read never gets displaced
            // by a normalization that wasn't needed.
            const normalizedWindowText = windowText.replace(/[IL]/g, '1');
            const rawResult = decodeBestCodeRun(windowText);
            const normalizedResult = decodeBestCodeRun(normalizedWindowText);
            const { bestCodes, bestEndIndex: bestEndOffset } =
                normalizedResult.bestCodes.length > rawResult.bestCodes.length ? normalizedResult : rawResult;
            const bestEndIndex = bestEndOffset !== -1 ? searchStart + bestEndOffset : -1;

            if (bestCodes.length > 0) {
                document.querySelectorAll('.c-dl-code').forEach(cb => {
                    if (bestCodes.includes(cb.value)) cb.checked = true;
                });
            }

            if (bestEndIndex !== -1) {
                const afterCodes = fullCleanText.slice(bestEndIndex, bestEndIndex + 20);
                if (!/^\s*(NONE|N\/A|NIL)/i.test(afterCodes)) {
                    // Same comma-dropping risk applies to Conditions (e.g. "1,4"
                    // reading as "14") — pull out whichever digits appear right
                    // after the codes, comma-separated or not, and split them
                    // into individual condition numbers either way.
                    const condRunMatch = afterCodes.match(/\d[\d,\s]{0,8}\d|\d/);
                    if (condRunMatch) {
                        const foundConditions = condRunMatch[0].replace(/[^\d]/g, '').split('').filter(t => ['1','2','3','4','5'].includes(t));
                        document.querySelectorAll('.c-restriction-code').forEach(cb => {
                            if (foundConditions.includes(cb.value)) cb.checked = true;
                        });
                    }
                }
            }
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

    /**
     * Grayscale + linear contrast stretch via canvas — a plain color phone
     * photo of a card with a busy watermarked/patterned background is
     * noticeably harder for OCR to read than a cleaned-up, high-contrast
     * version of the same shot. Used for every OCR pass; the original color
     * image is kept separately for the driver-photo auto-crop.
     */
    function preprocessImageForOCR(imageSource) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(imageSource);
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                const grays = new Uint8ClampedArray(data.length / 4);
                let min = 255, max = 0;

                for (let p = 0; p < data.length; p += 4) {
                    const gray = 0.299 * data[p] + 0.587 * data[p + 1] + 0.114 * data[p + 2];
                    grays[p / 4] = gray;
                    if (gray < min) min = gray;
                    if (gray > max) max = gray;
                }

                const range = Math.max(max - min, 1); // avoid divide-by-zero on a flat/blank image
                for (let p = 0; p < data.length; p += 4) {
                    const stretched = ((grays[p / 4] - min) / range) * 255;
                    data[p] = data[p + 1] = data[p + 2] = stretched;
                }
                ctx.putImageData(imageData, 0, 0);

                URL.revokeObjectURL(objectUrl);
                canvas.toBlob((blob) => {
                    blob ? resolve(blob) : reject(new Error('Canvas toBlob failed'));
                }, 'image/jpeg', 0.95);
            };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('Image load failed')); };
            img.src = objectUrl;
        });
    }

    /**
     * Rotates an image (File/Blob) by the given degrees using a canvas, for
     * retrying OCR on back-of-license text that's printed sideways next to
     * the barcode rather than horizontally with the rest of the card.
     */
    function rotateImageBlob(imageSource, degrees) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(imageSource);
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const rotated90or270 = (degrees === 90 || degrees === 270);
                canvas.width = rotated90or270 ? img.naturalHeight : img.naturalWidth;
                canvas.height = rotated90or270 ? img.naturalWidth : img.naturalHeight;

                const ctx = canvas.getContext('2d');
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate(degrees * Math.PI / 180);
                ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);

                URL.revokeObjectURL(objectUrl);
                canvas.toBlob((blob) => {
                    blob ? resolve(blob) : reject(new Error('Canvas toBlob failed'));
                }, 'image/jpeg', 0.95);
            };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('Image load failed')); };
            img.src = objectUrl;
        });
    }

    /**
     * Pulls the License Serial Number from OCR text — shared by the normal
     * back-of-license pass and the rotated retry passes.
     */
    function extractSerialNumber(text) {
        const fullCleanText = text.replace(/\s+/g, ' ');

        // Look for a clean run of digits within a window after the "SERIAL"
        // label, rather than requiring it immediately adjacent — the serial
        // number sits right next to a dense barcode graphic, which routinely
        // gets OCR'd as stray noise between the label and the actual digits.
        const serialIdx = fullCleanText.search(/SERIAL/i);
        if (serialIdx === -1) return null;

        const windowText = fullCleanText.slice(serialIdx, serialIdx + 60);
        const digitsMatch = windowText.match(/\b\d{6,12}\b/);
        return digitsMatch ? digitsMatch[0] : null;
    }

    /**
     * Back-of-license OCR — pulls the License Serial Number and the
     * Emergency Contact block, since those are plain printed text the OCR
     * engine can read reliably. The DL Codes and Conditions/Restrictions
     * boxes are only a generic reference legend on the back (identical on
     * every license, regardless of which codes this driver actually holds)
     * — those are read from the front scan instead (see parseOCRText).
     *
     * Only fills fields that are still blank — this gets called once per
     * image rotation tried (see processImageForOCR), since this card prints
     * the serial/DL-legend block and the emergency contact block at 90° to
     * each other, so only one of them is horizontal/readable in any given
     * photo. Never overwrites a value an earlier, cleaner-reading pass (or
     * the user) already filled in.
     */
    function parseBackOCRText(text) {
        if (!document.getElementById('c_lic_serial').value) {
            const serial = extractSerialNumber(text);
            if (serial) {
                document.getElementById('c_lic_serial').value = serial;
            }
        }

        // Emergency contact is usually printed as labeled lines ("Name:",
        // "Address:", "Tel. No:") under an "In case of emergency" heading —
        // scan line by line and take the first match for each label.
        const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        let gotName = !!document.getElementById('c_ec_name').value;
        let gotAddress = !!document.getElementById('c_ec_address').value;
        let gotNumber = !!document.getElementById('c_ec_number').value;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];

            if (!gotName && /NAME/i.test(line)) {
                const value = line.replace(/.*NAME[:\s\-]*/i, '').trim();
                if (value.length > 1) {
                    document.getElementById('c_ec_name').value = value;
                    gotName = true;
                    continue;
                }
            }
            if (!gotAddress && /ADDRESS/i.test(line)) {
                const value = line.replace(/.*ADDRESS[:\s\-]*/i, '').trim();
                if (value.length > 1) {
                    document.getElementById('c_ec_address').value = value;
                    gotAddress = true;
                    continue;
                }
            }
            if (!gotNumber && /(TEL\.?\s*NO\.?|TELEPHONE|CONTACT\s*NO\.?)/i.test(line)) {
                const value = line.replace(/.*(TEL\.?\s*NO\.?|TELEPHONE|CONTACT\s*NO\.?)[:\s\-]*/i, '').trim();
                if (value.length > 1) {
                    document.getElementById('c_ec_number').value = value;
                    gotNumber = true;
                }
            }
        }
    }
});
</script>
@endsection
