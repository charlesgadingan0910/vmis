@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Trip Logs</title>
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
  .stat-card-modern.tl-total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.tl-today .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.tl-month .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
  .stat-card-modern.tl-vehicles .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
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
    .fleet-table td:nth-child(3)::before { content: "Trip Date"; }
    .fleet-table td:nth-child(4)::before { content: "Route"; }
    .fleet-table td:nth-child(5)::before { content: "Odometer"; }
    .fleet-table td:nth-child(6)::before { content: "Logged By"; }
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
  textarea.form-control-modern { height: auto; }
</style>
@endsection

@section('nav-title', 'VMIS | Trip Logs')

@if($canLogForAnyVehicle)
@section('nav-actions')
<button type="button" class="btn-add-entity" data-toggle="modal" data-target="#logTripModal">
    <i class="fas fa-route mr-1"></i> <span class="btn-label">Log Trip</span>
</button>
@endsection
@endif

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern tl-total">
                <div class="stat-icon-wrapper"><i class="fas fa-route"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_trips'] }}</div>
                    <div class="stat-label-title">Total Trips Logged</div>
                </div>
            </div>
            <div class="stat-card-modern tl-today">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['logged_today'] }}</div>
                    <div class="stat-label-title">Logged Today</div>
                </div>
            </div>
            <div class="stat-card-modern tl-month">
                <div class="stat-icon-wrapper"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['logged_month'] }}</div>
                    <div class="stat-label-title">Logged This Month</div>
                </div>
            </div>
            <div class="stat-card-modern tl-vehicles">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['vehicles_logged'] }}</div>
                    <div class="stat-label-title">Vehicles With Trips</div>
                </div>
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-route text-primary"></i> Trip History</h5>
                    <p>Every logged trip{{ $hasBroadVisibility ? ' fleet-wide' : ' within your assigned scope' }}</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="tripSearchBox" class="form-control" placeholder="Search trips...">
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
                    <button type="button" id="resetTripFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table w-100" id="tripLogsTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Trip Date / Time</th>
                            <th>Route / Purpose</th>
                            <th>Odometer</th>
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
<!-- LOG TRIP MODAL (Super Administrator only) -->
<div class="modal fade" id="logTripModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-route mr-2 text-primary"></i> Log a Trip</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="logTripForm">
                @csrf
                <div class="modal-body p-4">
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
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Trip Date <span class="text-danger">*</span></label>
                            <input type="date" name="trip_date" class="form-control form-control-modern" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Departure</label>
                            <input type="time" name="departure_time" class="form-control form-control-modern">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Arrival</label>
                            <input type="time" name="arrival_time" class="form-control form-control-modern">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Origin <span class="text-danger">*</span></label>
                            <input type="text" name="origin" class="form-control form-control-modern" required maxlength="150">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Destination <span class="text-danger">*</span></label>
                            <input type="text" name="destination" class="form-control form-control-modern" required maxlength="150">
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Purpose</label>
                        <input type="text" name="purpose" class="form-control form-control-modern" maxlength="255">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Odometer (Start)</label>
                            <input type="number" name="odometer_start" class="form-control form-control-modern" min="0">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Odometer (End)</label>
                            <input type="number" name="odometer_end" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Passenger(s)</label>
                        <input type="text" name="passengers" class="form-control form-control-modern" maxlength="255">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-modern" rows="2" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Trip Log</button>
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

    const tripLogsTable = $('#tripLogsTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('trip-logs.index') }}",
            data: function(d) {
                d.unit_id = $('#filterUnit').val();
                d.station_id = $('#filterStation').val();
                d.vehicle_id = $('#filterVehicle').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.search = { value: $('#tripSearchBox').val() };
            }
        },
        columns: [
            { data: 'vehicle_html', orderable: false, searchable: false },
            { data: 'driver_html', orderable: false, searchable: false },
            { data: 'trip_html', orderable: false, searchable: false },
            { data: 'route_html', orderable: false, searchable: false },
            { data: 'odometer_html', orderable: false, searchable: false },
            { data: 'logged_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading trips...'
        }
    });

    let searchTimeout;
    $('#tripSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { tripLogsTable.draw(); }, 300);
    });
    $('#filterUnit, #filterStation, #filterVehicle, #filterDateFrom, #filterDateTo').on('change', function() {
        tripLogsTable.draw();
    });
    $('#resetTripFiltersBtn').on('click', function() {
        $('#tripSearchBox, #filterUnit, #filterStation, #filterVehicle, #filterDateFrom, #filterDateTo').val('');
        tripLogsTable.draw();
    });

    @if($canLogForAnyVehicle)
    $('#logTripForm').on('submit', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Save Trip Log?',
            text: "Trip logs are permanent records and cannot be edited or deleted afterward.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Save It'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('trip-logs.store') }}",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {
                        if (res.success) {
                            Swal.fire('Saved!', res.message, 'success').then(() => {
                                $('#logTripModal').modal('hide');
                                $('#logTripForm')[0].reset();
                                tripLogsTable.ajax.reload(null, false);
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
    @endif
});
</script>
@endsection
