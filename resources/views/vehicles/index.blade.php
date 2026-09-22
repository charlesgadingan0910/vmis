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
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .type-breakdown-card{background:#ffffff;border-radius:14px;padding:18px 20px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);margin-bottom:22px;}
  .type-breakdown-header{font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.03em;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
  .type-breakdown-header i{color:#3b82f6;}
  .type-breakdown-chips{display:flex;flex-wrap:wrap;gap:10px;}
  .type-chip{
    display:inline-flex;align-items:center;gap:9px;padding:8px 8px 8px 14px;border-radius:10px;
    background:#f8fafc;border:1.5px solid #e2e8f0;font-size:13px;font-weight:600;color:#334155;
    cursor:pointer;transition:all .15s ease;user-select:none;
  }
  .type-chip:hover{border-color:#93c5fd;background:#eff6ff;}
  .type-chip.active{border-color:#3b82f6;background:#3b82f6;color:#fff;}
  .type-chip.empty{opacity:0.5;}
  .type-chip .count-badge{
    background:rgba(59,130,246,0.12);color:#3b82f6;font-size:11.5px;font-weight:800;
    padding:3px 9px;border-radius:20px;min-width:22px;text-align:center;line-height:1.3;
  }
  .type-chip.active .count-badge{background:rgba(255,255,255,0.25);color:#fff;}
  .type-breakdown-empty{font-size:13px;color:#94a3b8;}
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03); display: flex; align-items: center; gap: 16px; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.total .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.serviceable .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.unserviceable .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.ber .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }
  
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 22px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #eef1f6;}
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .search-input-shell { position: relative; width: 200px; }
  .search-input-shell i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 5; }
  .search-input-shell input { padding-left: 36px; border-radius: 9px; border: 1.5px solid #e2e8f0; height: 38px; font-size: 13px; }
  .custom-filter-select { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; width: 140px; }
  
  .fleet-table thead th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 14px 24px; white-space: nowrap; border: none;}
  .fleet-table tbody td { padding: 16px 24px; vertical-align: middle; border-top: 1px solid #f1f5f9; font-size: 13.5px; }
  .plate-badge { font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #1e293b; color: #ffffff; padding: 6px 12px; border-radius: 6px; }
  .vehicle-main-name { font-weight: 700; color: #0f172a; font-size: 14px; }
  .vehicle-sub-info { font-size: 12px; color: #64748b; margin-top: 2px; }
  .driver-chip-wrapper { display: flex; align-items: center; gap: 10px; }
  .driver-avatar-circle { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center;}
  .driver-name-text { font-size: 13px; font-weight: 600; color: #1e293b; }
  .driver-none { color: #94a3b8; font-style: italic; font-size: 12.5px; }
  
  .status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; }
  .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; }
  .status-serviceable { background: #eaf6ef; color: #16a34a; } .status-serviceable .dot { background: #16a34a; }
  .status-unserviceable { background: #fff4e5; color: #d97706; } .status-unserviceable .dot { background: #d97706; }
  .status-ber { background: #fcedec; color: #dc2626; } .status-ber .dot { background: #dc2626; }
  
  .modal-content-premium { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
  .modal-header-slate { background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px 24px; border-bottom: none; }
  .modal-header-slate h5 { font-weight: 800; font-size: 17px; margin: 0; }

  /* ---------- QR modal: premium credential-card layout ---------- */
  .qr-modal-hero{
    background:linear-gradient(135deg,#1e293b,#0f172a); color:#fff; padding:22px 24px 20px; position:relative; overflow:hidden;
  }
  .qr-modal-hero::after{content:'';position:absolute;top:-40%;right:-20%;width:60%;height:60%;background:radial-gradient(circle,rgba(59,130,246,0.25),transparent 65%);}
  .qr-modal-hero .qr-plate-badge{
    position:relative;z-index:1;font-family:'Courier New',monospace;font-weight:800;font-size:20px;letter-spacing:.05em;
    background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);display:inline-block;padding:6px 14px;border-radius:9px;
  }
  .qr-modal-hero .qr-veh-name{position:relative;z-index:1;font-size:14.5px;font-weight:600;margin-top:10px;color:#cbd5e1;}
  .qr-modal-hero .qr-veh-sub{position:relative;z-index:1;font-size:12px;color:#94a3b8;margin-top:2px;}
  .qr-modal-hero .qr-status-chip{
    position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;
    padding:4px 11px;border-radius:20px;margin-top:12px;text-transform:capitalize;
  }
  .qr-status-chip.SERVICEABLE{background:rgba(34,197,94,0.16);color:#4ade80;}
  .qr-status-chip.UNSERVICEABLE{background:rgba(245,158,11,0.18);color:#fbbf24;}
  .qr-status-chip.BER{background:rgba(239,68,68,0.18);color:#f87171;}

  .qr-code-frame{
    width:240px;height:240px;margin:22px auto 0;background:#fff;border:1.5px solid #eef1f6;border-radius:16px;
    box-shadow:0 8px 24px rgba(15,23,42,0.08); display:flex;align-items:center;justify-content:center;padding:16px;
  }
  .qr-code-frame img{width:100%;height:100%;}

  .qr-provenance{margin:18px 24px 4px;padding:14px 16px;background:#f8fafc;border-radius:12px;border:1px solid #eef1f6;}
  .qr-provenance-row{display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#64748b;padding:4px 0;}
  .qr-provenance-row i{color:#3b82f6;margin-top:2px;width:13px;text-align:center;}
  .qr-provenance-row b{color:#334155;font-weight:600;}
  .modal-section-divider { font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; margin: 18px 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid #f1f5f9; }
  .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; }
  .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
  
  .history-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 12px; }
  .history-year { font-size: 18px; font-weight: 800; color: #0f172a; }
  .live-checker-banner { display: none; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; font-weight: 600; align-items: center; gap: 10px; }
  .live-checker-banner.warning { display: flex; background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; }
  .field-feedback-text { font-size: 11.5px; font-weight: 600; margin-top: 4px; display: block; }
  .field-feedback-text.error { color: #dc2626; }
  .field-feedback-text.success { color: #16a34a; }

  /* ============================================================ */
  /* MOBILE: table rows become stacked cards, not a cramped scroll */
  /* ============================================================ */
  @media (max-width: 767.98px) {
    .fleet-table thead { display: none; }
    .fleet-table, .fleet-table tbody, .fleet-table tr, .fleet-table td { display: block; width: 100%; }
    .fleet-table tr {
      background: #fff; border: 1px solid #eef1f6; border-radius: 14px;
      box-shadow: 0 1px 3px rgba(15,23,42,0.04); margin-bottom: 12px; padding: 4px 16px;
    }
    .fleet-table td {
      padding: 10px 0 !important; border-top: 1px solid #f8fafc !important; text-align: left !important;
    }
    .fleet-table td:first-child { border-top: none !important; padding-top: 14px !important; }
    .fleet-table td:last-child { padding-bottom: 14px !important; }

    /* Column order here is fixed (matches the columns: [...] config below), so
       labels are keyed to position — Plate, Status and Actions read fine on
       their own (a plate badge or status pill needs no extra label), the rest
       get a small caption so it's clear what each stacked value actually is. */
    .fleet-table td:nth-child(3)::before { content: "Specification"; }
    .fleet-table td:nth-child(4)::before { content: "Type"; }
    .fleet-table td:nth-child(5)::before { content: "Driver"; }
    .fleet-table td:nth-child(6)::before { content: "Registration"; }
    .fleet-table td:nth-child(7)::before { content: "Next PMS"; }
    .fleet-table td::before {
      display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: #94a3b8; margin-bottom: 5px;
    }

    .fleet-table td:last-child {
      text-align: right !important; border-top: 1px dashed #eef1f6 !important; margin-top: 2px;
    }
    /* The select-all checkbox column: unobtrusive, no label, sits compactly
       at the top of the card rather than eating a full labeled row. */
    .fleet-table td:first-child { padding-bottom: 2px !important; }
  }
</style>
@endsection

@section('nav-title', 'VMIS | Vehicle Management')

@section('nav-actions')
<button type="button" id="printSelectedQrBtn" class="btn btn-outline-secondary font-weight-bold shadow-sm mr-2" style="border-radius:8px;" disabled>
  <i class="fas fa-print"></i> <span class="btn-label">Print QR</span> <span class="badge badge-primary ml-1" id="selectedCountBadge" style="display:none;">0</span>
</button>
@if (! $isViewer)
<button class="btn btn-primary font-weight-bold shadow-sm" style="border-radius:8px;" data-toggle="modal" data-target="#registerVehicleModal">
  <i class="fas fa-plus"></i> <span class="btn-label">Register Vehicle</span>
</button>
@endif
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-stats-grid">
            <div class="stat-card-modern total">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div><div class="stat-num-value">{{ $stats['total'] }}</div><div class="stat-label-title">Total Vehicles</div></div>
            </div>
            <div class="stat-card-modern serviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-num-value">{{ $stats['serviceable'] }}</div><div class="stat-label-title">Serviceable</div></div>
            </div>
            <div class="stat-card-modern unserviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-tools"></i></div>
                <div><div class="stat-num-value">{{ $stats['unserviceable'] }}</div><div class="stat-label-title">Unserviceable</div></div>
            </div>
            <div class="stat-card-modern ber">
                <div class="stat-icon-wrapper"><i class="fas fa-ban"></i></div>
                <div><div class="stat-num-value">{{ $stats['ber'] }}</div><div class="stat-label-title">BER</div></div>
            </div>
        </div>

        <div class="type-breakdown-card">
            <div class="type-breakdown-header"><i class="fas fa-layer-group"></i> Vehicles by Type — click a type to filter</div>
            <div class="type-breakdown-chips" id="typeBreakdownChips">
                @forelse ($vehicleTypes as $type)
                <div class="type-chip" data-type-id="{{ $type->id }}">
                    <span>{{ $type->name }}</span>
                    <span class="count-badge">{{ $type->vehicles_count ?? 0 }}</span>
                </div>
                @empty
                <span class="type-breakdown-empty">No vehicle types configured yet.</span>
                @endforelse
            </div>
        </div>

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title"><h5>Vehicle Inventory</h5></div>
                <div class="filter-bar">
                    <div class="search-input-shell">
                        <i class="fas fa-search"></i>
                        <input type="text" id="customSearchBox" class="form-control" placeholder="Search...">
                    </div>
                    
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

                    <select id="filterVehicleType" class="form-control custom-filter-select" style="width:170px;">
                        <option value="">All Types</option>
                        @foreach ($vehicleTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>

                    <select id="filterStatus" class="form-control custom-filter-select" style="width:120px;">
                        <option value="">All Statuses</option>
                        <option value="SERVICEABLE">Serviceable</option>
                        <option value="UNSERVICEABLE">Unserviceable</option>
                        <option value="BER">BER</option>
                    </select>

                    <button type="button" id="resetFiltersBtn" class="btn btn-light border text-secondary"><i class="fas fa-undo"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table fleet-table" id="vehiclesTable">
                    <thead>
                        <tr>
                            <th style="width:34px;"><input type="checkbox" id="selectAllRows"></th>
                            <th>Plate</th>
                            <th>Specification</th>
                            <th>Type</th>
                            <th>Driver</th>
                            <th>Registration</th>
                            <th>PMS</th>
                            <th>Status</th>
                            <th>Actions</th>
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
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate">
                <h5><i class="fas fa-car-side mr-2 text-primary"></i> Register New Vehicle</h5>
            </div>
            
            <form method="POST" action="{{ route('vehicles.store') }}" enctype="multipart/form-data" id="registerVehicleForm">
                @csrf
                <div class="modal-body p-4">
                    <div id="modalLiveBanner" class="live-checker-banner warning">
                        <i class="fas fa-exclamation-triangle font-size-16"></i>
                        <span id="modalLiveBannerText">Warning: Duplicate record detected.</span>
                    </div>

                    <div class="modal-section-divider mt-0">Identity & Classification</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Plate Number <span class="text-danger">*</span></label>
                            <input type="text" id="plate_number" name="plate_number" class="form-control form-control-modern live-check-field" data-field="plate_number" required autocomplete="off">
                            <span class="field-feedback-text" id="feedback-plate_number"></span>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Make <span class="text-danger">*</span></label>
                            <input type="text" name="make" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Model <span class="text-danger">*</span></label>
                            <input type="text" name="model" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Vehicle Type <span class="text-danger">*</span></label>
                            <select name="vehicle_type_id" class="form-control form-control-modern" required>
                                <option value="">Select type...</option>
                                @foreach ($vehicleTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-divider">Specifications & Identifiers</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Year Model</label>
                            <input type="number" name="year_model" class="form-control form-control-modern" min="1980" max="{{ date('Y') + 1 }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Color</label>
                            <input type="text" name="color" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Engine Number</label>
                            <input type="text" name="engine_number" class="form-control form-control-modern live-check-field" data-field="engine_number" autocomplete="off">
                            <span class="field-feedback-text" id="feedback-engine_number"></span>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Chassis Number</label>
                            <input type="text" name="chassis_number" class="form-control form-control-modern live-check-field" data-field="chassis_number" autocomplete="off">
                            <span class="field-feedback-text" id="feedback-chassis_number"></span>
                        </div>
                    </div>

                    <div class="modal-section-divider">Deployment & Status</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="formUnitId" class="form-control form-control-modern" required>
                                <option value="">Select Unit...</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}" {{ $units->count() === 1 ? 'selected' : '' }}>{{ $u->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Station <span class="text-danger">*</span></label>
                            <select name="station_id" id="formStationId" class="form-control form-control-modern" required>
                                <option value="">Select Station...</option>
                                @foreach ($stations as $s)
                                    <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}">{{ $s->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Driver</label>
                            <select name="assigned_driver_id" class="form-control form-control-modern">
                                <option value="">Unassigned</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">
                                        {{ $driver->firstname }} {{$driver->lastname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control form-control-modern" required>
                                <option value="SERVICEABLE">Serviceable</option>
                                <option value="UNSERVICEABLE">Unserviceable</option>
                                <option value="BER">BER</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-divider">Metrics & Documents</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Odometer (km)</label>
                            <input type="number" name="odometer_km" class="form-control form-control-modern" min="0" value="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Next PMS Date</label>
                            <input type="date" name="next_pms_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Official Receipt (OR) <span class="text-danger">*</span></label>
                            <input type="file" name="or_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required style="padding-top:7px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Cert. of Reg (CR) <span class="text-danger">*</span></label>
                            <input type="file" name="cr_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required style="padding-top:7px;">
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitVehicle" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT VEHICLE MODAL -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate">
                <h5><i class="fas fa-edit mr-2 text-primary"></i> Edit Vehicle</h5>
            </div>

            <form id="editVehicleForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body p-4">
                    <div id="editModalLiveBanner" class="live-checker-banner warning">
                        <i class="fas fa-exclamation-triangle font-size-16"></i>
                        <span id="editModalLiveBannerText">Warning: Duplicate record detected.</span>
                    </div>
                    <div id="editModalErrorBanner" class="live-checker-banner warning"></div>

                    <div class="modal-section-divider mt-0">Identity &amp; Classification</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Plate Number <span class="text-danger">*</span></label>
                            <input type="text" id="edit_plate_number" name="plate_number" class="form-control form-control-modern live-check-field-edit" data-field="plate_number" required autocomplete="off">
                            <span class="field-feedback-text" id="edit-feedback-plate_number"></span>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Make <span class="text-danger">*</span></label>
                            <input type="text" id="edit_make" name="make" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Model <span class="text-danger">*</span></label>
                            <input type="text" id="edit_model" name="model" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Vehicle Type <span class="text-danger">*</span></label>
                            <select id="edit_vehicle_type_id" name="vehicle_type_id" class="form-control form-control-modern" required>
                                <option value="">Select type...</option>
                                @foreach ($vehicleTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-divider">Specifications &amp; Identifiers</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Year Model</label>
                            <input type="number" id="edit_year_model" name="year_model" class="form-control form-control-modern" min="1980" max="{{ date('Y') + 1 }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Color</label>
                            <input type="text" id="edit_color" name="color" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Engine Number</label>
                            <input type="text" id="edit_engine_number" name="engine_number" class="form-control form-control-modern live-check-field-edit" data-field="engine_number" autocomplete="off">
                            <span class="field-feedback-text" id="edit-feedback-engine_number"></span>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Chassis Number</label>
                            <input type="text" id="edit_chassis_number" name="chassis_number" class="form-control form-control-modern live-check-field-edit" data-field="chassis_number" autocomplete="off">
                            <span class="field-feedback-text" id="edit-feedback-chassis_number"></span>
                        </div>
                    </div>

                    <div class="modal-section-divider">Deployment &amp; Status</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Unit <span class="text-danger">*</span></label>
                            <select id="edit_unit_id" name="unit_id" class="form-control form-control-modern" required>
                                <option value="">Select Unit...</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Station <span class="text-danger">*</span></label>
                            <select id="edit_station_id" name="station_id" class="form-control form-control-modern" required>
                                <option value="">Select Station...</option>
                                @foreach ($stations as $s)
                                    <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}">{{ $s->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Assigned Driver</label>
                            <select id="edit_assigned_driver_id" name="assigned_driver_id" class="form-control form-control-modern">
                                <option value="">Unassigned</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">
                                        {{ $driver->firstname }} {{ $driver->lastname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Status <span class="text-danger">*</span></label>
                            <select id="edit_status" name="status" class="form-control form-control-modern" required>
                                <option value="SERVICEABLE">Serviceable</option>
                                <option value="UNSERVICEABLE">Unserviceable</option>
                                <option value="BER">BER</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-divider">Metrics</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label">Odometer (km)</label>
                            <input type="number" id="edit_odometer_km" name="odometer_km" class="form-control form-control-modern" min="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Next PMS Date</label>
                            <input type="date" id="edit_next_pms_date" name="next_pms_date" class="form-control form-control-modern">
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fas fa-info-circle mr-1"></i> OR/CR documents aren't edited here — use the <strong>Docs</strong> button on the vehicle's row to upload a new year's registration.
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitEditVehicle" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- HISTORY / REGISTRATION MODAL -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex align-items-center justify-content-between">
                <h5 id="historyModalTitle">Document History</h5>
                <button type="button" class="btn btn-sm btn-outline-light" id="toggleAddRegistrationBtn">
                    <i class="fas fa-plus mr-1"></i> Add Registration
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="addRegistrationForm" class="history-card" style="display:none; background:#eff6ff; border-color:#bfdbfe;" enctype="multipart/form-data">
                    <input type="hidden" id="registration_vehicle_id" name="vehicle_id">
                    <div class="font-weight-bold text-primary mb-2"><i class="fas fa-calendar-plus mr-1"></i> New Year Registration</div>
                    <div class="row">
                        <div class="col-md-3 form-group mb-2">
                            <label class="field-label">Year <span class="text-danger">*</span></label>
                            <input type="number" name="registration_year" class="form-control form-control-modern" value="{{ date('Y') }}" min="1980" max="{{ date('Y') + 1 }}" required>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="field-label">Official Receipt (OR) <span class="text-danger">*</span></label>
                            <input type="file" name="or_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="field-label">Cert. of Reg (CR) <span class="text-danger">*</span></label>
                            <input type="file" name="cr_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="col-md-1 form-group mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block" id="btnSubmitRegistration"><i class="fas fa-upload"></i></button>
                        </div>
                    </div>
                    <div class="field-feedback-text error" id="registrationErrorText" style="display:none;"></div>
                </form>

                <div id="historyModalBody">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!-- QR CODE MODAL -->
<div class="modal fade" id="qrModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-premium">
            <div id="qrModalBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printSingleQrBtn"><i class="fas fa-print mr-1"></i> Print This QR</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Route templates: generated by Laravel's route() helper (so they correctly include
    // the app's actual base path — e.g. /vmis/public — instead of being hardcoded absolute
    // paths that only work when the app is hosted at the domain root). Swap ":id" for the
    // real vehicle id in JS wherever one of these is used.
    const routeTemplates = {
        editData:     "{{ route('vehicles.edit-data', ':id') }}",
        update:       "{{ route('vehicles.update', ':id') }}",
        history:      "{{ route('vehicles.history', ':id') }}",
        registrations:"{{ route('vehicles.registrations.store', ':id') }}",
        qrData:       "{{ route('vehicles.qr-data', ':id') }}",
    };
    function vehicleRoute(name, id) {
        return routeTemplates[name].replace(':id', id);
    }
    const qrPrintBaseUrl = "{{ route('vehicles.qr.print') }}";

    @if (session('success'))
        toastr.success(@json(session('success')));
    @endif

    // Animates the four summary cards to whatever the current filter set actually returns —
    // called from DataTables' ajax.dataSrc, so it fires after every search/filter/page change.
    function updateStatCards(stats) {
        if (!stats) return;
        $('.stat-card-modern.total .stat-num-value').text(stats.total);
        $('.stat-card-modern.serviceable .stat-num-value').text(stats.serviceable);
        $('.stat-card-modern.unserviceable .stat-num-value').text(stats.unserviceable);
        $('.stat-card-modern.ber .stat-num-value').text(stats.ber);
    }

    // Re-renders the "Vehicles by Type" chips with live counts and keeps the active
    // chip in sync with whatever's currently selected in the Type filter dropdown.
    function updateTypeBreakdown(types) {
        if (!types) return;
        const currentFilter = $('#filterVehicleType').val();
        const $container = $('#typeBreakdownChips');
        $container.empty();

        if (!types.length) {
            $container.append('<span class="type-breakdown-empty">No vehicle types configured yet.</span>');
            return;
        }

        types.forEach(function (t) {
            const isActive = currentFilter !== '' && String(currentFilter) === String(t.id);
            const $chip = $('<div class="type-chip"></div>')
                .toggleClass('active', isActive)
                .toggleClass('empty', t.count === 0)
                .attr('data-type-id', t.id)
                .append($('<span></span>').text(t.name))
                .append($('<span class="count-badge"></span>').text(t.count));
            $container.append($chip);
        });
    }

    // Clicking a chip filters the table by that type; clicking the already-active
    // chip again clears the filter — a toggle, not a one-way selection.
    $(document).on('click', '.type-chip', function () {
        const id = $(this).data('type-id');
        const current = $('#filterVehicleType').val();
        $('#filterVehicleType').val(String(current) === String(id) ? '' : id);
        table.draw();
    });

    const table = $('#vehiclesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('vehicles.index') }}",
            data: function (d) {
                @if ($hasBroadVisibility)
                    d.unit_id = $('#filterUnit').val();
                @endif
                d.station_id = $('#filterStation').val();
                d.status = $('#filterStatus').val();
                d.vehicle_type_id = $('#filterVehicleType').val();
            },
            dataSrc: function (json) {
                updateStatCards(json.stats);
                updateTypeBreakdown(json.type_breakdown);
                return json.data;
            }
        },
        columns: [
            { data: 'select_html', name: 'select', orderable: false, searchable: false },
            { data: 'plate_html', name: 'plate_number' },
            { data: 'spec_html', name: 'make' },
            { data: 'type_html', name: 'vehicle_type_id', orderable: false },
            { data: 'driver_html', name: 'assigned_driver_id', orderable: false },
            { data: 'docs_html', name: 'docs', orderable: false, searchable: false },
            { data: 'pms_html', name: 'next_pms_date' },
            { data: 'status_html', name: 'status' },
            { data: 'actions_html', name: 'actions', orderable: false, searchable: false }
        ],
        dom: '<"row"<"col-sm-12"tr>><"row pt-3"<"col-sm-5"i><"col-sm-7"p>>',
    });

    // ---------------- QR Code module ----------------
    // Selected IDs persist across searches/filters/pages — DataTables rebuilds every row
    // on each draw, so checkbox "checked" state alone would reset; we track it separately.
    const selectedVehicleIds = new Set();

    function updateSelectedUI() {
        const count = selectedVehicleIds.size;
        $('#selectedCountBadge').text(count).toggle(count > 0);
        $('#printSelectedQrBtn').prop('disabled', count === 0);
        const totalRows = $('.row-select-checkbox').length;
        const checkedRows = $('.row-select-checkbox:checked').length;
        $('#selectAllRows').prop('checked', totalRows > 0 && totalRows === checkedRows);
    }

    $('#vehiclesTable').on('change', '.row-select-checkbox', function () {
        const id = String($(this).data('id'));
        if (this.checked) { selectedVehicleIds.add(id); } else { selectedVehicleIds.delete(id); }
        updateSelectedUI();
    });

    $('#selectAllRows').on('change', function () {
        const checked = this.checked;
        $('.row-select-checkbox').each(function () {
            $(this).prop('checked', checked);
            const id = String($(this).data('id'));
            if (checked) { selectedVehicleIds.add(id); } else { selectedVehicleIds.delete(id); }
        });
        updateSelectedUI();
    });

    // Re-apply checked state to whatever rows DataTables just rebuilt.
    table.on('draw', function () {
        $('.row-select-checkbox').each(function () {
            $(this).prop('checked', selectedVehicleIds.has(String($(this).data('id'))));
        });
        updateSelectedUI();
    });

    $('#printSelectedQrBtn').on('click', function () {
        if (selectedVehicleIds.size === 0) return;
        const ids = Array.from(selectedVehicleIds).join(',');
        window.open(qrPrintBaseUrl + '?ids=' + encodeURIComponent(ids), '_blank');
    });

    let currentQrVehicleId = null;

    $('#vehiclesTable').on('click', '.btn-view-qr', function () {
        const id = $(this).data('id');
        currentQrVehicleId = id;
        $('#qrModal').modal('show');
        $('#qrModalBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');

        $.get(vehicleRoute('qrData', id), function (data) {
            const printedLine = data.last_printed_by
                ? ('Last printed by <b>' + data.last_printed_by + '</b> on ' + data.last_printed_at)
                : 'Not printed yet';

            $('#qrModalBody').html(
                '<div class="qr-modal-hero text-center">' +
                    '<span class="qr-plate-badge">' + data.plate_number + '</span>' +
                    '<div class="qr-veh-name">' + data.make_model + '</div>' +
                    '<div class="qr-veh-sub">' + data.type_name + '</div>' +
                    '<span class="qr-status-chip ' + data.status + '"><i class="fas fa-circle" style="font-size:5px;"></i> ' + data.status.toLowerCase() + '</span>' +
                    '<div class="qr-code-frame"><img src="' + data.qr_image_url + '" alt="QR code"></div>' +
                '</div>' +
                '<div class="qr-provenance">' +
                    '<div class="qr-provenance-row"><i class="fas fa-user-edit"></i> Generated by <b>' + data.generated_by + '</b> on ' + data.generated_at + '</div>' +
                    '<div class="qr-provenance-row"><i class="fas fa-print"></i> ' + printedLine + '</div>' +
                '</div>'
            );
        }).fail(function () {
            $('#qrModalBody').html('<div class="alert alert-danger m-4">Could not load this QR code.</div>');
        });
    });

    $('#printSingleQrBtn').on('click', function () {
        if (!currentQrVehicleId) return;
        window.open(qrPrintBaseUrl + '?ids=' + encodeURIComponent(currentQrVehicleId), '_blank');
    });

    $('#customSearchBox').on('keyup input', function() { table.search($(this).val()).draw(); });$('#filterUnit, #filterStation, #filterStatus, #filterVehicleType').change(function() { table.draw(); });
    
    $('#filterUnit, #formUnitId, #edit_unit_id').change(function() {
        let unitId = $(this).val();
        let triggerId = $(this).attr('id');
        let targetStation = { filterUnit: '#filterStation', formUnitId: '#formStationId', edit_unit_id: '#edit_station_id' }[triggerId];

        // No unit chosen = no stations shown, everywhere (toolbar filter included) — a
        // station can't be picked without a unit context, so listing every station before
        // a unit is chosen is just noise rather than a real "show everything" filter state.
        $(targetStation + ' option').each(function() {
            if($(this).val() === "") { $(this).show(); return; }
            let matchesUnit = ($(this).data('unit') == unitId);
            $(this).toggle(unitId !== "" && matchesUnit);
        });
        if (triggerId !== 'edit_unit_id') { $(targetStation).val(''); } // don't clear the station when populating the edit modal
    });

    // Apply that same hidden-by-default state to the toolbar filter on first load —
    // otherwise every station shows until the user actually touches the Unit dropdown once.
    $('#filterUnit').trigger('change');

    $('#resetFiltersBtn').click(function() {
        $('#customSearchBox, #filterStation, #filterStatus, #filterVehicleType').val('');
        @if ($hasBroadVisibility)$('#filterUnit').val('');
        @endif
        $('#filterStation option').show();
        table.search('').draw();
    });

    let currentHistoryVehicleId = null;

    function loadHistory(id) {
        currentHistoryVehicleId = id;
        $('#registration_vehicle_id').val(id);
        $('#addRegistrationForm').hide();
        $('#addRegistrationForm')[0].reset();
        $('#registrationErrorText').hide().text('');
        $('#historyModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');

        $.get(vehicleRoute('history', id), function(data) {
            $('#historyModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} - Registration History`);

            let html = '';
            if(data.registrations.length === 0) {
                html = '<div class="alert alert-info">No document history found yet. Use "Add Registration" above to upload the first OR/CR.</div>';
            } else {
                data.registrations.forEach(function(reg) {
                    html += `
                    <div class="history-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="history-year">Year: ${reg.year}</span>
                            <span class="badge badge-light border"><i class="fas fa-user-edit"></i> Encoded by: ${reg.uploader}</span>
                        </div>
                        <p class="text-muted small mb-2"><i class="fas fa-clock"></i> Uploaded: ${reg.date}</p>
                        <div class="d-flex gap-2">
                            <a href="${reg.or_url}" target="_blank" class="btn btn-sm btn-outline-primary mr-2"><i class="fas fa-file-pdf"></i> View OR</a>
                            <a href="${reg.cr_url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> View CR</a>
                        </div>
                    </div>`;
                });
            }
            $('#historyModalBody').html(html);
        }).fail(function() {
            $('#historyModalBody').html('<div class="alert alert-danger">Error loading document history. You may not have permission.</div>');
        });
    }

    $('#vehiclesTable').on('click', '.btn-history', function() {
        let id = $(this).data('id');
        $('#historyModal').modal('show');
        loadHistory(id);
    });

    $('#toggleAddRegistrationBtn').click(function() {
        $('#addRegistrationForm').slideToggle(150);
    });

    $('#addRegistrationForm').on('submit', function(e) {
        e.preventDefault();
        const vehicleId = $('#registration_vehicle_id').val();
        const formData = new FormData(this);
        const $btn = $('#btnSubmitRegistration');

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $('#registrationErrorText').hide().text('');

        $.ajax({
            url: vehicleRoute('registrations', vehicleId),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(res) {
            toastr.success(res.message);
            $('#addRegistrationForm')[0].reset();
            $('#addRegistrationForm').slideUp(150);
            loadHistory(vehicleId);
            table.ajax.reload(null, false);
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
            $('#registrationErrorText').text(msg).show();
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-upload"></i>');
        });
    });

    // Confirmation before saving a new vehicle record — the file inputs mean this must stay a
    // real form submit (not AJAX), so we confirm then call the native form.submit(), which does
    // NOT re-trigger this same 'submit' event handler (avoids an infinite confirm loop).
    $('#registerVehicleForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Save this vehicle record?',
            text: 'Please confirm the details are correct before saving.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Record',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Review Again',
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Delete confirmation via SweetAlert2 (already loaded in this app) instead of a plain confirm() dialog.
    // Delegated on the table wrapper since DataTables re-renders these rows/forms on every draw.
    $('#vehiclesTable').on('submit', '.form-delete-vehicle', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Delete this vehicle?',
            text: 'This will permanently remove the vehicle record and its registration history. This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // ---------------- Edit Vehicle ----------------
    $('#vehiclesTable').on('click', '.btn-edit-vehicle', function() {
        const id = $(this).data('id');

        $.get(vehicleRoute('editData', id), function(data) {
            $('#edit_id').val(data.id);
            $('#edit_plate_number').val(data.plate_number);
            $('#edit_engine_number').val(data.engine_number);
            $('#edit_chassis_number').val(data.chassis_number);
            $('#edit_make').val(data.make);
            $('#edit_model').val(data.model);
            $('#edit_vehicle_type_id').val(data.vehicle_type_id);
            $('#edit_year_model').val(data.year_model);
            $('#edit_color').val(data.color);
            $('#edit_odometer_km').val(data.odometer_km);
            $('#edit_next_pms_date').val(data.next_pms_date);
            $('#edit_status').val(data.status);
            $('#edit_assigned_driver_id').val(data.assigned_driver_id);

            $('#edit_unit_id').val(data.unit_id).trigger('change');
            $('#edit_station_id').val(data.station_id);

            $('.live-check-field-edit').removeClass('is-invalid is-valid');
            $('#editVehicleModal .field-feedback-text').html('');
            $('#editModalLiveBanner, #editModalErrorBanner').hide();
            editDuplicateState.plate_number = false; editDuplicateState.engine_number = false; editDuplicateState.chassis_number = false;

            $('#editVehicleModal').modal('show');
        }).fail(function() {
            toastr.error('Could not load this vehicle\'s details.');
        });
    });

    $('#editVehicleForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = $(form).serialize();

        Swal.fire({
            title: 'Save changes to this vehicle?',
            text: 'This will update the vehicle\'s record immediately.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Keep Editing',
        }).then(function(result) {
            if (! result.isConfirmed) return;

            const id = $('#edit_id').val();
            const $btn = $('#btnSubmitEditVehicle');
            $('#editModalErrorBanner').hide().text('');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: vehicleRoute('update', id),
                type: 'PUT',
                data: formData,
            }).done(function(res) {
                toastr.success(res.message);
                $('#editVehicleModal').modal('hide');
                table.ajax.reload(null, false);
            }).fail(function(xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                $('#editModalErrorBanner').addClass('warning').text(msg).show();
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes');
            });
        });
    });

    const editDuplicateState = { plate_number: false, engine_number: false, chassis_number: false };
    let editTypingTimer;

    $('.live-check-field-edit').on('keyup input', function() {
        const inputElem = $(this);
        const fieldName = inputElem.data('field');
        const value = inputElem.val().trim();
        const excludeId = $('#edit_id').val();

        clearTimeout(editTypingTimer);
        if (value.length < 2) {
            editDuplicateState[fieldName] = false;
            inputElem.removeClass('is-invalid is-valid');
            $('#edit-feedback-' + fieldName).html('').removeClass('error success');
            updateEditModalGlobalState();
            return;
        }

        editTypingTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('vehicles.check-availability') }}",
                type: "POST",
                data: { field: fieldName, value: value, exclude_id: excludeId },
                success: function(response) {
                    const feedbackElem = $('#edit-feedback-' + fieldName);
                    if (response.exists) {
                        editDuplicateState[fieldName] = true;
                        inputElem.addClass('is-invalid').removeClass('is-valid');
                        feedbackElem.removeClass('success').addClass('error').html('<i class="fas fa-times-circle"></i> Already used by ' + response.plate_number + ' (' + response.make_model + ')');
                    } else {
                        editDuplicateState[fieldName] = false;
                        inputElem.addClass('is-valid').removeClass('is-invalid');
                        feedbackElem.removeClass('error').addClass('success').html('<i class="fas fa-check-circle"></i> Available');
                    }
                    updateEditModalGlobalState();
                }
            });
        }, 400);
    });

    function updateEditModalGlobalState() {
        const hasDuplicate = editDuplicateState.plate_number || editDuplicateState.engine_number || editDuplicateState.chassis_number;
        $('#editModalLiveBanner').toggle(hasDuplicate);
        $('#btnSubmitEditVehicle').prop('disabled', hasDuplicate).css('opacity', hasDuplicate ? '0.65' : '1');
    }

    $('#registerVehicleModal').on('show.bs.modal', function () {
        $('#modalLiveBanner').hide();
        $('#btnSubmitVehicle').prop('disabled', false).css('opacity', '1');
        duplicateState.plate_number = false; duplicateState.engine_number = false; duplicateState.chassis_number = false;
        $('.live-check-field').removeClass('is-invalid is-valid');$('.field-feedback-text').html('').removeClass('error success');

        // Re-apply the unit→station filter every time the modal opens: hides all stations for
        // multi-unit admins (nothing chosen yet), or auto-filters to the one pre-selected unit's
        // stations for a Unit Administrator (who only ever has a single option anyway).
        $('#formUnitId').trigger('change');
    });

    let typingTimer;
    const duplicateState = { plate_number: false, engine_number: false, chassis_number: false };

    $('.live-check-field').on('keyup input', function() {
        const inputElem = $(this);
        const fieldName = inputElem.data('field');
        const value = inputElem.val().trim();

        clearTimeout(typingTimer);
        if (value.length < 2) {
            duplicateState[fieldName] = false;
            inputElem.removeClass('is-invalid is-valid');
            $('#feedback-' + fieldName).html('').removeClass('error success');
            updateModalGlobalState();
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

    function updateModalGlobalState() {
        const hasDuplicate = duplicateState.plate_number || duplicateState.engine_number || duplicateState.chassis_number;
        if (hasDuplicate) {
            $('#modalLiveBanner').show();
            $('#btnSubmitVehicle').prop('disabled', true).css('opacity', '0.65');
        } else {
            $('#modalLiveBanner').hide();
            $('#btnSubmitVehicle').prop('disabled', false).css('opacity', '1');
        }
    }
});
</script>
@endsection