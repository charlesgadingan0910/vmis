@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Vehicle Management</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
@endsection

@section('css')
<style>
  /* Summary Dashboard Cards */
  .fleet-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
  }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .stat-card-modern {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    border: 1px solid #eef1f6;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .stat-card-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
  }
  .stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
  }
  .stat-card-modern.total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.serviceable .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.unserviceable .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.ber .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  /* Main Card & Toolbar */
  .fleet-card-container {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #eef1f6;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 6px 20px rgba(15, 23, 42, 0.04);
    overflow: hidden;
  }
  .toolbar-header {
    padding: 22px 24px;
    background: #ffffff;
    border-bottom: 1px solid #eef1f6;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .filter-bar {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: center;
  }
  .search-input-shell { position: relative; width: 230px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .search-input-shell input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); outline: none; }
  
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 170px; }
  .custom-filter-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }

  /* Modern Table Elements */
  .fleet-table { width: 100% !important; margin: 0 !important; }
  .fleet-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: none;
    padding: 14px 24px;
    white-space: nowrap;
  }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  .fleet-table tbody tr:hover { background: #f8fafc; }

  .plate-badge {
    font-family: 'Courier New', monospace;
    font-weight: 800;
    letter-spacing: 0.06em;
    font-size: 13px;
    background: #1e293b;
    color: #ffffff;
    padding: 6px 12px;
    border-radius: 6px;
    display: inline-block;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  .vehicle-main-name { font-weight: 700; color: #0f172a; font-size: 14px; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; margin-top: 2px; }

  .driver-chip-wrapper { display: flex; align-items: center; gap: 10px; }
  .driver-avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .driver-name-text { font-size: 13px; font-weight: 600; color: #1e293b; }
  .driver-none { color: #94a3b8; font-style: italic; font-size: 12.5px; }

  /* New Status Pills */
  .status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; }
  .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; }
  .status-serviceable { background: #eaf6ef; color: #16a34a; } .status-serviceable .dot { background: #16a34a; }
  .status-unserviceable { background: #fff4e5; color: #d97706; } .status-unserviceable .dot { background: #d97706; }
  .status-ber { background: #fcedec; color: #dc2626; } .status-ber .dot { background: #dc2626; }

  .pms-normal { color: #334155; }
  .pms-overdue { color: #dc2626; font-weight: 700; }
  .pms-soon { color: #d97706; font-weight: 700; }

  /* DataTables Pagination */
  .dataTables_wrapper .row { padding: 0 24px; align-items: center; }
  .dataTables_info { font-size: 12.5px; color: #64748b; padding-top: 20px !important; }
  .dataTables_paginate { padding-top: 15px !important; padding-bottom: 20px !important; }
  .pagination .page-link { border-radius: 8px; margin: 0 3px; font-size: 13px; border: 1px solid #e2e8f0; color: #334155; }
  .pagination .page-item.active .page-link { background: #3b82f6; border-color: #3b82f6; color: #fff; }

  /* Modal Enhancements */
  .modal-backdrop.show { opacity: 0.65; backdrop-filter: blur(4px); }
  .modal-content-premium { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px 24px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }
  .modal-header-slate p { color: #94a3b8; font-size: 12.5px; margin: 2px 0 0; }
  .modal-header-slate .close { color: #94a3b8; opacity: 1; transition: color 0.2s; }
  .modal-header-slate .close:hover { color: #ffffff; }

  .modal-section-divider {
    font-size: 11.5px;
    font-weight: 800;
    color: #3b82f6;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 18px 0 12px;
    padding-bottom: 6px;
    border-bottom: 1.5px solid #f1f5f9;
  }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; transition: all 0.2s ease; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
  
  .live-checker-banner {
    display: none;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 600;
    align-items: center;
    gap: 10px;
  }
  .live-checker-banner.warning { display: flex; background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; }
  .field-feedback-text { font-size: 11.5px; font-weight: 600; margin-top: 4px; display: block; }
  .field-feedback-text.error { color: #dc2626; }
  .field-feedback-text.success { color: #16a34a; }
</style>
@endsection

@section('nav-title', 'VMIS | Vehicle Management')

@section('nav-actions')
<button class="btn-nav-action btn-nav-action-primary" data-toggle="modal" data-target="#registerVehicleModal">
  <i class="fas fa-plus"></i> Register Vehicle
</button>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <!-- DASHBOARD SUMMARY CARDS (SERVICEABLE, UNSERVICEABLE, BER) -->
        <div class="fleet-stats-grid">
            <div class="stat-card-modern total">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total'] }}</div>
                    <div class="stat-label-title">Total Vehicles</div>
                </div>
            </div>
            <div class="stat-card-modern serviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['serviceable'] }}</div>
                    <div class="stat-label-title">Serviceable</div>
                </div>
            </div>
            <div class="stat-card-modern unserviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-tools"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['unserviceable'] }}</div>
                    <div class="stat-label-title">Unserviceable</div>
                </div>
            </div>
            <div class="stat-card-modern ber">
                <div class="stat-icon-wrapper"><i class="fas fa-ban"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['ber'] }}</div>
                    <div class="stat-label-title">BER</div>
                </div>
            </div>
        </div>

        <!-- MAIN AJAX TABLE CONTAINER -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5>Vehicle Inventory</h5>
                    <p>Real-time server-side data processing</p>
                </div>

                <!-- FILTERS -->
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search plate, make, model…">
                    </div>

                    <select id="filterVehicleType" class="form-control custom-filter-select">
                        <option value="">All Vehicle Types</option>
                        @foreach($vehicleTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>

                    <select id="filterStatus" class="form-control custom-filter-select">
                        <option value="">All Statuses</option>
                        <option value="SERVICEABLE">Serviceable</option>
                        <option value="UNSERVICEABLE">Unserviceable</option>
                        <option value="BER">BER</option>
                    </select>

                    <button type="button" id="resetFiltersBtn" class="btn btn-light btn-sm font-weight-bold border text-secondary" style="border-radius:9px; height:38px; display:inline-flex; align-items:center; padding: 0 12px;">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- AJAX TABLE -->
            <div class="table-responsive">
                <table class="table fleet-table" id="vehiclesTable">
                    <thead>
                        <tr>
                            <th>Plate Number</th>
                            <th>Vehicle Specification</th>
                            <th>Type</th>
                            <th>Assigned Driver</th>
                            <th>Next PMS Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- REGISTER MODAL -->
<div class="modal fade" id="registerVehicleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex align-items-center justify-content-between">
                <div>
                    <h5><i class="fas fa-car-side mr-2 text-primary"></i> Register New Vehicle</h5>
                    <p>Add a new unit to the fleet registry with instant verification.</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form method="POST" action="{{ route('vehicles.store') }}" id="vehicleRegistrationForm">
                @csrf
                <div class="modal-body p-4">
                    <div id="modalLiveBanner" class="live-checker-banner warning">
                        <i class="fas fa-exclamation-triangle font-size-16"></i>
                        <span id="modalLiveBannerText">Warning: Duplicate record detected.</span>
                    </div>

                    <div class="modal-section-divider mt-0">Identity & Classification</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Plate Number <span class="text-danger">*</span></label>
                            <input type="text" id="plate_number" name="plate_number" value="{{ old('plate_number') }}" 
                                   class="form-control form-control-modern live-check-field" 
                                   data-field="plate_number" placeholder="e.g. SGF 1234" required autocomplete="off">
                            <span class="field-feedback-text" id="feedback-plate_number"></span>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Vehicle Type <span class="text-danger">*</span></label>
                            <select name="vehicle_type_id" class="form-control form-control-modern" required>
                                <option value="">Select type...</option>
                                @foreach ($vehicleTypes as $type)
                                <option value="{{ $type->id }}" {{ old('vehicle_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Assigned Driver</label>
                            <select name="assigned_driver_id" class="form-control form-control-modern">
                                <option value="">Unassigned</option>
                                @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ old('assigned_driver_id') == $driver->id ? 'selected' : '' }}>
                                    {{ $driver->firstname }} {{ $driver->lastname }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Make <span class="text-danger">*</span></label>
                            <input type="text" name="make" value="{{ old('make') }}" class="form-control form-control-modern" placeholder="e.g. Toyota" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Model <span class="text-danger">*</span></label>
                            <input type="text" name="model" value="{{ old('model') }}" class="form-control form-control-modern" placeholder="e.g. Hilux" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Year Model</label>
                            <input type="number" name="year_model" value="{{ old('year_model') }}" class="form-control form-control-modern" min="1980" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}">
                        </div>
                    </div>

                    <div class="modal-section-divider">Identifiers & Engine Specs</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Engine Number</label>
                            <input type="text" id="engine_number" name="engine_number" value="{{ old('engine_number') }}" 
                                   class="form-control form-control-modern live-check-field" 
                                   data-field="engine_number" placeholder="Engine serial number" autocomplete="off">
                            <span class="field-feedback-text" id="feedback-engine_number"></span>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Chassis Number</label>
                            <input type="text" id="chassis_number" name="chassis_number" value="{{ old('chassis_number') }}" 
                                   class="form-control form-control-modern live-check-field" 
                                   data-field="chassis_number" placeholder="VIN / Chassis serial number" autocomplete="off">
                            <span class="field-feedback-text" id="feedback-chassis_number"></span>
                        </div>
                    </div>

                    <div class="modal-section-divider">Operational Metrics & Status</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Color</label>
                            <input type="text" name="color" value="{{ old('color') }}" class="form-control form-control-modern" placeholder="e.g. White">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Odometer (km)</label>
                            <input type="number" name="odometer_km" value="{{ old('odometer_km', 0) }}" class="form-control form-control-modern" min="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Next PMS Date</label>
                            <input type="date" name="next_pms_date" value="{{ old('next_pms_date') }}" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Initial Status <span class="text-danger">*</span></label>
                            <!-- UPDATED STATUS OPTIONS -->
                            <select name="status" class="form-control form-control-modern" required>
                                <option value="SERVICEABLE" {{ old('status') === 'SERVICEABLE' ? 'selected' : '' }}>Serviceable</option>
                                <option value="UNSERVICEABLE" {{ old('status') === 'UNSERVICEABLE' ? 'selected' : '' }}>Unserviceable</option>
                                <option value="BER" {{ old('status') === 'BER' ? 'selected' : '' }}>BER</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #eef1f6; padding: 16px 24px;">
                    <button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal" style="border-radius: 9px; padding: 10px 20px;">Cancel</button>
                    <button type="submit" id="btnSubmitVehicle" class="btn btn-primary font-weight-bold" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none; border-radius: 9px; padding: 10px 24px;">
                        <i class="fas fa-check mr-1"></i> Save Vehicle Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document.body).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    @if (session('success'))
        toastr.success(@json(session('success')));
    @endif
    
    @if ($errors->any())
        $('#registerVehicleModal').modal('show');
        toastr.error('Validation failed. Please review the form fields.');
    @endif

    const table = $('#vehiclesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('vehicles.index') }}",
            data: function (d) {
                d.vehicle_type_id = $('#filterVehicleType').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'plate_html', name: 'plate_number' },
            { data: 'spec_html', name: 'make' },
            { data: 'type_html', name: 'vehicle_type_id', orderable: false },
            { data: 'driver_html', name: 'assigned_driver_id', orderable: false },
            { data: 'pms_html', name: 'next_pms_date' },
            { data: 'status_html', name: 'status' }
        ],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading records...'
        }
    });

    $('#registerVehicleModal').on('show.bs.modal', function () {
        $('#modalLiveBanner').hide();
        $('#btnSubmitVehicle').prop('disabled', false).css('opacity', '1');
        duplicateState.plate_number = false;
        duplicateState.engine_number = false;
        duplicateState.chassis_number = false;
    });

    let searchTimeout;
    $('#customSearchBox').on('keyup input', function() {
        clearTimeout(searchTimeout);
        let val = $(this).val();
        searchTimeout = setTimeout(function() {
            table.search(val).draw();
        }, 300);
    });

    $('#filterVehicleType, #filterStatus').change(function() {
        table.draw();
    });

    $('#resetFiltersBtn').click(function() {
        $('#customSearchBox').val('');
        $('#filterVehicleType').val('');
        $('#filterStatus').val('');
        table.search('').draw();
    });

    let typingTimer;
    const duplicateState = { plate_number: false, engine_number: false, chassis_number: false };

    $('.live-check-field').on('keyup input', function() {
        const inputElem = $(this);
        const fieldName = inputElem.data('field');
        const value = inputElem.val().trim();

        clearTimeout(typingTimer);
        if (value.length < 2) {
            resetFieldStatus(inputElem, fieldName);
            return;
        }

        typingTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('vehicles.check-availability') }}",
                type: "POST",
                data: { field: fieldName, value: value },
                success: function(response) {
                    const feedbackElem = $('#feedback-' + fieldName);
                    if (response.exists) {
                        duplicateState[fieldName] = true;
                        inputElem.addClass('is-invalid').removeClass('is-valid');
                        feedbackElem.removeClass('success').addClass('error').html('<i class="fas fa-times-circle"></i> Already exists! (' + response.make_model + ')');
                    } else {
                        duplicateState[fieldName] = false;
                        inputElem.addClass('is-valid').removeClass('is-invalid');
                        feedbackElem.removeClass('error').addClass('success').html('<i class="fas fa-check-circle"></i> Available');
                    }
                    updateModalGlobalState();
                }
            });
        }, 400);
    });

    function resetFieldStatus(inputElem, fieldName) {
        duplicateState[fieldName] = false;
        inputElem.removeClass('is-invalid is-valid');
        $('#feedback-' + fieldName).html('').removeClass('error success');
        updateModalGlobalState();
    }

    function updateModalGlobalState() {
        const hasDuplicate = duplicateState.plate_number || duplicateState.engine_number || duplicateState.chassis_number;
        const banner = $('#modalLiveBanner');
        const submitBtn = $('#btnSubmitVehicle');

        if (hasDuplicate) {
            banner.show();
            submitBtn.prop('disabled', true).css('opacity', '0.65');
        } else {
            banner.hide();
            submitBtn.prop('disabled', false).css('opacity', '1');
        }
    }
});
</script>
@endsection