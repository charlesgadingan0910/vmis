@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Vehicle Categories</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('css')
<style>
  /* Modern Stat Card */
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 22px; max-width: 500px; }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 18px; }
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  /* Main Card & Toolbar */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 6px 20px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; background: #ffffff; border-bottom: 1px solid #eef1f6; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .search-input-shell { position: relative; width: 280px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13.5px; }
  .search-input-shell input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); outline: none; }
  
  /* Table Elements */
  .fleet-table { width: 100% !important; margin: 0 !important; }
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; border: none; padding: 14px 24px; }
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  .fleet-table tbody tr:hover { background: #f8fafc; }

  /* MOBILE: table rows become stacked cards, not a cramped horizontal scroll —
     same pattern used on Vehicle Inventory, Driver Management and Maintenance. */
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
    .fleet-table td:nth-child(2)::before { content: "Active Units Assigned"; }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }
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
  textarea.form-control-modern { height: auto; }
</style>
@endsection

@section('nav-title', 'VMIS | Settings > Vehicle Types')

@section('nav-actions')
<button class="btn-nav-action btn-nav-action-primary" data-toggle="modal" data-target="#createModal">
  <i class="fas fa-plus"></i> <span class="btn-label">Add Category</span>
</button>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        
        <!-- STATS CARD -->
        <div class="fleet-stats-grid">
            <div class="stat-card-modern">
                <div class="stat-icon-wrapper"><i class="fas fa-tags"></i></div>
                <div>
                    <div class="stat-num-value">{{ $stats['total_types'] }}</div>
                    <div class="stat-label-title">Vehicle Categories</div>
                </div>
            </div>
        </div>

        <!-- MAIN TABLE CONTAINER -->
        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5>Categories Registry</h5>
                    <p>Manage classification groups for your active vehicles</p>
                </div>
                <div class="search-input-shell">
                    <i class="fas fa-search"></i>
                    <input type="text" id="customSearchBox" class="form-control" placeholder="Search vehicle types...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="vehicleTypesTable">
                    <thead>
                        <tr>
                            <th>Category Information</th>
                            <th>Active Units Assigned</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- CREATE MODAL -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-folder-plus mr-2 text-primary"></i> Add Vehicle Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('vehicle-types.store') }}" method="POST" id="createForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-modern" required placeholder="e.g. Patrol Car">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Description</label>
                        <textarea name="description" class="form-control form-control-modern" rows="3" placeholder="Brief classification details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-light font-weight-bold px-4 py-2" data-dismiss="modal" style="border-radius: 9px;">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4 py-2" style="border-radius: 9px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none;"><i class="fas fa-check mr-1"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit Vehicle Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Category Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_name" name="name" class="form-control form-control-modern" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold font-size-12 text-secondary text-uppercase mb-2">Description</label>
                        <textarea id="edit_desc" name="description" class="form-control form-control-modern" rows="3"></textarea>
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

    // INITIALIZE FAST AJAX DATATABLE FOR VEHICLE TYPES
    const table = $('#vehicleTypesTable').DataTable({
        processing: true,
        serverSide: true,
        // Without this, DataTables locks the <table> to an inline pixel
        // width at init time, which would override our CSS width:100% and
        // break the mobile "stack into cards" layout below.
        autoWidth: false,
        ajax: {
            url: "{{ route('vehicle-types.index') }}"
        },
        columns: [
            { data: 'name_html', name: 'name' },
            { data: 'count_html', orderable: false, searchable: false },
            { data: 'actions_html', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>',
        pageLength: 10,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading categories...'
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

    // Event delegation for dynamic Edit buttons
    $('#vehicleTypesTable').on('click', '.edit-btn', function() {
        let typeId = $(this).data('id');
        $('#edit_name').val($(this).data('name'));
        $('#edit_desc').val($(this).data('desc'));
        
        let updateUrl = "{{ route('vehicle-types.update', ':id') }}".replace(':id', typeId);
        $('#editForm').attr('action', updateUrl);
        $('#editModal').modal('show');
    });

    // Event delegation for dynamic Delete buttons with SweetAlert2 Validation
    $('#vehicleTypesTable').on('click', '.delete-btn', function() {
        let typeId = $(this).data('id');
        let typeName = $(this).data('name');
        let count = parseInt($(this).data('count'));

        if (count > 0) {
            Swal.fire({
                title: 'Cannot Delete Category',
                text: 'Active vehicles (' + count + ' units) are currently assigned to "' + typeName + '". Please reassign them first.',
                icon: 'error',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'modal-content-premium' }
            });
            return;
        }

        Swal.fire({
            title: 'Delete ' + typeName + '?',
            text: "This action is permanent and will remove the vehicle category from the system.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                let deleteUrl = "{{ route('vehicle-types.destroy', ':id') }}".replace(':id', typeId);
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

    // SweetAlert2 Confirmation for Saving New Record
    $('#createForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Save Category?',
            text: "Are you sure you want to add this new vehicle category?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // SweetAlert2 Confirmation for Editing Record
    $('#editForm').submit(function(e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Update Category?',
            text: "Are you sure you want to save these changes?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, update it!',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'modal-content-premium' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endsection