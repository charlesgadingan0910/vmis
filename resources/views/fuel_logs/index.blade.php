@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Fuel Monitoring</title>
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
  .stat-card-modern.fl-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.fl-liters .stat-icon-wrapper { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
  .stat-card-modern.fl-cost .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.fl-kml .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
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
    .fleet-table td:nth-child(2)::before { content: "Driver"; }
    .fleet-table td:nth-child(3)::before { content: "Refuel Date"; }
    .fleet-table td:nth-child(4)::before { content: "Liters / Cost"; }
    .fleet-table td:nth-child(5)::before { content: "Odometer / Efficiency"; }
    .fleet-table td:nth-child(6)::before { content: "Receipt"; }
    .fleet-table td:nth-child(7)::before { content: "Logged By"; }
  }

  .plate-badge { display: inline-block; font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #0f172a; color: #fff; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.5px; }
  .vehicle-main-name { font-weight: 700; color: #0f172a; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; }
  .docs-pill-btn { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px; border: 1.5px solid transparent; text-decoration: none; }
  .docs-pill-btn i { font-size: 11px; }
  .docs-pill-btn.has-docs { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
  .docs-pill-btn.has-docs:hover { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }

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
</style>
@endsection

@section('nav-title', 'VMIS | Fuel Monitoring')

@if($canLogForAnyVehicle)
@section('nav-actions')
<button type="button" class="btn-add-entity" data-toggle="modal" data-target="#logFuelModal">
    <i class="fas fa-gas-pump mr-1"></i> <span class="btn-label">Log Refuel</span>
</button>
@endsection
@endif

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern fl-total">
                <div class="stat-icon-wrapper"><i class="fas fa-gas-pump"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_logs'] }}</div>
                    <div class="stat-label-title">Total Refuels Logged</div>
                </div>
            </div>
            <div class="stat-card-modern fl-liters">
                <div class="stat-icon-wrapper"><i class="fas fa-tint"></i></div>
                <div>
                    <div class="stat-num-value">{{ number_format((float) $stats['liters_month'], 1) }} L</div>
                    <div class="stat-label-title">Liters This Month</div>
                </div>
            </div>
            <div class="stat-card-modern fl-cost">
                <div class="stat-icon-wrapper"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="stat-num-value">&#8369;{{ number_format((float) $stats['cost_month'], 2) }}</div>
                    <div class="stat-label-title">Fuel Cost This Month</div>
                </div>
            </div>
            <div class="stat-card-modern fl-kml">
                <div class="stat-icon-wrapper"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['avg_kml'] !== null ? number_format($stats['avg_kml'], 2) . ' km/L' : '—' }}</div>
                    <div class="stat-label-title">Avg. Efficiency This Month</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-gas-pump text-primary"></i> Refuel History</h5>
                    <p>Every logged refuel{{ $hasBroadVisibility ? ' fleet-wide' : ' within your assigned scope' }}</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="fuelSearchBox" class="form-control" placeholder="Search refuels...">
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
                    <select id="filterVehicle" class="form-control custom-filter-select" style="width:170px;">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }}</option>
                        @endforeach
                    </select>
                    <input type="date" id="filterDateFrom" class="form-control custom-filter-date" title="From date">
                    <input type="date" id="filterDateTo" class="form-control custom-filter-date" title="To date">
                    <button type="button" id="resetFuelFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table w-100" id="fuelLogsTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Refuel Date</th>
                            <th>Liters / Cost</th>
                            <th>Odometer / Efficiency</th>
                            <th>Receipt</th>
                            <th>Logged By</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@if($canLogForAnyVehicle)
<!-- LOG REFUEL MODAL (Super Administrator only) -->
<div class="modal fade" id="logFuelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-gas-pump mr-2 text-primary"></i> Log a Refuel</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="logFuelForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div id="logFuelErrorBanner" class="alert alert-warning" style="display:none;"></div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Vehicle <span class="text-danger">*</span></label>
                        <select name="vehicle_id" class="form-control form-control-modern" required>
                            <option value="">Select vehicle...</option>
                            @foreach($allVehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ strtoupper($vehicle->plate_number) }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Refuel Date <span class="text-danger">*</span></label>
                            <input type="date" name="refuel_date" class="form-control form-control-modern" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Odometer Reading <span class="text-danger">*</span></label>
                            <input type="number" name="odometer_reading" class="form-control form-control-modern" min="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Liters <span class="text-danger">*</span></label>
                            <input type="number" name="liters" step="0.01" class="form-control form-control-modern" min="0.01" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Total Cost (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number" name="total_cost" step="0.01" class="form-control form-control-modern" min="0" required>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Receipt <small class="text-muted font-weight-normal">(optional — photo or PDF)</small></label>
                        <input type="file" name="receipt" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" id="btnSubmitFuelLog" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Refuel Log</button>
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

    const fuelLogsTable = $('#fuelLogsTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('fuel-logs.index') }}",
            data: function(d) {
                d.unit_id = $('#filterUnit').val();
                d.station_id = $('#filterStation').val();
                d.vehicle_id = $('#filterVehicle').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.search = { value: $('#fuelSearchBox').val() };
            }
        },
        columns: [
            { data: 'vehicle_html', orderable: false, searchable: false },
            { data: 'driver_html', orderable: false, searchable: false },
            { data: 'date_html', orderable: false, searchable: false },
            { data: 'fuel_html', orderable: false, searchable: false },
            { data: 'odometer_html', orderable: false, searchable: false },
            { data: 'receipt_html', orderable: false, searchable: false },
            { data: 'logged_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading refuels...'
        }
    });

    let searchTimeout;
    $('#fuelSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { fuelLogsTable.draw(); }, 300);
    });
    $('#filterUnit, #filterStation, #filterVehicle, #filterDateFrom, #filterDateTo').on('change', function() {
        fuelLogsTable.draw();
    });
    $('#resetFuelFiltersBtn').on('click', function() {
        $('#fuelSearchBox, #filterUnit, #filterStation, #filterVehicle, #filterDateFrom, #filterDateTo').val('');
        fuelLogsTable.draw();
    });

    @if($canLogForAnyVehicle)
    $('#logFuelForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save Refuel Log?',
            text: "Fuel logs are permanent records and cannot be edited or deleted afterward.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Save It'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const formData = new FormData(form);
            const $btn = $('#btnSubmitFuelLog');
            $('#logFuelErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: "{{ route('fuel-logs.store') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function(res) {
                Swal.fire('Saved!', res.message, 'success').then(() => {
                    $('#logFuelModal').modal('hide');
                    $('#logFuelForm')[0].reset();
                    fuelLogsTable.ajax.reload(null, false);
                });
            }).fail(function(xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#logFuelErrorBanner').text(msg).show();
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Refuel Log');
            });
        });
    });
    @endif
});
</script>
@endsection
