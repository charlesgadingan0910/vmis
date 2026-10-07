@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Maintenance &amp; PMS</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03); display: flex; align-items: center; gap: 16px; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.soon .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.overdue .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  .stat-card-modern.month .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  /* ---------------- Monitoring panel ---------------- */
  .monitor-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
  @media (max-width: 767.98px) { .monitor-panel { grid-template-columns: 1fr; } }
  .monitor-column { background: #ffffff; border-radius: 14px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15,23,42,0.04); overflow: hidden; }
  .monitor-column-header { padding: 14px 18px; font-size: 12.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; }
  .monitor-column.soon .monitor-column-header { color: #d97706; background: #fffbeb; }
  .monitor-column.overdue .monitor-column-header { color: #dc2626; background: #fef2f2; }
  .monitor-column-count { background: rgba(0,0,0,0.06); border-radius: 20px; padding: 2px 10px; font-size: 11px; }
  .monitor-list { max-height: 260px; overflow-y: auto; }
  .monitor-empty { padding: 24px 18px; text-align: center; color: #94a3b8; font-size: 13px; }
  .monitor-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 18px; border-bottom: 1px solid #f8fafc; }
  .monitor-item:last-child { border-bottom: none; }
  .monitor-item-info { min-width: 0; }
  .monitor-item .plate-badge-sm { font-family: 'Courier New', monospace; font-weight: 800; font-size: 11.5px; background: #1e293b; color: #fff; padding: 3px 8px; border-radius: 5px; }
  .monitor-item-name { font-size: 12.5px; color: #64748b; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .monitor-item-days { font-size: 11px; font-weight: 700; white-space: nowrap; }
  .monitor-column.soon .monitor-item-days { color: #d97706; }
  .monitor-column.overdue .monitor-item-days { color: #dc2626; }
  .btn-log-quick { flex-shrink: 0; }

  /* ---------------- Toolbar / table (matches Vehicle Inventory & Driver Management) ---------------- */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6;}
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 200px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 140px; }
  .filter-date-input { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 145px; }

  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none;}
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }
  .plate-badge { font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #1e293b; color: #ffffff; padding: 6px 12px; border-radius: 6px; }
  .vehicle-main-name { font-weight: 700; color: #0f172a; font-size: 14px; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; margin-top: 2px; }

  .due-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; }
  .due-overdue { background: #fcedec; color: #dc2626; }
  .due-soon { background: #fff4e5; color: #d97706; }
  .due-normal { background: #eaf6ef; color: #16a34a; }
  .due-none { background: #f1f5f9; color: #94a3b8; }

  .modal-content-premium { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px 24px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }
  .modal-section-divider { font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; margin: 18px 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid #f1f5f9; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
  textarea.form-control-modern { height: auto; }
  .live-checker-banner { display: none; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; font-weight: 600; align-items: center; gap: 10px; }
  .live-checker-banner.warning { display: flex; background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; }
  .current-odo-hint { font-size: 11.5px; color: #94a3b8; margin-top: 4px; display: block; }
  /* Default select2 height matches the toolbar's plain filter dropdowns (38px) so the
     Vehicle filter doesn't sit taller than its siblings in the same row; the modal forms
     override this back up to 42px to match their own .form-control-modern fields instead. */
  .select2-container .select2-selection--single { height: 38px !important; border: 1.5px solid #e2e8f0 !important; border-radius: 9px !important; }
  .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 13px; }
  .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow { height: 36px !important; }
  .modal-body .select2-container .select2-selection--single { height: 42px !important; }
  .modal-body .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { line-height: 40px !important; font-size: 13.5px; }
  .modal-body .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow { height: 40px !important; }

  /* ---------------- Premium modal enhancements: header subtitle, stage stepper, readiness banner ---------------- */
  .modal-header-slate .mr-header-sub { font-size: 12px; color: #cbd5e1; margin-top: 4px; font-weight: 500; }

  .mr-stage-stepper { display: flex; align-items: flex-start; padding: 16px 2px 8px; }
  .mr-step { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 84px; flex-shrink: 0; }
  .mr-step-dot { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; background: #f1f5f9; color: #94a3b8; border: 2px solid #e2e8f0; transition: all 0.2s ease; }
  .mr-step-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: #94a3b8; text-align: center; line-height: 1.2; }
  .mr-step.done .mr-step-dot { background: #16a34a; border-color: #16a34a; color: #fff; }
  .mr-step.done .mr-step-label { color: #16a34a; }
  .mr-step.active .mr-step-dot { background: #3b82f6; border-color: #3b82f6; color: #fff; box-shadow: 0 0 0 5px rgba(59, 130, 246, 0.15); }
  .mr-step.active .mr-step-label { color: #3b82f6; }
  .mr-step.skipped .mr-step-dot { background: #ffffff; border-color: #e2e8f0; color: #cbd5e1; border-style: dashed; }
  .mr-step.skipped .mr-step-label { color: #cbd5e1; text-decoration: line-through; }
  .mr-step-connector { flex: 1 1 auto; height: 2px; background: #e2e8f0; margin-top: 17px; min-width: 10px; }
  .mr-step-connector.done { background: #16a34a; }
  .mr-stage-meta { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; padding: 2px 2px 16px; margin-bottom: 6px; border-bottom: 1.5px solid #f1f5f9; }
  .mr-control-number { font-size: 12px; font-weight: 700; color: #475569; font-family: 'Courier New', monospace; }
  .mr-blocked-hint { font-size: 11.5px; color: #b45309; font-weight: 600; background: #fffbeb; padding: 3px 10px; border-radius: 20px; }

  .mr-ready-banner { display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; }
  .mr-ready-icon { width: 38px; height: 38px; border-radius: 10px; background: #16a34a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
  .mr-ready-banner-text { font-size: 13.5px; color: #14532d; font-weight: 700; line-height: 1.4; }
  .mr-ready-banner-text small { display: block; font-weight: 500; color: #166534; margin-top: 2px; font-size: 12px; }

  .modal-section-divider i { margin-right: 4px; }

  input[type="file"].form-control-modern { padding: 7px 12px; background: #f8fafc; cursor: pointer; }
  input[type="file"].form-control-modern::file-selector-button { border: none; background: #1e293b; color: #fff; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; margin-right: 12px; cursor: pointer; transition: background 0.15s ease; }
  input[type="file"].form-control-modern::file-selector-button:hover { background: #334155; }

  @media (max-width: 575.98px) {
    .mr-step { width: 64px; }
    .mr-step-dot { width: 28px; height: 28px; font-size: 11px; }
    .mr-step-label { font-size: 8.5px; }
  }

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

    .fleet-table td:nth-child(2)::before { content: "Stage"; }
    .fleet-table td:nth-child(3)::before { content: "Maintenance"; }
    .fleet-table td:nth-child(4)::before { content: "Service Date"; }
    .fleet-table td:nth-child(5)::before { content: "Cost"; }
    .fleet-table td:nth-child(6)::before { content: "Next Due"; }
    .fleet-table td:nth-child(7)::before { content: "Logged By"; }
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

@section('nav-title', 'VMIS | Maintenance & PMS')

@section('nav-actions')
@if (! $isViewer)
<button class="btn btn-primary font-weight-bold shadow-sm" style="border-radius:8px;" data-toggle="modal" data-target="#logMaintenanceModal">
  <i class="fas fa-plus"></i> <span class="btn-label">Log Maintenance</span>
</button>
@endif
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern total">
                <div class="stat-icon-wrapper"><i class="fas fa-clipboard-list"></i></div>
                <div><div class="stat-num-value" id="statTotalRecords">{{ $stats['total_records'] }}</div><div class="stat-label-title">Total Records</div></div>
            </div>
            <div class="stat-card-modern soon">
                <div class="stat-icon-wrapper"><i class="fas fa-clock"></i></div>
                <div><div class="stat-num-value" id="statDueSoon">{{ $stats['due_soon'] }}</div><div class="stat-label-title">Due Soon (14 days)</div></div>
            </div>
            <div class="stat-card-modern overdue">
                <div class="stat-icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-num-value" id="statOverdue">{{ $stats['overdue'] }}</div><div class="stat-label-title">Overdue</div></div>
            </div>
            <div class="stat-card-modern month">
                <div class="stat-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-num-value" id="statServicedMonth">{{ $stats['serviced_month'] }}</div><div class="stat-label-title">Serviced This Month</div></div>
            </div>
        </div>

        <div class="monitor-panel">
            <div class="monitor-column soon">
                <div class="monitor-column-header">
                    <span><i class="fas fa-clock mr-1"></i> Due Soon</span>
                    <span class="monitor-column-count" id="monitorSoonCount">0</span>
                </div>
                <div class="monitor-list" id="monitorSoonList">
                    <div class="monitor-empty">Loading…</div>
                </div>
            </div>
            <div class="monitor-column overdue">
                <div class="monitor-column-header">
                    <span><i class="fas fa-exclamation-triangle mr-1"></i> Overdue</span>
                    <span class="monitor-column-count" id="monitorOverdueCount">0</span>
                </div>
                <div class="monitor-list" id="monitorOverdueList">
                    <div class="monitor-empty">Loading…</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title"><h5>Maintenance Records</h5></div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search...">
                    </div>

                    <select id="filterVehicle" class="custom-filter-select" style="width:200px;">
                        <option value="">All Vehicles</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make.' '.$v->model) }}</option>
                        @endforeach
                    </select>

                    <select id="filterMaintenanceType" class="form-control custom-filter-select" style="width:170px;">
                        <option value="">All Types</option>
                        @foreach (\App\Models\MaintenanceRecord::TYPES as $key => $label)
                            @continue($key === 'REPAIR')
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @if ($hasBroadVisibility)
                        <select id="filterUnit" class="form-control custom-filter-select">
                            <option value="">All Units</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->unit_name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <select id="filterStation" class="form-control custom-filter-select">
                        <option value="">All Stations</option>
                        @foreach ($stations as $s)
                            <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}">{{ $s->station_name }}</option>
                        @endforeach
                    </select>

                    <input type="date" id="filterDateFrom" class="form-control filter-date-input" placeholder="From">
                    <input type="date" id="filterDateTo" class="form-control filter-date-input" placeholder="To">

                    <button type="button" id="resetFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="maintenanceTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Stage</th>
                            <th>Maintenance</th>
                            <th>Service Date</th>
                            <th>Cost</th>
                            <th>Next Due</th>
                            <th>Logged By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@if (! $isViewer)
<!-- LOG MAINTENANCE MODAL -->
<div class="modal fade" id="logMaintenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate">
                <h5><i class="fas fa-tools mr-2 text-primary"></i> Log Maintenance Activity</h5>
            </div>

            <form id="logMaintenanceForm" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div id="logModalErrorBanner" class="live-checker-banner warning"></div>

                    <div class="modal-section-divider mt-0"><i class="fas fa-car"></i> Vehicle & Activity</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="log_vehicle_id" class="form-control" style="width:100%;" required>
                                <option value="">Select vehicle...</option>
                                @foreach ($vehicles as $v)
                                    @php
                                        // Same name-assembly as VehicleController's driverHtml, so the
                                        // name that auto-fills here matches the one shown everywhere
                                        // else in the app for this driver.
                                        $driverName = '';
                                        if ($v->driver) {
                                            $mi = $v->driver->middlename ? strtoupper(substr($v->driver->middlename, 0, 1)) : '';
                                            $driverName = implode(' ', array_filter(
                                                [$v->driver->rank, $v->driver->firstname, $mi, $v->driver->lastname, $v->driver->qlfr],
                                                fn ($part) => ! is_null($part) && trim($part) !== ''
                                            ));
                                        }
                                    @endphp
                                    <option value="{{ $v->id }}" data-driver="{{ $driverName }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make.' '.$v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Maintenance Type <span class="text-danger">*</span></label>
                            <select name="maintenance_type" id="log_maintenance_type" class="form-control form-control-modern" required>
                                @foreach (\App\Models\MaintenanceRecord::TYPES as $key => $label)
                                    @continue($key === 'REPAIR')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">Logging a repair? Use the <a href="{{ route('repairs.index') }}">Repairs</a> module instead.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Description / Notes</label>
                            <textarea name="description" id="log_description" rows="2" class="form-control form-control-modern" placeholder="What was done..."></textarea>
                        </div>
                    </div>

                    {{--
                        Process-flow redesign (Step 1: digitized Motorpool Service Request
                        Form). Only request-stage fields are collected here now — service
                        date, odometer, cost, performed-by and next-schedule all belong to
                        completeService() instead, unlocked once the Technical Inspection
                        (and Requisition Slip, if parts are needed) are filled in. See
                        MaintenanceController::store()'s docblock.
                    --}}
                    <div class="modal-section-divider"><i class="fas fa-file-signature"></i> Request Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Date Requested <span class="text-danger">*</span></label>
                            <input type="date" name="request_date" id="log_request_date" class="form-control form-control-modern" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Requested By <span class="text-danger">*</span></label>
                            <input type="text" name="requested_by" id="log_requested_by" class="form-control form-control-modern" placeholder="Driver / requesting party" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Recommended By</label>
                            <input type="text" name="recommended_by" id="log_recommended_by" class="form-control form-control-modern">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Approved By</label>
                            <input type="text" name="approved_by" id="log_approved_by" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-paperclip"></i> Supporting Forms</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Technical Inspection Report</label>
                            <input type="file" name="technical_inspection" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <small class="text-muted d-block mt-1">Upload the filled-out/scanned copy, if already on hand. <a href="{{ asset('forms/technical-inspection-report.pdf') }}" target="_blank"><i class="fas fa-download mr-1"></i>Download blank form</a></small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Motorpool Service Request Form</label>
                            <input type="file" name="service_request_form" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <small class="text-muted d-block mt-1">Upload the filled-out/scanned copy, if already on hand. <a href="{{ asset('forms/motorpool-service-request-form.pdf') }}" target="_blank"><i class="fas fa-download mr-1"></i>Download blank form</a></small>
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fas fa-info-circle mr-1"></i> After this request is saved, fill out the Technical Inspection checklist next — that's what determines whether a Requisition Slip is needed before this job can be marked Completed.
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitLog" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MAINTENANCE MODAL -->
<div class="modal fade" id="editMaintenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit Maintenance Record</h5>
                <div class="mr-header-sub" id="editHeaderSub"></div>
            </div>

            <form id="editMaintenanceForm" enctype="multipart/form-data">
                <input type="hidden" id="edit_maintenance_id" name="id">
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body p-4">
                    <div id="editLogErrorBanner" class="live-checker-banner warning"></div>

                    {{--
                        Read-only process-flow status — lets whoever's editing see at a
                        glance where this record sits (Requested/Inspected/Awaiting Parts/
                        Completed) without leaving the modal. See MaintenanceRecord::STAGE_*.
                    --}}
                    <div class="mr-stage-stepper" id="editStageStepper">
                        <div class="mr-step" data-stage="REQUESTED">
                            <div class="mr-step-dot"><i class="fas fa-file-alt"></i></div>
                            <div class="mr-step-label">Requested</div>
                        </div>
                        <div class="mr-step-connector"></div>
                        <div class="mr-step" data-stage="INSPECTED">
                            <div class="mr-step-dot"><i class="fas fa-clipboard-check"></i></div>
                            <div class="mr-step-label">Inspected</div>
                        </div>
                        <div class="mr-step-connector"></div>
                        <div class="mr-step" data-stage="AWAITING_PARTS">
                            <div class="mr-step-dot"><i class="fas fa-boxes"></i></div>
                            <div class="mr-step-label">Awaiting Parts</div>
                        </div>
                        <div class="mr-step-connector"></div>
                        <div class="mr-step" data-stage="COMPLETED">
                            <div class="mr-step-dot"><i class="fas fa-check-circle"></i></div>
                            <div class="mr-step-label">Completed</div>
                        </div>
                    </div>
                    <div class="mr-stage-meta">
                        <span id="edit_control_number" class="mr-control-number"></span>
                        <span id="edit_blocked_hint" class="mr-blocked-hint" style="display:none;"></span>
                    </div>

                    <div class="modal-section-divider mt-0"><i class="fas fa-car"></i> Vehicle & Activity</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="edit_log_vehicle_id" class="form-control" style="width:100%;" required>
                                <option value="">Select vehicle...</option>
                                @foreach ($vehicles as $v)
                                    @php
                                        $driverName = '';
                                        if ($v->driver) {
                                            $mi = $v->driver->middlename ? strtoupper(substr($v->driver->middlename, 0, 1)) : '';
                                            $driverName = implode(' ', array_filter(
                                                [$v->driver->rank, $v->driver->firstname, $mi, $v->driver->lastname, $v->driver->qlfr],
                                                fn ($part) => ! is_null($part) && trim($part) !== ''
                                            ));
                                        }
                                    @endphp
                                    <option value="{{ $v->id }}" data-driver="{{ $driverName }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make.' '.$v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Maintenance Type <span class="text-danger">*</span></label>
                            <select name="maintenance_type" id="edit_maintenance_type" class="form-control form-control-modern" required>
                                @foreach (\App\Models\MaintenanceRecord::TYPES as $key => $label)
                                    @continue($key === 'REPAIR')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Description / Notes</label>
                            <textarea name="description" id="edit_description" rows="2" class="form-control form-control-modern"></textarea>
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-file-signature"></i> Request Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Date Requested</label>
                            <input type="date" name="request_date" id="edit_request_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Requested By</label>
                            <input type="text" name="requested_by" id="edit_requested_by" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Recommended By</label>
                            <input type="text" name="recommended_by" id="edit_recommended_by" class="form-control form-control-modern">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Approved By</label>
                            <input type="text" name="approved_by" id="edit_approved_by" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-wrench"></i> Service Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Service Date <span class="text-danger">*</span></label>
                            <input type="date" name="service_date" id="edit_service_date" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Odometer Reading (km)</label>
                            <input type="number" name="odometer_km" id="edit_odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Cost (&#8369;)</label>
                            <input type="number" name="cost" id="edit_cost" step="0.01" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Performed By / Shop</label>
                            <input type="text" name="performed_by" id="edit_performed_by" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Replace Receipt / Invoice</label>
                            <input type="file" name="attachment" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="editAttachmentCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="editAttachmentLink" class="small"><i class="fas fa-paperclip"></i> View current attachment</a> —
                                <a href="#" id="editAttachmentRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_attachment" id="edit_remove_attachment" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-paperclip"></i> Supporting Forms</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Replace Technical Inspection Report</label>
                            <input type="file" name="technical_inspection" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="editInspectionCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="editInspectionLink" class="small"><i class="fas fa-clipboard-check"></i> View current copy</a> —
                                <a href="#" id="editInspectionRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_technical_inspection" id="edit_remove_technical_inspection" value="0">
                            </div>
                            <small class="text-muted d-block mt-1"><a href="{{ asset('forms/technical-inspection-report.pdf') }}" target="_blank"><i class="fas fa-download mr-1"></i>Download blank form</a></small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Replace Motorpool Service Request Form</label>
                            <input type="file" name="service_request_form" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="editServiceRequestCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="editServiceRequestLink" class="small"><i class="fas fa-file-invoice"></i> View current copy</a> —
                                <a href="#" id="editServiceRequestRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_service_request_form" id="edit_remove_service_request_form" value="0">
                            </div>
                            <small class="text-muted d-block mt-1"><a href="{{ asset('forms/motorpool-service-request-form.pdf') }}" target="_blank"><i class="fas fa-download mr-1"></i>Download blank form</a></small>
                        </div>
                    </div>
                    {{--
                        Only unlocks once the Technical Inspection checklist has flagged
                        parts/materials as needed — see MaintenanceRecord::canFillRequisition().
                        This is just an optional scanned copy of the signed slip; the digitized
                        Requisition Slip (the amber "Vehicle Repair Requisition Slip" row button)
                        is what actually has to be filled out before the job can be Completed —
                        see MaintenanceRecord::hasRequisitionFilled(). Hidden by default; toggled
                        visible by the Edit AJAX handler below.
                    --}}
                    <div class="row" id="edit_requisition_wrapper" style="display:none;">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Replace Vehicle Repair Requisition Slip</label>
                            <input type="file" name="requisition_slip" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="editRequisitionCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="editRequisitionLink" class="small"><i class="fas fa-boxes"></i> View current copy</a> —
                                <a href="#" id="editRequisitionRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_requisition_slip" id="edit_remove_requisition_slip" value="0">
                            </div>
                            <small class="text-muted d-block mt-1">Upload the filled-out/scanned copy, if already on hand. <a href="{{ asset('forms/vehicle-repair-requisition-slip.pdf') }}" target="_blank"><i class="fas fa-download mr-1"></i>Download blank form</a></small>
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-calendar-alt"></i> Next Schedule</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Date</label>
                            <input type="date" name="next_due_date" id="edit_next_due_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Odometer (km)</label>
                            <input type="number" name="next_due_odometer_km" id="edit_next_due_odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitEditLog" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--
    Step 4 of the process flow: the actual service/repair details (cost,
    performed-by, odometer, receipt, next schedule) — everything the old
    single-step Log modal used to collect, now only reachable once
    MaintenanceRecord::canComplete() allows it (Technical Inspection done,
    and Requisition Slip on file if parts were flagged as needed). See
    MaintenanceController::completeService().
--}}
<div class="modal fade" id="completeMaintenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate">
                <h5><i class="fas fa-check-circle mr-2 text-success"></i> Complete Service</h5>
                <div class="mr-header-sub" id="completeHeaderSub"></div>
            </div>

            <form id="completeMaintenanceForm" enctype="multipart/form-data">
                <input type="hidden" id="complete_maintenance_id" name="id">
                <div class="modal-body p-4">
                    <div id="completeErrorBanner" class="live-checker-banner warning"></div>
                    <div class="mr-ready-banner">
                        <div class="mr-ready-icon"><i class="fas fa-check"></i></div>
                        <div class="mr-ready-banner-text">
                            <span id="completeVehicleLabel" style="display:block;"></span>
                            <small>Inspection requirements are satisfied — log the final service details below to close this record.</small>
                        </div>
                    </div>

                    <div class="modal-section-divider mt-0"><i class="fas fa-wrench"></i> Service Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Service Date <span class="text-danger">*</span></label>
                            <input type="date" name="service_date" id="complete_service_date" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Odometer Reading (km)</label>
                            <input type="number" name="odometer_km" id="complete_odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Cost (&#8369;)</label>
                            <input type="number" name="cost" id="complete_cost" step="0.01" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Performed By / Shop <span class="text-danger">*</span></label>
                            <input type="text" name="performed_by" id="complete_performed_by" class="form-control form-control-modern" placeholder="e.g. Motorpool, ABC Auto Shop" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Receipt / Invoice</label>
                            <input type="file" name="attachment" id="complete_attachment" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <span class="field-feedback-text" id="completeAttachmentScanStatus" style="display:none;"></span>
                            @if($aiDocumentScanningEnabled ?? false)
                            <small class="text-muted d-block mt-1"><i class="fas fa-wand-magic-sparkles mr-1 text-primary"></i> A clear receipt photo or PDF auto-fills cost/date/shop below.</small>
                            @endif
                        </div>
                    </div>

                    <div class="modal-section-divider"><i class="fas fa-calendar-alt"></i> Next Schedule</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Date</label>
                            <input type="date" name="next_due_date" id="complete_next_due_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Odometer (km)</label>
                            <input type="number" name="next_due_odometer_km" id="complete_next_due_odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fas fa-info-circle mr-1"></i> Setting a Next Due Date/Odometer here updates this vehicle's PMS schedule shown on the Vehicle Inventory page.
                    </div>

                    {{--
                        Part IV "Certification of Completion" on the Motorpool Service
                        Request Form — "I hereby certify that the requested ... service
                        have been successfully performed ... Date Completed / Inspected
                        and Received by." Date Completed is the Service Date above;
                        this is the signee. See MaintenanceRecord::$fillable's
                        received_by and MaintenanceController::completeService().
                    --}}
                    <div class="modal-section-divider"><i class="fas fa-stamp"></i> Part IV — Certification of Completion</div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Inspected and Received By <span class="text-danger">*</span></label>
                            <input type="text" name="received_by" id="complete_received_by" class="form-control form-control-modern" placeholder="Driver / Representative" required>
                            <small class="text-muted d-block mt-1">Signature over printed name of the driver/representative certifying the completed service.</small>
                        </div>
                    </div>

                    {{--
                        Supporting documents: scanned/signed copies of the three official
                        PRO5/RLRDD forms, attached now that the job is done. Each can
                        already have been uploaded earlier (Log/Edit); this is just where
                        the process flow now points admins to do it.
                    --}}
                    <div class="modal-section-divider"><i class="fas fa-paperclip"></i> Supporting Documents</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Motorpool Service Request Form</label>
                            <input type="file" name="service_request_form" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="completeServiceRequestCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="completeServiceRequestLink" class="small"><i class="fas fa-file-invoice"></i> View current copy</a> —
                                <a href="#" id="completeServiceRequestRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_service_request_form" id="complete_remove_service_request_form" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Technical Inspection Report</label>
                            <input type="file" name="technical_inspection" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="completeInspectionCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="completeInspectionLink" class="small"><i class="fas fa-clipboard-check"></i> View current copy</a> —
                                <a href="#" id="completeInspectionRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_technical_inspection" id="complete_remove_technical_inspection" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row" id="complete_requisition_wrapper" style="display:none;">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Vehicle Repair Requisition Slip</label>
                            <input type="file" name="requisition_slip" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                            <div id="completeRequisitionCurrent" class="mt-1" style="display:none;">
                                <a href="#" target="_blank" id="completeRequisitionLink" class="small"><i class="fas fa-boxes"></i> View current copy</a> —
                                <a href="#" id="completeRequisitionRemove" class="small text-danger">remove</a>
                                <input type="hidden" name="remove_requisition_slip" id="complete_remove_requisition_slip" value="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitComplete" class="btn btn-success"><i class="fas fa-check-circle mr-1"></i> Mark Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Available to every role including Viewer (read-only) — see the partial's own
     docblock and TechnicalInspectionController for why it isn't gated behind the
     "! $isViewer" block above like the Log/Edit modals are. --}}
@include('technical-inspections._modal')

{{-- Same visibility rule as the Technical Inspection Checklist above — every
     role including Viewer can open it (read-only there), since it now holds
     real requisition data, not just an attachment link. --}}
@include('vehicle-requisitions._modal')
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const routeTemplates = {
        editData: "{{ route('maintenance.edit-data', ':id') }}",
        update:   "{{ route('maintenance.update', ':id') }}",
        destroy:  "{{ route('maintenance.destroy', ':id') }}",
        complete: "{{ route('maintenance.complete', ':id') }}",
    };
    function maintenanceRoute(name, id) {
        return routeTemplates[name].replace(':id', id);
    }

    // Renders the Requested → Inspected → Awaiting Parts → Completed stepper in the
    // Edit modal. "Awaiting Parts" is marked skipped (dashed, struck through) rather
    // than done/pending when the Technical Inspection never flagged parts as needed —
    // see MaintenanceRecord::STAGE_* / parts_needed.
    function mrRenderStageStepper($stepper, stage, partsNeeded) {
        const order = ['REQUESTED', 'INSPECTED', 'AWAITING_PARTS', 'COMPLETED'];
        const currentIndex = order.indexOf(stage);
        $stepper.find('.mr-step').each(function () {
            const stepStage = $(this).data('stage');
            const $el = $(this).removeClass('done active pending skipped');
            if (stepStage === 'AWAITING_PARTS' && !partsNeeded) {
                $el.addClass('skipped');
                return;
            }
            const stepIndex = order.indexOf(stepStage);
            if (stepIndex < currentIndex) { $el.addClass('done'); }
            else if (stepIndex === currentIndex) { $el.addClass('active'); }
            else { $el.addClass('pending'); }
        });
        $stepper.find('.mr-step-connector').each(function (i) {
            $(this).toggleClass('done', i < currentIndex);
        });
    }

    if ($.fn.select2) {
        // Modal dropdowns fill their form column; the toolbar filter keeps its own
        // fixed width (set inline on the element) so it sits in line with the other
        // filter dropdowns instead of stretching to fill the flex row.
        $('#log_vehicle_id, #edit_log_vehicle_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Select or search a vehicle...',
        });
        $('#filterVehicle').select2({
            theme: 'bootstrap4',
            width: 'resolve',
            placeholder: 'All Vehicles',
            allowClear: true,
        });
    }

    // ---------------- Auto-fill "Requested By" from the vehicle's assigned driver ----------------
    // In practice it's the driver assigned to a vehicle who actually requests its
    // PMS/repair, not whichever admin is logged in and logging it on their behalf —
    // so picking a vehicle fills this in as a convenience default. It's a plain
    // text input, so the admin can still overwrite it (walk-in request, vehicle
    // with no assigned driver, etc.) — this just saves the common case of retyping
    // a name that's already on file. Bound to 'change' rather than select2's own
    // 'select2:select' so it also fires for every .val(...).trigger('change') call
    // already used elsewhere on this page (quick-log from the monitoring panel,
    // the Edit modal populating itself) — in the edit case, the actual saved
    // requested_by value is set right afterward and wins, as it should.
    $('#log_vehicle_id').on('change', function () {
        const driverName = $(this).find(':selected').data('driver') || '';
        $('#log_requested_by').val(driverName);
    });
    $('#edit_log_vehicle_id').on('change', function () {
        const driverName = $(this).find(':selected').data('driver') || '';
        $('#edit_requested_by').val(driverName);
    });

    @if (session('success'))
        toastr.success(@json(session('success')));
    @endif

    // ---------------- Stat cards + monitoring panel ----------------
    function updateStatCards(stats) {
        if (!stats) return;
        $('#statTotalRecords').text(stats.total_records);
        $('#statDueSoon').text(stats.due_soon);
        $('#statOverdue').text(stats.overdue);
        $('#statServicedMonth').text(stats.serviced_month);
    }

    function renderMonitorList($list, $count, items, variant) {
        $count.text(items.length);
        $list.empty();

        if (!items.length) {
            $list.append('<div class="monitor-empty">Nothing here — all clear.</div>');
            return;
        }

        items.forEach(function (item) {
            const dayLabel = variant === 'overdue'
                ? Math.abs(item.days) + (Math.abs(item.days) === 1 ? ' day overdue' : ' days overdue')
                : (item.days === 0 ? 'Due today' : 'in ' + item.days + (item.days === 1 ? ' day' : ' days'));

            const $row = $('<div class="monitor-item"></div>');
            $row.append(
                $('<div class="monitor-item-info"></div>').append(
                    $('<span class="plate-badge-sm"></span>').text(item.plate_number),
                    $('<div class="monitor-item-name"></div>').text(item.make_model + ' · ' + item.next_pms_date)
                )
            );
            const $right = $('<div class="d-flex align-items-center" style="gap:10px;"></div>');
            $right.append($('<span class="monitor-item-days"></span>').text(dayLabel));
            @if (! $isViewer)
            $right.append(
                $('<button type="button" class="btn btn-sm btn-light border btn-log-quick" title="Log maintenance for this vehicle"><i class="fas fa-plus"></i></button>')
                    .attr('data-vehicle-id', item.id)
            );
            @endif
            $row.append($right);
            $list.append($row);
        });
    }

    function updateMonitoring(monitoring) {
        if (!monitoring) return;
        renderMonitorList($('#monitorSoonList'), $('#monitorSoonCount'), monitoring.due_soon || [], 'soon');
        renderMonitorList($('#monitorOverdueList'), $('#monitorOverdueCount'), monitoring.overdue || [], 'overdue');
    }

    $(document).on('click', '.btn-log-quick', function () {
        const vehicleId = $(this).data('vehicle-id');
        $('#logMaintenanceForm')[0].reset();
        $('#log_request_date').val(new Date().toISOString().slice(0, 10));
        $('#logModalErrorBanner').hide().text('');
        $('#log_vehicle_id').val(String(vehicleId)).trigger('change');
        $('#logMaintenanceModal').modal('show');
    });

    // ---------------- DataTable ----------------
    const table = $('#maintenanceTable').DataTable({
        processing: true,
        serverSide: true,
        // See vehicles/index.blade.php for why this matters: without it,
        // DataTables locks the <table> to an inline pixel width at init time,
        // which overrides our CSS width:100% and breaks the mobile "stack
        // into cards" layout below.
        autoWidth: false,
        ajax: {
            url: "{{ route('maintenance.index') }}",
            data: function (d) {
                d.vehicle_id = $('#filterVehicle').val();
                d.maintenance_type = $('#filterMaintenanceType').val();
                @if ($hasBroadVisibility)
                    d.unit_id = $('#filterUnit').val();
                @endif
                d.station_id = $('#filterStation').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
            },
            dataSrc: function (json) {
                updateStatCards(json.stats);
                updateMonitoring(json.monitoring);
                return json.data;
            }
        },
        columns: [
            { data: 'vehicle_html', name: 'vehicle', orderable: false },
            { data: 'stage_html', name: 'stage', orderable: false },
            { data: 'type_html', name: 'maintenance_type', orderable: false },
            { data: 'service_html', name: 'service_date' },
            { data: 'cost_html', name: 'cost' },
            { data: 'next_due_html', name: 'next_due_date' },
            { data: 'logged_html', name: 'logged', orderable: false, searchable: false },
            { data: 'actions_html', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        dom: '<"row"<"col-sm-12"tr>><"row pt-3"<"col-sm-5"i><"col-sm-7"p>>',
    });
    // Exposed globally so the shared modal partials (technical-inspections/_modal.blade.php,
    // vehicle-requisitions/_modal.blade.php) — each their own separate <script> tag, included
    // outside this closure — can reload this row's data after they save, instead of the reload
    // silently no-op'ing because their own `typeof table !== 'undefined'` check can't see a
    // `const` declared inside this one.
    window.table = table;

    // Fast search: debounced so a burst of keystrokes fires one query, not one per key —
    // keeps the table responsive instead of hammering the server on every character.
    let searchDebounce;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchDebounce);
        const value = $(this).val();
        searchDebounce = setTimeout(function () { table.search(value).draw(); }, 300);
    });

    $('#filterVehicle, #filterMaintenanceType, #filterUnit, #filterStation, #filterDateFrom, #filterDateTo').on('change', function() {
        table.draw();
    });

    $('#filterUnit').change(function() {
        let unitId = $(this).val();
        $('#filterStation option').each(function() {
            if ($(this).val() === "") { $(this).show(); return; }
            let matchesUnit = ($(this).data('unit') == unitId);
            $(this).toggle(unitId !== "" && matchesUnit);
        });
        $('#filterStation').val('');
    });
    $('#filterUnit').trigger('change');

    $('#resetFiltersBtn').click(function() {
        $('#customSearchBox, #filterMaintenanceType, #filterStation, #filterDateFrom, #filterDateTo').val('');
        $('#filterVehicle').val('').trigger('change');
        @if ($hasBroadVisibility)
            $('#filterUnit').val('');
        @endif
        $('#filterStation option').show();
        table.search('').draw();
    });

    // ---------------- Log Maintenance (create) ----------------
    $('#logMaintenanceModal').on('show.bs.modal', function (e) {
        if (!e.relatedTarget) return; // opened programmatically (btn-log-quick) — it sets its own state
        $('#logMaintenanceForm')[0].reset();
        $('#log_request_date').val(new Date().toISOString().slice(0, 10));
        $('#logModalErrorBanner').hide().text('');
        $('#log_vehicle_id').val('').trigger('change');
    });

    // ---------------- AI Document Intelligence: auto-fill from receipt photo ----------------
    // Reuses the same file already being attached as the Complete Service record's
    // receipt/invoice — as soon as it's chosen, it's read and whatever comes back
    // pre-fills the fields below, which stay normal editable inputs so a misread is
    // just corrected before Save. Gated on the same server-side flag as the hint text
    // above: when no API key is configured yet, this stays completely silent instead
    // of firing a call just to show a "not configured" message on every single upload.
    const aiDocumentScanningEnabled = @json($aiDocumentScanningEnabled ?? false);

    $('#complete_attachment').on('change', function () {
        const file = this.files && this.files[0];
        const statusEl = $('#completeAttachmentScanStatus');
        // Accept photos as well as PDF exports — a saved receipt/invoice is
        // very commonly a PDF, and the backend now reads both the same way.
        const isScannable = file && (/^image\//.test(file.type) || file.type === 'application/pdf');
        if (!aiDocumentScanningEnabled || !isScannable) {
            statusEl.hide();
            return;
        }

        statusEl.removeClass('error success').addClass('text-muted').show()
            .html('<i class="fas fa-spinner fa-spin"></i> Reading receipt…');

        const formData = new FormData();
        formData.append('image', file);

        $.ajax({
            url: "{{ route('ai.extract-maintenance-receipt') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function (res) {
            if (!res.success) {
                statusEl.removeClass('text-muted success').addClass('error').text(res.message || 'Could not read this receipt.');
                return;
            }

            const d = res.data || {};
            let filled = 0;
            const setIfEmpty = function (selector, value) {
                if (value === null || value === undefined || value === '') return;
                const $field = $(selector);
                if (!$field.val()) { $field.val(value); filled++; }
            };

            setIfEmpty('#complete_service_date', d.service_date);
            setIfEmpty('#complete_cost', d.cost);
            setIfEmpty('#complete_performed_by', d.performed_by);

            if (filled > 0) {
                statusEl.removeClass('text-muted error').addClass('success')
                    .html('<i class="fas fa-check"></i> Auto-filled ' + filled + ' field(s) — please review before saving.');
            } else {
                statusEl.removeClass('text-muted success').addClass('error').text('No readable fields found — please enter details manually.');
            }
        }).fail(function () {
            statusEl.removeClass('text-muted success').addClass('error').text('Scanning failed — please enter details manually.');
        });
    });

    $('#logMaintenanceForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save this service request?',
            text: 'This opens a new Requested record — fill out the Technical Inspection checklist next to move it forward.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Request',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Review Again',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const formData = new FormData(form);
            const $btn = $('#btnSubmitLog');
            $('#logModalErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: "{{ route('maintenance.store') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function (res) {
                toastr.success(res.message);
                $('#logMaintenanceModal').modal('hide');
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#logModalErrorBanner').addClass('warning').text(msg).show();
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Request');
            });
        });
    });

    // ---------------- Edit ----------------
    $('#maintenanceTable').on('click', '.btn-edit-maintenance', function () {
        const id = $(this).data('id');

        $.get(maintenanceRoute('editData', id), function (data) {
            $('#edit_maintenance_id').val(data.id);
            $('#edit_log_vehicle_id').val(data.vehicle_id).trigger('change');
            $('#edit_maintenance_type').val(data.maintenance_type);
            $('#edit_description').val(data.description);
            $('#edit_request_date').val(data.request_date);
            $('#edit_requested_by').val(data.requested_by);
            $('#edit_recommended_by').val(data.recommended_by);
            $('#edit_approved_by').val(data.approved_by);
            $('#editHeaderSub').text([data.vehicle_label, data.control_number].filter(Boolean).join(' · '));
            mrRenderStageStepper($('#editStageStepper'), data.stage, !!data.parts_needed);
            $('#edit_control_number').text(data.control_number || '');
            if (data.stage !== 'COMPLETED' && data.blocked_reason) {
                $('#edit_blocked_hint').html('<i class="fas fa-lock mr-1"></i>' + data.blocked_reason).show();
            } else {
                $('#edit_blocked_hint').hide();
            }
            $('#edit_service_date').val(data.service_date);
            $('#edit_odometer_km').val(data.odometer_km);
            $('#edit_cost').val(data.cost);
            $('#edit_performed_by').val(data.performed_by);
            $('#edit_next_due_date').val(data.next_due_date);
            $('#edit_next_due_odometer_km').val(data.next_due_odometer_km);
            $('#edit_remove_attachment').val('0');
            $('#edit_remove_technical_inspection').val('0');
            $('#edit_remove_service_request_form').val('0');
            $('#edit_remove_requisition_slip').val('0');

            $('#edit_requisition_wrapper').toggle(!!data.can_fill_requisition);
            if (data.has_requisition_slip) {
                $('#editRequisitionLink').attr('href', data.requisition_slip_url);
                $('#editRequisitionCurrent').show();
            } else {
                $('#editRequisitionCurrent').hide();
            }

            if (data.has_attachment) {
                $('#editAttachmentLink').attr('href', data.attachment_url);
                $('#editAttachmentCurrent').show();
            } else {
                $('#editAttachmentCurrent').hide();
            }

            if (data.has_technical_inspection) {
                $('#editInspectionLink').attr('href', data.technical_inspection_url);
                $('#editInspectionCurrent').show();
            } else {
                $('#editInspectionCurrent').hide();
            }

            if (data.has_service_request) {
                $('#editServiceRequestLink').attr('href', data.service_request_url);
                $('#editServiceRequestCurrent').show();
            } else {
                $('#editServiceRequestCurrent').hide();
            }

            $('#editLogErrorBanner').hide().text('');
            $('#editMaintenanceModal').modal('show');
        }).fail(function () {
            toastr.error('Could not load this record\'s details.');
        });
    });

    $('#editAttachmentRemove').on('click', function (e) {
        e.preventDefault();
        $('#edit_remove_attachment').val('1');
        $('#editAttachmentCurrent').hide();
        toastr.info('Attachment will be removed when you save.');
    });

    $('#editInspectionRemove').on('click', function (e) {
        e.preventDefault();
        $('#edit_remove_technical_inspection').val('1');
        $('#editInspectionCurrent').hide();
        toastr.info('Technical Inspection Report will be removed when you save.');
    });

    $('#editServiceRequestRemove').on('click', function (e) {
        e.preventDefault();
        $('#edit_remove_service_request_form').val('1');
        $('#editServiceRequestCurrent').hide();
        toastr.info('Motorpool Service Request Form will be removed when you save.');
    });

    $('#editRequisitionRemove').on('click', function (e) {
        e.preventDefault();
        $('#edit_remove_requisition_slip').val('1');
        $('#editRequisitionCurrent').hide();
        toastr.info('Requisition Slip will be removed when you save.');
    });

    $('#editMaintenanceForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save changes to this record?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Keep Editing',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const id = $('#edit_maintenance_id').val();
            const formData = new FormData(form);
            const $btn = $('#btnSubmitEditLog');
            $('#editLogErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: maintenanceRoute('update', id),
                type: 'POST', // spoofed to PUT via the _method field — needed for the file upload
                data: formData,
                processData: false,
                contentType: false,
            }).done(function (res) {
                toastr.success(res.message);
                $('#editMaintenanceModal').modal('hide');
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#editLogErrorBanner').addClass('warning').text(msg).show();
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes');
            });
        });
    });

    // ---------------- Complete Service (Step 4 of the process flow) ----------------
    // Reuses the same edit-data endpoint (it already returns vehicle_label/service_date/
    // etc.) rather than a separate lookup just for this modal.
    $('#maintenanceTable').on('click', '.btn-complete-maintenance', function () {
        const id = $(this).data('id');

        $.get(maintenanceRoute('editData', id), function (data) {
            $('#complete_maintenance_id').val(data.id);
            $('#completeHeaderSub').text(data.control_number || '');
            $('#completeVehicleLabel').text(data.vehicle_label || '');
            // A Requested/Inspected/Awaiting-Parts record's service_date is just its
            // request_date placeholder — start the completion date at today instead
            // of carrying that placeholder forward.
            $('#complete_service_date').val(new Date().toISOString().slice(0, 10));
            $('#complete_odometer_km').val(data.odometer_km);
            $('#complete_cost').val(data.cost);
            $('#complete_performed_by').val(data.performed_by);
            $('#complete_received_by').val(data.received_by);
            $('#complete_next_due_date').val(data.next_due_date);
            $('#complete_next_due_odometer_km').val(data.next_due_odometer_km);
            $('#completeAttachmentScanStatus').hide();

            // Supporting documents — same current-file/remove pattern as the Edit
            // modal, reset per-open since this modal is reused across records.
            $('#complete_remove_technical_inspection, #complete_remove_service_request_form, #complete_remove_requisition_slip').val('0');
            if (data.has_technical_inspection) {
                $('#completeInspectionLink').attr('href', data.technical_inspection_url);
                $('#completeInspectionCurrent').show();
            } else {
                $('#completeInspectionCurrent').hide();
            }
            if (data.has_service_request) {
                $('#completeServiceRequestLink').attr('href', data.service_request_url);
                $('#completeServiceRequestCurrent').show();
            } else {
                $('#completeServiceRequestCurrent').hide();
            }
            $('#complete_requisition_wrapper').toggle(!!data.can_fill_requisition);
            if (data.has_requisition_slip) {
                $('#completeRequisitionLink').attr('href', data.requisition_slip_url);
                $('#completeRequisitionCurrent').show();
            } else {
                $('#completeRequisitionCurrent').hide();
            }

            $('#completeErrorBanner').hide().text('');
            $('#completeMaintenanceModal').modal('show');
        }).fail(function () {
            toastr.error('Could not load this record\'s details.');
        });
    });

    $('#completeInspectionRemove').on('click', function (e) {
        e.preventDefault();
        $('#complete_remove_technical_inspection').val('1');
        $('#completeInspectionCurrent').hide();
        toastr.info('Technical Inspection Report will be removed when you save.');
    });

    $('#completeServiceRequestRemove').on('click', function (e) {
        e.preventDefault();
        $('#complete_remove_service_request_form').val('1');
        $('#completeServiceRequestCurrent').hide();
        toastr.info('Motorpool Service Request Form will be removed when you save.');
    });

    $('#completeRequisitionRemove').on('click', function (e) {
        e.preventDefault();
        $('#complete_remove_requisition_slip').val('1');
        $('#completeRequisitionCurrent').hide();
        toastr.info('Requisition Slip will be removed when you save.');
    });

    $('#completeMaintenanceForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Mark this job Completed?',
            text: 'This records the final service details and updates the vehicle\'s PMS schedule if a next due date was set.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Mark Completed',
            confirmButtonColor: '#16a34a',
            cancelButtonText: 'Review Again',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const id = $('#complete_maintenance_id').val();
            const formData = new FormData(form);
            const $btn = $('#btnSubmitComplete');
            $('#completeErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: maintenanceRoute('complete', id),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function (res) {
                toastr.success(res.message);
                $('#completeMaintenanceModal').modal('hide');
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#completeErrorBanner').addClass('warning').text(msg).show();
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Mark Completed');
            });
        });
    });

    // ---------------- Delete ----------------
    $('#maintenanceTable').on('click', '.btn-delete-maintenance', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Delete this maintenance record?',
            text: 'This cannot be undone. The vehicle\'s PMS schedule will be recalculated from its remaining history.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: maintenanceRoute('destroy', id),
                type: 'DELETE',
            }).done(function (res) {
                toastr.success(res.message);
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                toastr.error(xhr.responseJSON?.message || 'Could not delete this record.');
            });
        });
    });
});
</script>
@endsection
