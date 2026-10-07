@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Accident Records</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.ac-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.ac-month .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
  .stat-card-modern.ac-major .stat-icon-wrapper { background: rgba(220, 38, 38, 0.12); color: #dc2626; }
  .stat-card-modern.ac-cost .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
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
  .custom-filter-select { width: 150px; }
  .custom-filter-date { width: 140px; }
  .btn-add-entity { border-radius: 9px; font-weight: 700; font-size: 13px; padding: 8px 16px; border: none; color: #fff; background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
  .btn-add-entity:hover { color: #fff; opacity: 0.92; }

  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }

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
    .fleet-table td:nth-child(2)::before { content: "Date / Time"; }
    .fleet-table td:nth-child(3)::before { content: "Location"; }
    .fleet-table td:nth-child(4)::before { content: "Severity"; }
    .fleet-table td:nth-child(5)::before { content: "Driver"; }
    .fleet-table td:nth-child(6)::before { content: "Est. Cost"; }
    .fleet-table td:nth-child(7)::before { content: "Logged By"; }
  }

  .plate-badge { display: inline-block; font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #0f172a; color: #fff; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.5px; }
  .vehicle-main-name { font-weight: 700; color: #0f172a; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; }

  .severity-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .03em; }
  .badge-severity-minor { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .badge-severity-moderate { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .badge-severity-major { background: rgba(220, 38, 38, 0.12); color: #dc2626; }

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
  textarea.form-control-modern { height: auto; }
  .field-label { font-weight: 700; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 8px; display: block; }
</style>
@endsection

@section('nav-title', 'VMIS | Accident Records')

@unless($isViewer)
@section('nav-actions')
<button type="button" class="btn-add-entity" data-toggle="modal" data-target="#logAccidentModal">
    <i class="fas fa-car-crash mr-1"></i> <span class="btn-label">Log Accident</span>
</button>
@endsection
@endunless

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern ac-total">
                <div class="stat-icon-wrapper"><i class="fas fa-car-crash"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_accidents'] }}</div>
                    <div class="stat-label-title">Total Accident Records</div>
                </div>
            </div>
            <div class="stat-card-modern ac-month">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['this_month'] }}</div>
                    <div class="stat-label-title">Reported This Month</div>
                </div>
            </div>
            <div class="stat-card-modern ac-major">
                <div class="stat-icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['major'] }}</div>
                    <div class="stat-label-title">Major / Totaled</div>
                </div>
            </div>
            <div class="stat-card-modern ac-cost">
                <div class="stat-icon-wrapper"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="stat-num-value">&#8369;{{ number_format((float) $stats['total_est_cost'], 2) }}</div>
                    <div class="stat-label-title">Total Estimated Cost</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-car-crash text-primary"></i> Accident Records</h5>
                    <p>Every logged accident{{ $hasBroadVisibility ? ' fleet-wide' : ' within your assigned scope' }}</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="accidentSearchBox" class="form-control" placeholder="Search accidents...">
                    </div>
                    @if($hasBroadVisibility)
                    <select id="filterUnit" class="form-control custom-filter-select">
                        <option value="">All Units</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                        @endforeach
                    </select>
                    @endif
                    <select id="filterStation" class="form-control custom-filter-select">
                        <option value="">All Stations</option>
                        @foreach($stations as $station)
                            <option value="{{ $station->id }}">{{ $station->station_name }}</option>
                        @endforeach
                    </select>
                    <select id="filterVehicle" class="form-control custom-filter-select" style="width:150px;">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }}</option>
                        @endforeach
                    </select>
                    <select id="filterSeverity" class="form-control custom-filter-select" style="width:130px;">
                        <option value="">All Severities</option>
                        @foreach(\App\Models\VehicleAccident::SEVERITIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" id="filterDateFrom" class="form-control custom-filter-date" title="From date">
                    <input type="date" id="filterDateTo" class="form-control custom-filter-date" title="To date">
                    <button type="button" id="resetAccidentFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table w-100" id="accidentsTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Date / Time</th>
                            <th>Location / Description</th>
                            <th>Severity</th>
                            <th>Driver</th>
                            <th>Est. Cost</th>
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

@unless($isViewer)
<!-- LOG ACCIDENT MODAL -->
<div class="modal fade" id="logAccidentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-car-crash mr-2 text-danger"></i> Log an Accident</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="logAccidentForm">
                @csrf
                <div class="modal-body p-4">
                    <div id="logAccidentErrorBanner" class="alert alert-warning" style="display:none;"></div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" class="form-control form-control-modern" required>
                                <option value="">Select vehicle...</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="field-label">Driver Involved</label>
                            <select name="driver_id" class="form-control form-control-modern">
                                <option value="">Unknown / not applicable</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ trim($driver->firstname . ' ' . $driver->lastname) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="field-label">Accident Date <span class="text-danger">*</span></label>
                            <input type="date" name="accident_date" class="form-control form-control-modern" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="field-label">Time</label>
                            <input type="time" name="accident_time" class="form-control form-control-modern">
                        </div>
                        <div class="form-group col-md-4">
                            <label class="field-label">Severity <span class="text-danger">*</span></label>
                            <select name="severity" class="form-control form-control-modern" required>
                                @foreach(\App\Models\VehicleAccident::SEVERITIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="field-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control form-control-modern" required maxlength="255" placeholder="e.g. National Highway, Brgy. San Isidro, Legazpi City">
                    </div>
                    <div class="form-group mb-3">
                        <label class="field-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-modern" rows="3" maxlength="2000" required placeholder="What happened, extent of damage, parties involved, etc."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="field-label">Estimated Damage Cost (&#8369;)</label>
                            <input type="number" name="estimated_cost" class="form-control form-control-modern" min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="field-label">Police Report No.</label>
                            <input type="text" name="police_report_no" class="form-control form-control-modern" maxlength="100">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="field-label">Accident Photo <span class="text-muted" style="text-transform:none;font-weight:500;">(supporting document)</span></label>
                        <input type="file" name="photo" class="form-control form-control-modern" accept=".jpg,.jpeg,.png,.pdf" style="padding-top:7px;">
                        <small class="text-muted d-block mt-1">Photo of the damage/scene, or a scanned police/incident report. JPG, PNG or PDF, up to 8 MB.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" id="btnSubmitAccident" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Accident Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT ACCIDENT MODAL -->
<div class="modal fade" id="editAccidentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit mr-2 text-info"></i> Edit Accident Record</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editAccidentForm">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="id" id="e_accident_id">
                <div class="modal-body p-4">
                    <div id="editAccidentErrorBanner" class="alert alert-warning" style="display:none;"></div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="field-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="e_vehicle_id" class="form-control form-control-modern" required>
                                <option value="">Select vehicle...</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="field-label">Driver Involved</label>
                            <select name="driver_id" id="e_driver_id" class="form-control form-control-modern">
                                <option value="">Unknown / not applicable</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ trim($driver->firstname . ' ' . $driver->lastname) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="field-label">Accident Date <span class="text-danger">*</span></label>
                            <input type="date" name="accident_date" id="e_accident_date" class="form-control form-control-modern" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="field-label">Time</label>
                            <input type="time" name="accident_time" id="e_accident_time" class="form-control form-control-modern">
                        </div>
                        <div class="form-group col-md-4">
                            <label class="field-label">Severity <span class="text-danger">*</span></label>
                            <select name="severity" id="e_severity" class="form-control form-control-modern" required>
                                @foreach(\App\Models\VehicleAccident::SEVERITIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="field-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" id="e_location" class="form-control form-control-modern" required maxlength="255">
                    </div>
                    <div class="form-group mb-3">
                        <label class="field-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" id="e_description" class="form-control form-control-modern" rows="3" maxlength="2000" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="field-label">Estimated Damage Cost (&#8369;)</label>
                            <input type="number" name="estimated_cost" id="e_estimated_cost" class="form-control form-control-modern" min="0" step="0.01">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="field-label">Police Report No.</label>
                            <input type="text" name="police_report_no" id="e_police_report_no" class="form-control form-control-modern" maxlength="100">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="field-label">Accident Photo <span class="text-muted" style="text-transform:none;font-weight:500;">(supporting document)</span></label>
                        <input type="file" name="photo" class="form-control form-control-modern" accept=".jpg,.jpeg,.png,.pdf" style="padding-top:7px;">
                        <div id="editAccidentPhotoCurrent" class="mt-1" style="display:none;">
                            <a href="#" target="_blank" id="editAccidentPhotoLink" class="small"><i class="fas fa-image"></i> View current photo</a> —
                            <a href="#" id="editAccidentPhotoRemove" class="small text-danger">remove</a>
                            <input type="hidden" name="remove_photo" id="e_remove_photo" value="0">
                        </div>
                        <small class="text-muted d-block mt-1">Leave blank to keep the current photo. JPG, PNG or PDF, up to 8 MB.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" id="btnSubmitEditAccident" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endunless
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document.body).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const editDataUrlTemplate = @json(route('accidents.edit-data', ['accident' => ':id']));
    const updateUrlTemplate = @json(route('accidents.update', ['accident' => ':id']));
    const destroyUrlTemplate = @json(route('accidents.destroy', ['accident' => ':id']));

    function accidentUrl(template, id) {
        return template.replace(':id', id);
    }

    const accidentsTable = $('#accidentsTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('accidents.index') }}",
            data: function(d) {
                d.unit_id = $('#filterUnit').val();
                d.station_id = $('#filterStation').val();
                d.vehicle_id = $('#filterVehicle').val();
                d.severity = $('#filterSeverity').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.search = { value: $('#accidentSearchBox').val() };
            }
        },
        columns: [
            { data: 'vehicle_html', orderable: false, searchable: false },
            { data: 'date_html', orderable: false, searchable: false },
            { data: 'location_html', orderable: false, searchable: false },
            { data: 'severity_html', orderable: false, searchable: false },
            { data: 'driver_html', orderable: false, searchable: false },
            { data: 'cost_html', orderable: false, searchable: false },
            { data: 'logged_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading accident records...'
        }
    });
    window.accidentsTable = accidentsTable;

    let searchTimeout;
    $('#accidentSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { accidentsTable.draw(); }, 300);
    });
    $('#filterUnit, #filterStation, #filterVehicle, #filterSeverity, #filterDateFrom, #filterDateTo').on('change', function() {
        accidentsTable.draw();
    });
    $('#resetAccidentFiltersBtn').on('click', function() {
        $('#accidentSearchBox, #filterUnit, #filterStation, #filterVehicle, #filterSeverity, #filterDateFrom, #filterDateTo').val('');
        accidentsTable.draw();
    });

    @if(! $isViewer)
    $('#logAccidentForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save this accident record?',
            text: 'Please double-check the vehicle, date, and details below before saving.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save It',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Review Again',
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const formData = new FormData(form);
            const $btn = $('#btnSubmitAccident');
            $('#logAccidentErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: "{{ route('accidents.store') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function(res) {
                toastr.success(res.message);
                $('#logAccidentModal').modal('hide');
                form.reset();
                accidentsTable.ajax.reload(null, false);
            }).fail(function(xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#logAccidentErrorBanner').text(msg).show();
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Accident Record');
            });
        });
    });

    $('#accidentsTable').on('click', '.btn-edit-accident', function() {
        const id = $(this).data('id');

        $.get(accidentUrl(editDataUrlTemplate, id)).done(function(data) {
            $('#e_accident_id').val(data.id);
            $('#e_vehicle_id').val(data.vehicle_id);
            $('#e_driver_id').val(data.driver_id || '');
            $('#e_accident_date').val(data.accident_date);
            $('#e_accident_time').val(data.accident_time || '');
            $('#e_severity').val(data.severity);
            $('#e_location').val(data.location);
            $('#e_description').val(data.description);
            $('#e_estimated_cost').val(data.estimated_cost);
            $('#e_police_report_no').val(data.police_report_no);
            $('#editAccidentForm input[name="photo"]').val('');
            $('#e_remove_photo').val('0');

            if (data.has_photo) {
                $('#editAccidentPhotoLink').attr('href', data.photo_url);
                $('#editAccidentPhotoCurrent').show();
            } else {
                $('#editAccidentPhotoCurrent').hide();
            }

            $('#editAccidentErrorBanner').hide().text('');
            $('#editAccidentModal').modal('show');
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Could not load this record.');
        });
    });

    $('#editAccidentPhotoRemove').on('click', function(e) {
        e.preventDefault();
        $('#e_remove_photo').val('1');
        $('#editAccidentPhotoCurrent').hide();
        toastr.info('Photo will be removed when you save.');
    });

    $('#editAccidentForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save changes to this accident record?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Keep Editing',
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const id = $('#e_accident_id').val();
            const formData = new FormData(form);
            const $btn = $('#btnSubmitEditAccident');
            $('#editAccidentErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: accidentUrl(updateUrlTemplate, id),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function(res) {
                toastr.success(res.message);
                $('#editAccidentModal').modal('hide');
                accidentsTable.ajax.reload(null, false);
            }).fail(function(xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#editAccidentErrorBanner').text(msg).show();
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Changes');
            });
        });
    });

    $('#accidentsTable').on('click', '.btn-delete-accident', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Delete this accident record?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: accidentUrl(destroyUrlTemplate, id),
                type: 'DELETE',
            }).done(function(res) {
                toastr.success(res.message);
                accidentsTable.ajax.reload(null, false);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Could not delete this record.');
            });
        });
    });
    @endif
});
</script>
@endsection
