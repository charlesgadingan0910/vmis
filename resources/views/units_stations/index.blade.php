@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Units &amp; Stations</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  /* Modern Stat Card */
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; transition: transform 0.2s; }
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }

  .stat-card-modern.us-units .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.us-stations .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
  .stat-card-modern.us-personnel .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.us-vehicles .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }

  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  /* Main Card & Toolbar (reused for both the Units and Stations sections) */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 6px 20px rgba(15, 23, 42, 0.04); overflow: hidden; margin-bottom: 22px; }
  .toolbar-header { padding: 22px 24px; background: #ffffff; border-bottom: 1px solid #eef1f6; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 220px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13.5px; }
  .search-input-shell input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); outline: none; }
  .btn-add-entity { border-radius: 9px; font-weight: 700; font-size: 13px; padding: 8px 16px; border: none; color: #fff; background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
  .btn-add-entity:hover { color: #fff; opacity: 0.92; }

  /* Table Elements */
  .fleet-table { width: 100% !important; margin: 0 !important; }
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; border: none; padding: 14px 24px; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  .fleet-table tbody tr:hover { background: #f8fafc; }

  /* MOBILE: table rows become stacked cards, not a cramped horizontal scroll —
     same pattern used everywhere else (Vehicles, Drivers, Vehicle Types). */
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
    .fleet-table td:last-child {
      padding-bottom: 14px !important; text-align: right !important;
      border-top: 1px dashed #eef1f6 !important; margin-top: 2px;
    }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }
    /* Column labels are keyed to position and differ per table. */
    #unitsTable td:nth-child(2)::before { content: "Stations"; }
    #stationsTable td:nth-child(2)::before { content: "Parent Unit"; }
  }

  .type-name { font-weight: 700; color: #0f172a; font-size: 14px; }
  .type-desc { font-size: 12.5px; color: #64748b; margin-top: 2px; }

  .action-btn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; transition: all 0.2s; }
  .action-btn:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
  .action-btn.btn-delete:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

  /* DataTables Pagination alignment */
  .dataTables_wrapper .row { padding: 0 24px; align-items: center; }
  .dataTables_info { font-size: 12.5px; color: #64748b; padding-top: 20px !important; }
  .dataTables_paginate { padding-top: 15px !important; padding-bottom: 20px !important; }
  .pagination .page-link { border-radius: 8px; margin: 0 3px; font-size: 13px; border: 1px solid #e2e8f0; color: #334155; }
  .pagination .page-item.active .page-link { background: #3b82f6; border-color: #3b82f6; color: #fff; }

  /* Ultra-Modern Premium Modals */
  .modal-backdrop.show { opacity: 0.65; backdrop-filter: blur(4px); }
  .modal-content-premium { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25); background: #ffffff; }
  .modal-header-slate { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 22px 26px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; letter-spacing: -0.3px; display: flex; align-items: center; }
  .modal-header-slate .close { color: #94a3b8; opacity: 1; transition: 0.2s; text-shadow: none; font-size: 20px; }
  .modal-header-slate .close:hover { color: #fff; }

  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; height: 46px; padding: 10px 14px; color: #0f172a; transition: all 0.2s ease; background: #f8fafc; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); background: #ffffff; outline: none; }
  select.form-control-modern { height: 46px; }
</style>
@endsection

@section('nav-title', 'VMIS | Settings > Units &amp; Stations')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <!-- STATS CARD -->
        <div class="fleet-stats-grid">
            <div class="stat-card-modern us-units">
                <div class="stat-icon-wrapper"><i class="fas fa-building"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_units'] }}</div>
                    <div class="stat-label-title">Total Units</div>
                </div>
            </div>
            <div class="stat-card-modern us-stations">
                <div class="stat-icon-wrapper"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_stations'] }}</div>
                    <div class="stat-label-title">Total Stations</div>
                </div>
            </div>
            <div class="stat-card-modern us-personnel">
                <div class="stat-icon-wrapper"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['personnel_assigned'] }}</div>
                    <div class="stat-label-title">Personnel Assigned</div>
                </div>
            </div>
            <div class="stat-card-modern us-vehicles">
                <div class="stat-icon-wrapper"><i class="fas fa-car"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['vehicles_deployed'] }}</div>
                    <div class="stat-label-title">Vehicles Deployed</div>
                </div>
            </div>
        </div>

        <!-- UNITS TABLE -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-building text-primary"></i> Units Registry</h5>
                    <p>The organizational units vehicles, personnel and stations are grouped under</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="unitSearchBox" class="form-control" placeholder="Search units...">
                    </div>
                    <button type="button" id="resetUnitFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                    <button type="button" class="btn-add-entity" data-toggle="modal" data-target="#createUnitModal">
                        <i class="fas fa-plus mr-1"></i> Add Unit
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="unitsTable">
                    <thead>
                        <tr>
                            <th>Unit Information</th>
                            <th>Stations</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- STATIONS TABLE -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-map-marker-alt text-primary"></i> Stations Registry</h5>
                    <p>The individual stations personnel and vehicles are deployed to</p>
                </div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="stationSearchBox" class="form-control" placeholder="Search stations...">
                    </div>
                    <button type="button" id="resetStationFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                    <button type="button" class="btn-add-entity" data-toggle="modal" data-target="#createStationModal" @if($units->isEmpty()) disabled title="Add a unit first" @endif>
                        <i class="fas fa-plus mr-1"></i> Add Station
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="stationsTable">
                    <thead>
                        <tr>
                            <th>Station Information</th>
                            <th>Parent Unit</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- CREATE UNIT MODAL -->
<div class="modal fade" id="createUnitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-building mr-2 text-primary"></i> Add Unit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('units.store') }}" method="POST" id="createUnitForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" name="unit_name" class="form-control form-control-modern" required maxlength="100" placeholder="e.g. Maintenance Transport Police District">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Abbreviation</label>
                        <input type="text" name="unit_abbvr" class="form-control form-control-modern" maxlength="20" placeholder="e.g. MTPD">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT UNIT MODAL -->
<div class="modal fade" id="editUnitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit Unit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editUnitForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_unit_name" name="unit_name" class="form-control form-control-modern" required maxlength="100">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Abbreviation</label>
                        <input type="text" id="edit_unit_abbvr" name="unit_abbvr" class="form-control form-control-modern" maxlength="20">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-save mr-1"></i> Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CREATE STATION MODAL -->
<div class="modal fade" id="createStationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-map-marker-alt mr-2 text-primary"></i> Add Station</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('stations.store') }}" method="POST" id="createStationForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Parent Unit <span class="text-danger">*</span></label>
                        <select name="unit_id" class="form-control form-control-modern" required>
                            <option value="">Select Unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Station Name <span class="text-danger">*</span></label>
                        <input type="text" name="station_name" class="form-control form-control-modern" required maxlength="100" placeholder="e.g. Quezon City Station">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Abbreviation <span class="text-danger">*</span></label>
                        <input type="text" name="station_abbvr" class="form-control form-control-modern" required maxlength="20" placeholder="e.g. QC-01">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Station</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT STATION MODAL -->
<div class="modal fade" id="editStationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit Station</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editStationForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Parent Unit <span class="text-danger">*</span></label>
                        <select id="edit_station_unit_id" name="unit_id" class="form-control form-control-modern" required>
                            <option value="">Select Unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Station Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_station_name" name="station_name" class="form-control form-control-modern" required maxlength="100">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Abbreviation <span class="text-danger">*</span></label>
                        <input type="text" id="edit_station_abbvr" name="station_abbvr" class="form-control form-control-modern" required maxlength="20">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-save mr-1"></i> Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<!-- DataTables & SweetAlert2 JS Libraries -->
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

    // ================= UNITS TABLE =================
    const unitsTable = $('#unitsTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: { url: "{{ route('units.index') }}" },
        columns: [
            { data: 'name_html', name: 'unit_name' },
            { data: 'count_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading units...'
        }
    });

    let unitSearchTimeout;
    $('#unitSearchBox').on('keyup input', function() {
        clearTimeout(unitSearchTimeout);
        let val = $(this).val();
        unitSearchTimeout = setTimeout(function() { unitsTable.search(val).draw(); }, 300);
    });

    $('#resetUnitFiltersBtn').on('click', function() {
        $('#unitSearchBox').val('');
        unitsTable.search('').draw();
    });

    $('#unitsTable').on('click', '.edit-unit-btn', function() {
        let id = $(this).data('id');
        $('#edit_unit_name').val($(this).data('name'));
        $('#edit_unit_abbvr').val($(this).data('abbvr'));

        let updateUrl = "{{ route('units.update', ':id') }}".replace(':id', id);
        $('#editUnitForm').attr('action', updateUrl);
        $('#editUnitModal').modal('show');
    });

    $('#unitsTable').on('click', '.delete-unit-btn', function() {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let count = parseInt($(this).data('count'));

        if (count > 0) {
            Swal.fire({
                title: 'Cannot Delete Unit',
                text: 'This unit still has ' + count + ' station(s) under it. Please reassign or remove them first.',
                icon: 'error',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'modal-content-premium' }
            });
            return;
        }

        Swal.fire({
            title: 'Delete ' + name + '?',
            text: "This action is permanent and will remove the unit from the system.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                let deleteUrl = "{{ route('units.destroy', ':id') }}".replace(':id', id);
                let form = $('<form>', { method: 'POST', action: deleteUrl });
                form.append($('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }));
                form.append($('<input>', { type: 'hidden', name: '_method', value: 'DELETE' }));
                $('body').append(form);
                form.submit();
            }
        });
    });

    $('#createUnitForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Save Unit?',
            text: "Are you sure you want to add this new unit?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });

    $('#editUnitForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Update Unit?',
            text: "Are you sure you want to save these changes?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, update it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });

    // ================= STATIONS TABLE =================
    const stationsTable = $('#stationsTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: { url: "{{ route('stations.index') }}" },
        columns: [
            { data: 'name_html', name: 'station_name' },
            { data: 'unit_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading stations...'
        }
    });

    let stationSearchTimeout;
    $('#stationSearchBox').on('keyup input', function() {
        clearTimeout(stationSearchTimeout);
        let val = $(this).val();
        stationSearchTimeout = setTimeout(function() { stationsTable.search(val).draw(); }, 300);
    });

    $('#resetStationFiltersBtn').on('click', function() {
        $('#stationSearchBox').val('');
        stationsTable.search('').draw();
    });

    $('#stationsTable').on('click', '.edit-station-btn', function() {
        let id = $(this).data('id');
        $('#edit_station_name').val($(this).data('name'));
        $('#edit_station_abbvr').val($(this).data('abbvr'));
        $('#edit_station_unit_id').val($(this).data('unit-id'));

        let updateUrl = "{{ route('stations.update', ':id') }}".replace(':id', id);
        $('#editStationForm').attr('action', updateUrl);
        $('#editStationModal').modal('show');
    });

    $('#stationsTable').on('click', '.delete-station-btn', function() {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let count = parseInt($(this).data('count'));

        if (count > 0) {
            Swal.fire({
                title: 'Cannot Delete Station',
                text: 'Personnel or vehicles (' + count + ') are currently assigned to "' + name + '". Please reassign them first.',
                icon: 'error',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'modal-content-premium' }
            });
            return;
        }

        Swal.fire({
            title: 'Delete ' + name + '?',
            text: "This action is permanent and will remove the station from the system.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                let deleteUrl = "{{ route('stations.destroy', ':id') }}".replace(':id', id);
                let form = $('<form>', { method: 'POST', action: deleteUrl });
                form.append($('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }));
                form.append($('<input>', { type: 'hidden', name: '_method', value: 'DELETE' }));
                $('body').append(form);
                form.submit();
            }
        });
    });

    $('#createStationForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Save Station?',
            text: "Are you sure you want to add this new station?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });

    $('#editStationForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Update Station?',
            text: "Are you sure you want to save these changes?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, update it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    });
});
</script>
@endsection
