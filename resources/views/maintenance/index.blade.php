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

    .fleet-table td:nth-child(2)::before { content: "Maintenance"; }
    .fleet-table td:nth-child(3)::before { content: "Service Date"; }
    .fleet-table td:nth-child(4)::before { content: "Cost"; }
    .fleet-table td:nth-child(5)::before { content: "Next Due"; }
    .fleet-table td:nth-child(6)::before { content: "Logged By"; }
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

                    <div class="modal-section-divider mt-0">Vehicle & Activity</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="log_vehicle_id" class="form-control" style="width:100%;" required>
                                <option value="">Select vehicle...</option>
                                @foreach ($vehicles as $v)
                                    <option value="{{ $v->id }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make.' '.$v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Maintenance Type <span class="text-danger">*</span></label>
                            <select name="maintenance_type" class="form-control form-control-modern" required>
                                @foreach (\App\Models\MaintenanceRecord::TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Description / Notes</label>
                            <textarea name="description" rows="2" class="form-control form-control-modern" placeholder="What was done..."></textarea>
                        </div>
                    </div>

                    <div class="modal-section-divider">Service Details</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Service Date <span class="text-danger">*</span></label>
                            <input type="date" name="service_date" id="log_service_date" class="form-control form-control-modern" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Odometer Reading (km)</label>
                            <input type="number" name="odometer_km" id="log_odometer_km" class="form-control form-control-modern" min="0">
                            <small class="current-odo-hint" id="logCurrentOdoHint"></small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Cost (&#8369;)</label>
                            <input type="number" name="cost" step="0.01" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Performed By / Shop</label>
                            <input type="text" name="performed_by" class="form-control form-control-modern" placeholder="e.g. Motorpool, ABC Auto Shop">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Receipt / Invoice</label>
                            <input type="file" name="attachment" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" style="padding-top:7px;">
                        </div>
                    </div>

                    <div class="modal-section-divider">Next Schedule</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Date</label>
                            <input type="date" name="next_due_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next Due Odometer (km)</label>
                            <input type="number" name="next_due_odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fas fa-info-circle mr-1"></i> Setting a Next Due Date/Odometer here updates this vehicle's PMS schedule shown on the Vehicle Inventory page.
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitLog" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Record</button>
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
            </div>

            <form id="editMaintenanceForm" enctype="multipart/form-data">
                <input type="hidden" id="edit_maintenance_id" name="id">
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body p-4">
                    <div id="editLogErrorBanner" class="live-checker-banner warning"></div>

                    <div class="modal-section-divider mt-0">Vehicle & Activity</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="edit_log_vehicle_id" class="form-control" style="width:100%;" required>
                                <option value="">Select vehicle...</option>
                                @foreach ($vehicles as $v)
                                    <option value="{{ $v->id }}">{{ strtoupper($v->plate_number) }} — {{ trim($v->make.' '.$v->model) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Maintenance Type <span class="text-danger">*</span></label>
                            <select name="maintenance_type" id="edit_maintenance_type" class="form-control form-control-modern" required>
                                @foreach (\App\Models\MaintenanceRecord::TYPES as $key => $label)
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

                    <div class="modal-section-divider">Service Details</div>
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

                    <div class="modal-section-divider">Next Schedule</div>
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
@endif
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
    };
    function maintenanceRoute(name, id) {
        return routeTemplates[name].replace(':id', id);
    }

    // Vehicle odometer lookup — used only to show the "current: X km" hint next to the
    // odometer field, built once from the page's own vehicle list rather than a round trip.
    const vehicleOptions = @json($vehicles->map(fn($v) => ['id' => $v->id, 'label' => strtoupper($v->plate_number).' — '.trim($v->make.' '.$v->model), 'odometer' => $v->odometer_km]));

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
        $('#log_service_date').val(new Date().toISOString().slice(0, 10));
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
            { data: 'type_html', name: 'maintenance_type', orderable: false },
            { data: 'service_html', name: 'service_date' },
            { data: 'cost_html', name: 'cost' },
            { data: 'next_due_html', name: 'next_due_date' },
            { data: 'logged_html', name: 'logged', orderable: false, searchable: false },
            { data: 'actions_html', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[2, 'desc']],
        dom: '<"row"<"col-sm-12"tr>><"row pt-3"<"col-sm-5"i><"col-sm-7"p>>',
    });

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
    $('#log_vehicle_id').on('change', function () {
        const id = $(this).val();
        const vehicle = vehicleOptions.find(v => String(v.id) === String(id));
        if (vehicle && vehicle.odometer) {
            $('#logCurrentOdoHint').text('Current reading on file: ' + Number(vehicle.odometer).toLocaleString() + ' km');
        } else {
            $('#logCurrentOdoHint').text('');
        }
    });

    $('#logMaintenanceModal').on('show.bs.modal', function (e) {
        if (!e.relatedTarget) return; // opened programmatically (btn-log-quick) — it sets its own state
        $('#logMaintenanceForm')[0].reset();
        $('#log_service_date').val(new Date().toISOString().slice(0, 10));
        $('#logModalErrorBanner').hide().text('');
        $('#log_vehicle_id').val('').trigger('change');
    });

    $('#logMaintenanceForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save this maintenance record?',
            text: 'This will log the activity and update the vehicle\'s PMS schedule if a next due date was set.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Record',
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
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Record');
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
            $('#edit_service_date').val(data.service_date);
            $('#edit_odometer_km').val(data.odometer_km);
            $('#edit_cost').val(data.cost);
            $('#edit_performed_by').val(data.performed_by);
            $('#edit_next_due_date').val(data.next_due_date);
            $('#edit_next_due_odometer_km').val(data.next_due_odometer_km);
            $('#edit_remove_attachment').val('0');

            if (data.has_attachment) {
                $('#editAttachmentLink').attr('href', data.attachment_url);
                $('#editAttachmentCurrent').show();
            } else {
                $('#editAttachmentCurrent').hide();
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
