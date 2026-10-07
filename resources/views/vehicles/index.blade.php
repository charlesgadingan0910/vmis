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

  /* ---------- PMS (Preventive Maintenance Schedule) alert badges ---------- */
  .pms-date { font-weight: 600; color: #334155; font-size: 13.5px; }
  .pms-date-overdue { color: #dc2626; font-weight: 800; }
  .badge-pms { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; }
  .badge-pms i { font-size: 10px; }
  .pms-badge-soon { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
  .pms-badge-overdue {
    background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;
    animation: pms-alert-pulse 1.8s ease-in-out infinite;
  }
  @keyframes pms-alert-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.32); }
    50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0); }
  }
  /* An overdue PMS should be obvious the moment the eye reaches the row, not
     only once it lands on the PMS cell — so the whole row gets a faint red
     wash plus an accent bar on the PMS column itself. */
  .fleet-table tbody tr.row-pms-overdue { background: #fff6f6; }
  .fleet-table tbody tr.row-pms-overdue:hover { background: #fee2e2; }
  .fleet-table tbody tr.row-pms-overdue td:nth-child(7) { border-left: 3px solid #dc2626; padding-left: 21px; }
  @media (max-width: 767.98px) {
    .fleet-table tbody tr.row-pms-overdue { background: #fff6f6 !important; border-color: #fecaca !important; border-width: 1.5px !important; }
    .fleet-table tbody tr.row-pms-overdue td:nth-child(7) { border-left: none; padding-left: 0; }
  }

  /* Same treatment for an overdue OR/CR/Insurance registration, but accenting
     the Registration column (6th) instead of PMS (7th) — a vehicle can be
     flagged by either, or both, independently. */
  .fleet-table tbody tr.row-reg-overdue { background: #fff6f6; }
  .fleet-table tbody tr.row-reg-overdue:hover { background: #fee2e2; }
  .fleet-table tbody tr.row-reg-overdue td:nth-child(6) { border-left: 3px solid #dc2626; padding-left: 21px; }
  @media (max-width: 767.98px) {
    .fleet-table tbody tr.row-reg-overdue { background: #fff6f6 !important; border-color: #fecaca !important; border-width: 1.5px !important; }
    .fleet-table tbody tr.row-reg-overdue td:nth-child(6) { border-left: none; padding-left: 0; }
  }

  /* ---------- Predictive PMS priority tag (under the overdue/soon badge) ---------- */
  .pms-priority-tag { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; margin-top:4px; padding:2px 8px; border-radius:20px; cursor:help; }
  .pms-priority-tag i { font-size: 9px; }
  .pms-priority-critical { background:#450a0a; color:#fecaca; }
  .pms-priority-high { background:#7c2d12; color:#fed7aa; }
  .pms-priority-medium { background:#78350f; color:#fde68a; }
  .pms-priority-low { background:#1e293b; color:#cbd5e1; }

  /* ---------- Priority Attention panel ---------- */
  .priority-panel { background:#fff; border-radius:14px; padding:18px 20px; border:1px solid #eef1f6; box-shadow:0 1px 3px rgba(15,23,42,0.04); margin-bottom:22px; }
  .priority-panel-header { font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.03em; margin-bottom:6px; display:flex; align-items:center; gap:8px; }
  .priority-panel-header i { color:#dc2626; }
  .priority-panel-sub { font-size:12px; color:#94a3b8; margin-bottom:14px; }
  .priority-row { display:flex; align-items:center; gap:14px; padding:10px; border-top:1px solid #f8fafc; cursor:pointer; border-radius:8px; margin:0 -10px; }
  .priority-row:first-of-type { border-top:none; }
  .priority-row:hover { background:#f8fafc; }
  .priority-row-plate { font-family:'Courier New',monospace; font-weight:800; font-size:12.5px; background:#1e293b; color:#fff; padding:4px 10px; border-radius:6px; flex-shrink:0; }
  .priority-row-info { flex:1; min-width:0; }
  .priority-row-name { font-weight:700; font-size:13px; color:#0f172a; }
  .priority-row-reason { font-size:11.5px; color:#64748b; margin-top:2px; }

  /* Unserviceable 90+ days panel — reuses .priority-panel/.priority-row's
     structure and click-to-find behavior, just with a red/warning theme
     instead of the Priority Attention panel's default styling. */
  .priority-panel.unserviceable-alert-panel { border-color:#fecaca; background:#fffbfb; }
  .unserviceable-alert-panel .priority-panel-header i { color:#dc2626; }

  /* Registration Due panel — same priority-panel/priority-row structure,
     amber theme since it mixes already-overdue and due-soon vehicles rather
     than being purely critical like the unserviceable panel above. */
  .priority-panel.registration-due-panel { border-color:#fde68a; background:#fffdf5; }
  .registration-due-panel .priority-panel-header i { color:#d97706; }

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

  /* Deployment & Status uses a flex row instead of fixed col-md-3s so that when
     conditional fields (Source added, BER Sub-Status / Disposal Date shown or
     hidden) change how many fields are visible, the remaining ones on the last
     line grow to fill the row instead of leaving a sparse, dangling row of
     mostly-empty columns. */
  .form-flex-row { align-items: flex-start; }
  .form-flex-row > .form-flex-item { flex: 1 1 220px; padding-right: 15px; padding-left: 15px; }

  .history-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 12px; }
  .history-year { font-size: 18px; font-weight: 800; color: #0f172a; }

  /* ---------- Vehicle Records modal — tab bar across the four record types ---------- */
  .vr-tab-nav { border-bottom: 2px solid #eef1f6; gap: 4px; flex-wrap: wrap; }
  .vr-tab-nav .nav-link { font-size: 13px; font-weight: 700; color: #64748b; padding: 9px 14px; border-radius: 10px 10px 0 0; border: none; margin-bottom: -2px; }
  .vr-tab-nav .nav-link:hover { color: #334155; background: #f8fafc; }
  .vr-tab-nav .nav-link.active { color: #1d4ed8; background: #eff6ff; border-bottom: 2px solid #1d4ed8; }
  .vr-tab-pane .history-card .round-trip-chip{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;padding:2px 7px;border-radius:5px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;margin-left:6px;white-space:nowrap;}

  /* ---------- Registration document icons (Register Vehicle / Add Registration forms) ---------- */
  .doc-field-icon { font-size: 12px; margin-right: 4px; }
  .doc-field-icon-or { color: #3b82f6; }
  .doc-field-icon-cr { color: #8b5cf6; }
  .doc-field-icon-ins { color: #0d9488; }
  .doc-field-icon-exp { color: #d97706; }

  /* ---------- Docs / Add Docs action button (Vehicle Inventory Registration column) ---------- */
  .docs-pill-btn {
    display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;
    padding: 6px 13px; border-radius: 20px; border: 1.5px solid; transition: all .15s ease; line-height: 1.3;
  }
  .docs-pill-btn i { font-size: 11px; }
  .docs-pill-btn.has-docs { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
  .docs-pill-btn.has-docs:hover { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
  .docs-pill-btn.no-docs { background: #f8fafc; border-color: #e2e8f0; border-style: dashed; color: #64748b; }
  .docs-pill-btn.no-docs:hover { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }

  /* ---------- Registration History modal: premium per-year document cards ---------- */
  .reg-modal-subtitle { font-size: 12px; color: #94a3b8; font-weight: 600; margin-top: 2px; }
  .reg-year-card {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px;
    margin-bottom: 14px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); transition: box-shadow .15s ease, border-color .15s ease;
  }
  .reg-year-card:hover { box-shadow: 0 4px 16px rgba(15,23,42,0.07); }
  .reg-year-card.is-latest { border-color: #bfdbfe; background: linear-gradient(180deg, #f5f9ff 0%, #ffffff 60px); }
  .reg-year-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
  .reg-year-badge-wrap { display: flex; align-items: center; gap: 9px; }
  .reg-year-badge {
    font-size: 17px; font-weight: 800; color: #0f172a; background: #f1f5f9; border-radius: 8px;
    padding: 3px 12px; letter-spacing: .02em;
  }
  .reg-year-card.is-latest .reg-year-badge { background: #dbeafe; color: #1d4ed8; }
  .reg-current-chip {
    font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #16a34a;
    background: #eaf6ef; border: 1px solid #bbf0cf; padding: 3px 9px; border-radius: 20px;
  }
  .reg-uploader-chip { font-size: 11.5px; color: #64748b; font-weight: 600; }
  .reg-uploader-chip i { color: #94a3b8; margin-right: 3px; }
  .reg-uploaded-date { font-size: 11.5px; color: #94a3b8; margin: 3px 0 14px; }
  .reg-uploaded-date i { margin-right: 4px; }

  .reg-doc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  @media (max-width: 575px) { .reg-doc-grid { grid-template-columns: 1fr; } }

  .reg-doc-chip {
    display: flex; align-items: center; gap: 11px; background: #f8fafc; border: 1.5px solid #e2e8f0;
    border-radius: 11px; padding: 11px 12px; text-decoration: none; transition: all .15s ease;
  }
  a.reg-doc-chip:hover { background: #fff; border-color: #93c5fd; box-shadow: 0 3px 10px rgba(59,130,246,0.12); transform: translateY(-1px); text-decoration: none; }
  .reg-doc-icon {
    width: 36px; height: 36px; min-width: 36px; border-radius: 9px; display: flex; align-items: center;
    justify-content: center; font-size: 14px;
  }
  .reg-doc-icon.doc-or { background: rgba(59,130,246,0.12); color: #3b82f6; }
  .reg-doc-icon.doc-cr { background: rgba(139,92,246,0.12); color: #8b5cf6; }
  .reg-doc-icon.doc-ins { background: rgba(13,148,136,0.12); color: #0d9488; }
  .reg-doc-meta { min-width: 0; }
  .reg-doc-label { font-size: 12.5px; font-weight: 700; color: #1e293b; }
  .reg-doc-status { font-size: 11px; color: #3b82f6; font-weight: 600; margin-top: 1px; }
  .reg-doc-status i { font-size: 9px; margin-left: 2px; }
  .reg-doc-chip.reg-doc-missing { opacity: .7; border-style: dashed; cursor: default; }
  .reg-doc-chip.reg-doc-missing .reg-doc-icon { background: #f1f5f9; color: #cbd5e1; }
  .reg-doc-chip.reg-doc-missing .reg-doc-status { color: #94a3b8; }

  .reg-summary-bar {
    display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px solid #eef1f6;
    border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; font-size: 12.5px; color: #475569; font-weight: 600;
  }
  .reg-summary-bar i { color: #3b82f6; }

  /* ---------- Add Registration panel ---------- */
  .add-registration-panel { background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 12px; padding: 16px 18px; margin-bottom: 18px; }
  .live-checker-banner { display: none; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; font-weight: 600; align-items: center; gap: 10px; }
  .live-checker-banner.warning { display: flex; background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; }
  .field-feedback-text { font-size: 11.5px; font-weight: 600; margin-top: 4px; display: block; }
  .field-feedback-text.error { color: #dc2626; }
  .field-feedback-text.success { color: #16a34a; }

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

        @if($priorityVehicles->isNotEmpty())
        <div class="priority-panel">
            <div class="priority-panel-header"><i class="fas fa-bolt"></i> Priority Attention — Preventive Maintenance (PMS)</div>
            <div class="priority-panel-sub">These vehicles are most likely to need their next preventive maintenance service soon — not just the ones already overdue, but also those driven heavily or with a history of late services. Click a vehicle to find it in the table below.</div>
            @foreach($priorityVehicles as $p)
            <div class="priority-row" data-plate="{{ strtoupper($p['vehicle']->plate_number) }}" title="Click to find this vehicle in the table below">
                <span class="priority-row-plate">{{ strtoupper($p['vehicle']->plate_number) }}</span>
                <div class="priority-row-info">
                    <div class="priority-row-name">{{ $p['vehicle']->make }} {{ $p['vehicle']->model }}</div>
                    <div class="priority-row-reason">{{ ucfirst($p['reason']) }}</div>
                </div>
                <span class="pms-priority-tag pms-priority-{{ $p['priority'] }}"><i class="fas fa-bolt"></i> {{ ucfirst($p['priority']) }}</span>
            </div>
            @endforeach
        </div>
        @endif

        @if($unserviceableAlerts->isNotEmpty())
        <div class="priority-panel unserviceable-alert-panel">
            <div class="priority-panel-header"><i class="fas fa-exclamation-triangle"></i> Unserviceable 90+ Days — Needs Action</div>
            <div class="priority-panel-sub">These vehicles have been Unserviceable for at least 3 months. Repair, reclassify as BER, or otherwise resolve their status. Click one to find it below.</div>
            @foreach($unserviceableAlerts as $v)
            <div class="priority-row" data-plate="{{ strtoupper($v->plate_number) }}" title="Click to find this vehicle in the table below">
                <span class="priority-row-plate">{{ strtoupper($v->plate_number) }}</span>
                <div class="priority-row-info">
                    <div class="priority-row-name">{{ $v->make }} {{ $v->model }}</div>
                    <div class="priority-row-reason">Unserviceable since {{ $v->unserviceable_since->format('M d, Y') }}</div>
                </div>
                <span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> {{ $v->daysUnserviceable() }}d</span>
            </div>
            @endforeach
        </div>
        @endif

        @if($registrationDueAlerts->isNotEmpty())
        <div class="priority-panel registration-due-panel">
            <div class="priority-panel-header"><i class="fas fa-calendar-times"></i> Registration Due</div>
            <div class="priority-panel-sub">OR/CR/Insurance already expired or expiring within {{ \App\Models\Vehicle::REGISTRATION_DUE_SOON_DAYS }} days. Click one to find it below, or use its <strong>Docs</strong> button to upload the new year's registration.</div>
            @foreach($registrationDueAlerts as $v)
            @php $daysLeft = $v->registrationDaysRemaining(); @endphp
            <div class="priority-row" data-plate="{{ strtoupper($v->plate_number) }}" title="Click to find this vehicle in the table below">
                <span class="priority-row-plate">{{ strtoupper($v->plate_number) }}</span>
                <div class="priority-row-info">
                    <div class="priority-row-name">{{ $v->make }} {{ $v->model }}</div>
                    <div class="priority-row-reason">Valid until {{ $v->latestRegistration->expiry_date->format('M d, Y') }}</div>
                </div>
                @if($daysLeft < 0)
                <span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> {{ abs($daysLeft) }}d overdue</span>
                @else
                <span class="badge-pms pms-badge-soon"><i class="fas fa-clock"></i> Due in {{ $daysLeft }}d</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

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

                    <select id="filterSource" class="form-control custom-filter-select" style="width:130px;">
                        <option value="">All Sources</option>
                        @foreach (\App\Models\Vehicle::SOURCES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
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
                            <input type="text" id="make" name="make" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Model <span class="text-danger">*</span></label>
                            <input type="text" id="model" name="model" class="form-control form-control-modern" required>
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
                            <input type="number" id="year_model" name="year_model" class="form-control form-control-modern" min="1980" max="{{ date('Y') + 1 }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label">Color</label>
                            <input type="text" id="color" name="color" class="form-control form-control-modern">
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
                    <div class="row form-flex-row">
                        <div class="form-group form-flex-item">
                            <label class="field-label">Assigned Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="formUnitId" class="form-control form-control-modern" required>
                                <option value="">Select Unit...</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}" {{ $units->count() === 1 ? 'selected' : '' }}>{{ $u->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item">
                            <label class="field-label">Assigned Station <span class="text-danger">*</span></label>
                            <select name="station_id" id="formStationId" class="form-control form-control-modern" required>
                                <option value="">Select Station...</option>
                                @foreach ($stations as $s)
                                    <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}">{{ $s->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item">
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
                        <div class="form-group form-flex-item">
                            <label class="field-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control form-control-modern" required>
                                <option value="SERVICEABLE">Serviceable</option>
                                <option value="UNSERVICEABLE">Unserviceable</option>
                                <option value="BER">BER</option>
                            </select>
                        </div>
                        <div class="form-group form-flex-item">
                            <label class="field-label">Source <span class="text-danger">*</span></label>
                            <select name="source" class="form-control form-control-modern" required>
                                @foreach (\App\Models\Vehicle::SOURCES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item ber-field-wrapper d-none">
                            <label class="field-label">BER Sub-Status <span class="text-danger">*</span></label>
                            <select name="ber_sub_status" class="form-control form-control-modern ber-sub-status-select">
                                <option value="">Select sub-status...</option>
                                @foreach (\App\Models\Vehicle::BER_SUB_STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item disposal-date-wrapper d-none">
                            <label class="field-label">Disposal Date <span class="text-danger">*</span></label>
                            <input type="date" name="disposal_date" class="form-control form-control-modern disposal-date-input">
                            <small class="text-muted">Disposed vehicles are excluded from the Total Vehicles count.</small>
                        </div>
                    </div>

                    <div class="modal-section-divider">Metrics</div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Odometer (km)</label>
                            <input type="number" name="odometer_km" class="form-control form-control-modern" min="0" value="0">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Next PMS Date</label>
                            <input type="date" name="next_pms_date" class="form-control form-control-modern">
                        </div>
                    </div>

                    <div class="modal-section-divider">Registration Documents</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="field-label"><i class="fas fa-calendar-check doc-field-icon doc-field-icon-exp"></i> Valid Until <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control form-control-modern" value="{{ now()->addYear()->format('Y-m-d') }}" required>
                            <small class="text-muted" style="font-size:10.5px;">When this OR/CR/Insurance expires</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label"><i class="fas fa-receipt doc-field-icon doc-field-icon-or"></i> Official Receipt (OR) <span class="text-danger">*</span></label>
                            <input type="file" id="or_file" name="or_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required style="padding-top:7px;">
                            <span class="field-feedback-text" id="orScanStatus" style="display:none;"></span>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label"><i class="fas fa-id-card doc-field-icon doc-field-icon-cr"></i> Cert. of Reg (CR) <span class="text-danger">*</span></label>
                            <input type="file" name="cr_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required style="padding-top:7px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="field-label"><i class="fas fa-shield-alt doc-field-icon doc-field-icon-ins"></i> Insurance <span class="text-danger">*</span></label>
                            <input type="file" name="insurance_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required style="padding-top:7px;">
                        </div>
                    </div>
                    @if($aiDocumentScanningEnabled ?? false)
                    <div class="alert alert-light border small text-muted mb-0 mt-1">
                        <i class="fas fa-wand-magic-sparkles mr-1 text-primary"></i> When the OR is a clear, readable image or PDF, plate/make/model/year/color/engine/chassis fields above are auto-filled from it — always double-check before saving.
                    </div>
                    @endif

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
                    <div class="row form-flex-row">
                        <div class="form-group form-flex-item">
                            <label class="field-label">Assigned Unit <span class="text-danger">*</span></label>
                            <select id="edit_unit_id" name="unit_id" class="form-control form-control-modern" required>
                                <option value="">Select Unit...</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item">
                            <label class="field-label">Assigned Station <span class="text-danger">*</span></label>
                            <select id="edit_station_id" name="station_id" class="form-control form-control-modern" required>
                                <option value="">Select Station...</option>
                                @foreach ($stations as $s)
                                    <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}">{{ $s->station_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item">
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
                        <div class="form-group form-flex-item">
                            <label class="field-label">Status <span class="text-danger">*</span></label>
                            <select id="edit_status" name="status" class="form-control form-control-modern" required>
                                <option value="SERVICEABLE">Serviceable</option>
                                <option value="UNSERVICEABLE">Unserviceable</option>
                                <option value="BER">BER</option>
                            </select>
                            <small class="text-muted d-none" id="edit_unserviceable_note"></small>
                        </div>
                        <div class="form-group form-flex-item">
                            <label class="field-label">Source <span class="text-danger">*</span></label>
                            <select id="edit_source" name="source" class="form-control form-control-modern" required>
                                @foreach (\App\Models\Vehicle::SOURCES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item ber-field-wrapper d-none">
                            <label class="field-label">BER Sub-Status <span class="text-danger">*</span></label>
                            <select id="edit_ber_sub_status" name="ber_sub_status" class="form-control form-control-modern ber-sub-status-select">
                                <option value="">Select sub-status...</option>
                                @foreach (\App\Models\Vehicle::BER_SUB_STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-flex-item disposal-date-wrapper d-none">
                            <label class="field-label">Disposal Date <span class="text-danger">*</span></label>
                            <input type="date" id="edit_disposal_date" name="disposal_date" class="form-control form-control-modern disposal-date-input">
                            <small class="text-muted">Disposed vehicles are excluded from the Total Vehicles count.</small>
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
                        <i class="fas fa-info-circle mr-1"></i> OR/CR/Insurance documents aren't edited here — use the <strong>Docs</strong> button on the vehicle's row to upload a new year's registration.
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
                <div>
                    <h5 id="historyModalTitle"><i class="fas fa-folder-open mr-2"></i> Document History</h5>
                    <div class="reg-modal-subtitle" id="historyModalSubtitle"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" id="toggleAddRegistrationBtn">
                    <i class="fas fa-plus mr-1"></i> Add Registration
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="addRegistrationForm" class="add-registration-panel" style="display:none;" enctype="multipart/form-data">
                    <input type="hidden" id="registration_vehicle_id" name="vehicle_id">
                    <div class="font-weight-bold text-primary mb-3"><i class="fas fa-calendar-plus mr-1"></i> New Year Registration</div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-2">
                            <label class="field-label">Year <span class="text-danger">*</span></label>
                            <input type="number" name="registration_year" class="form-control form-control-modern" value="{{ date('Y') }}" min="1980" max="{{ date('Y') + 1 }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-2">
                            <label class="field-label"><i class="fas fa-calendar-check doc-field-icon doc-field-icon-exp"></i> Valid Until <span class="text-danger">*</span></label>
                            <input type="date" id="registration_expiry_date" name="expiry_date" class="form-control form-control-modern" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group mb-2">
                            <label class="field-label"><i class="fas fa-receipt doc-field-icon doc-field-icon-or"></i> Official Receipt <span class="text-danger">*</span></label>
                            <input type="file" name="or_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="field-label"><i class="fas fa-id-card doc-field-icon doc-field-icon-cr"></i> Cert. of Reg <span class="text-danger">*</span></label>
                            <input type="file" name="cr_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="field-label"><i class="fas fa-shield-alt doc-field-icon doc-field-icon-ins"></i> Insurance <span class="text-danger">*</span></label>
                            <input type="file" name="insurance_file" class="form-control form-control-modern" accept=".pdf,.jpg,.png" required>
                        </div>
                    </div>
                    <div class="field-feedback-text error" id="registrationErrorText" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary mt-2" id="btnSubmitRegistration"><i class="fas fa-upload mr-1"></i> Upload Registration</button>
                </form>

                <div class="reg-summary-bar" id="historySummaryBar" style="display:none;">
                    <i class="fas fa-layer-group"></i> <span id="historySummaryText"></span>
                </div>

                <div id="historyModalBody">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!-- SERVICE HISTORY MODAL (combined Maintenance & PMS + Repair) -->
<div class="modal fade" id="serviceHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header-slate d-flex align-items-center justify-content-between">
                <h5 id="serviceHistoryModalTitle"><i class="fas fa-folder-open mr-2"></i> Vehicle Records</h5>
            </div>
            <div class="modal-body p-4">

                <!-- Everything ever logged against this vehicle, one tab per module, so an
                     admin never has to leave Vehicle Inventory to piece the full picture
                     together across Maintenance, Repairs, Trip Logs, Fuel Monitoring and
                     Accident Records. -->
                <ul class="nav nav-pills vr-tab-nav mb-3" id="vrTabNav">
                    <li class="nav-item"><a href="#" class="nav-link active" data-tab="service"><i class="fas fa-tools mr-1"></i> Maintenance &amp; Repairs</a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-tab="trips"><i class="fas fa-route mr-1"></i> Trip Logs</a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-tab="fuel"><i class="fas fa-gas-pump mr-1"></i> Fuel Monitoring</a></li>
                    <li class="nav-item"><a href="#" class="nav-link" data-tab="accidents"><i class="fas fa-car-crash mr-1"></i> Accidents</a></li>
                </ul>

                <!-- ============ MAINTENANCE & REPAIRS TAB ============ -->
                <div class="vr-tab-pane" id="vrPaneService">
                    <div id="serviceHistorySummary" class="row text-center mb-3" style="display:none;">
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="shSummaryTotal">0</div>
                            <div class="text-muted small">Total Jobs</div>
                        </div>
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="shSummaryMaintenance">0</div>
                            <div class="text-muted small">Maintenance &amp; PMS</div>
                        </div>
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="shSummaryRepairs">0</div>
                            <div class="text-muted small">Repairs</div>
                        </div>
                    </div>

                    <!-- Search + module filter — only meaningful once there's more than a
                         handful of records, but always shown so a vehicle's history stays
                         browsable no matter how large it eventually grows. -->
                    <div class="d-flex flex-wrap mb-3" style="gap:8px;">
                        <input type="text" id="shSearchInput" class="form-control form-control-sm" placeholder="Search description, control #, performed by..." style="flex:1; min-width:200px;">
                        <select id="shModuleFilter" class="form-control form-control-sm" style="max-width:170px;">
                            <option value="">All Jobs</option>
                            <option value="MAINTENANCE">Maintenance &amp; PMS only</option>
                            <option value="REPAIR">Repairs only</option>
                        </select>
                    </div>

                    <div id="serviceHistoryBody">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>

                    <div id="serviceHistoryPagination" class="mt-3 pt-2 border-top" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" id="shPaginationInfo"></span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="shPrevBtn"><i class="fas fa-chevron-left"></i> Prev</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="shNextBtn">Next <i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ TRIP LOGS TAB ============ -->
                <div class="vr-tab-pane" id="vrPaneTrips" style="display:none;">
                    <div id="tripHistorySummary" class="row text-center mb-3" style="display:none;">
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="thSummaryTotal">0</div>
                            <div class="text-muted small">Total Trips</div>
                        </div>
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="thSummaryRoundTrips">0</div>
                            <div class="text-muted small">Round Trips</div>
                        </div>
                        <div class="col-4">
                            <div class="font-weight-bold" style="font-size:20px;" id="thSummaryDistance">0</div>
                            <div class="text-muted small">Total Distance (km)</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap mb-3" style="gap:8px;">
                        <input type="text" id="thSearchInput" class="form-control form-control-sm" placeholder="Search origin, destination, purpose..." style="flex:1; min-width:200px;">
                    </div>

                    <div id="tripHistoryBody">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>

                    <div id="tripHistoryPagination" class="mt-3 pt-2 border-top" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" id="thPaginationInfo"></span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="thPrevBtn"><i class="fas fa-chevron-left"></i> Prev</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="thNextBtn">Next <i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ FUEL MONITORING TAB ============ -->
                <div class="vr-tab-pane" id="vrPaneFuel" style="display:none;">
                    <div id="fuelHistorySummary" class="row text-center mb-3" style="display:none;">
                        <div class="col-3">
                            <div class="font-weight-bold" style="font-size:20px;" id="fhSummaryTotal">0</div>
                            <div class="text-muted small">Refuels</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold" style="font-size:20px;" id="fhSummaryLiters">0</div>
                            <div class="text-muted small">Total Liters</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold" style="font-size:20px;" id="fhSummaryCost">&#8369;0</div>
                            <div class="text-muted small">Total Cost</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold" style="font-size:20px;" id="fhSummaryKml">&mdash;</div>
                            <div class="text-muted small">Avg. km/L</div>
                        </div>
                    </div>

                    <div id="fuelHistoryBody">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>

                    <div id="fuelHistoryPagination" class="mt-3 pt-2 border-top" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" id="fhPaginationInfo"></span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="fhPrevBtn"><i class="fas fa-chevron-left"></i> Prev</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="fhNextBtn">Next <i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ ACCIDENTS TAB ============ -->
                <div class="vr-tab-pane" id="vrPaneAccidents" style="display:none;">
                    <div id="accidentHistorySummary" class="row text-center mb-3" style="display:none;">
                        <div class="col-3">
                            <div class="font-weight-bold" style="font-size:20px;" id="ahSummaryTotal">0</div>
                            <div class="text-muted small">Total Reports</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold text-info" style="font-size:20px;" id="ahSummaryMinor">0</div>
                            <div class="text-muted small">Minor</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold text-warning" style="font-size:20px;" id="ahSummaryModerate">0</div>
                            <div class="text-muted small">Moderate</div>
                        </div>
                        <div class="col-3">
                            <div class="font-weight-bold text-danger" style="font-size:20px;" id="ahSummaryMajor">0</div>
                            <div class="text-muted small">Major</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap mb-3" style="gap:8px;">
                        <input type="text" id="ahSearchInput" class="form-control form-control-sm" placeholder="Search location, description, police report #..." style="flex:1; min-width:200px;">
                    </div>

                    <div id="accidentHistoryBody">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>

                    <div id="accidentHistoryPagination" class="mt-3 pt-2 border-top" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" id="ahPaginationInfo"></span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ahPrevBtn"><i class="fas fa-chevron-left"></i> Prev</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ahNextBtn">Next <i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
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
        serviceHistory:"{{ route('vehicles.service-history', ':id') }}",
        tripLogsHistory:"{{ route('vehicles.trip-logs-history', ':id') }}",
        fuelLogsHistory:"{{ route('vehicles.fuel-logs-history', ':id') }}",
        accidentsHistory:"{{ route('vehicles.accidents-history', ':id') }}",
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
        // DataTables' default autoWidth measures the table at init time and
        // locks that in as an inline style="width:...px" on the <table>. That
        // inline width beats our CSS width:100% on mobile (inline always
        // outranks an external stylesheet unless it uses !important), which
        // is exactly what broke the "stack into cards" mobile layout below —
        // the table stayed pinned near its desktop width instead of actually
        // going full-width on a phone.
        autoWidth: false,
        ajax: {
            url: "{{ route('vehicles.index') }}",
            data: function (d) {
                @if ($hasBroadVisibility)
                    d.unit_id = $('#filterUnit').val();
                @endif
                d.station_id = $('#filterStation').val();
                d.status = $('#filterStatus').val();
                d.vehicle_type_id = $('#filterVehicleType').val();
                d.source = $('#filterSource').val();
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

    $('#customSearchBox').on('keyup input', function() { table.search($(this).val()).draw(); });$('#filterUnit, #filterStation, #filterStatus, #filterVehicleType, #filterSource').change(function() { table.draw(); });

    // Priority Attention panel — clicking a row jumps straight to that vehicle
    // in the table below via the same search box, rather than duplicating a
    // second row-rendering/detail view just for this panel.
    $('.priority-row').on('click', function() {
        const plate = $(this).data('plate');
        $('#customSearchBox').val(plate);
        table.search(plate).draw();
        $('html, body').animate({ scrollTop: $('#vehiclesTable').offset().top - 100 }, 400);
    });
    
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

    // BER sub-status only makes sense once Status = BER, and the disposal date
    // only once the sub-status is specifically "Disposed" — same nested-toggle
    // pattern as the DRIVER account type's driver-profile field on System Users.
    function toggleBerFields($statusSelect) {
        let $form = $statusSelect.closest('form');
        let $wrapper = $form.find('.ber-field-wrapper');
        let $subStatusSelect = $wrapper.find('.ber-sub-status-select');
        let isBer = $statusSelect.val() === 'BER';

        $wrapper.toggleClass('d-none', !isBer);
        $subStatusSelect.prop('required', isBer);
        if (!isBer) {
            $subStatusSelect.val('');
            toggleDisposalDate($subStatusSelect);
        }
    }

    function toggleDisposalDate($subStatusSelect) {
        let $form = $subStatusSelect.closest('form');
        let $dateWrapper = $form.find('.disposal-date-wrapper');
        let $dateInput = $dateWrapper.find('.disposal-date-input');
        let isDisposed = $subStatusSelect.val() === 'DISPOSED';

        $dateWrapper.toggleClass('d-none', !isDisposed);
        $dateInput.prop('required', isDisposed);
        if (!isDisposed) {
            $dateInput.val('');
        }
    }

    $('#status, #edit_status').on('change', function() { toggleBerFields($(this)); });
    $(document).on('change', '.ber-sub-status-select', function() { toggleDisposalDate($(this)); });

    // The "Unserviceable since" note reflects whatever was loaded from the
    // server — the moment the admin touches Status themselves, that note is
    // stale (a save will restamp or clear the date), so hide it rather than
    // leave a misleading date on screen until the next time this modal opens.
    $('#edit_status').on('change', function() { $('#edit_unserviceable_note').addClass('d-none').text(''); });

    $('#resetFiltersBtn').click(function() {
        $('#customSearchBox, #filterStation, #filterStatus, #filterVehicleType, #filterSource').val('');
        @if ($hasBroadVisibility)$('#filterUnit').val('');
        @endif
        $('#filterStation option').show();
        table.search('').draw();
    });

    let currentHistoryVehicleId = null;

    // One "document chip" per OR/CR/Insurance slot. When a registration row has
    // no file for that slot — either an older record predating the Insurance
    // column, or (in principle) a very old legacy row — it renders as a muted,
    // non-clickable placeholder instead of a dead/broken link.
    function registrationDocChip(label, icon, colorClass, url) {
        if (!url) {
            return `
            <div class="reg-doc-chip reg-doc-missing">
                <div class="reg-doc-icon ${colorClass}"><i class="fas ${icon}"></i></div>
                <div class="reg-doc-meta">
                    <div class="reg-doc-label">${label}</div>
                    <div class="reg-doc-status">Not uploaded</div>
                </div>
            </div>`;
        }
        return `
        <a href="${url}" target="_blank" rel="noopener" class="reg-doc-chip">
            <div class="reg-doc-icon ${colorClass}"><i class="fas ${icon}"></i></div>
            <div class="reg-doc-meta">
                <div class="reg-doc-label">${label}</div>
                <div class="reg-doc-status">View document <i class="fas fa-external-link-alt"></i></div>
            </div>
        </a>`;
    }

    // "Valid until <date>" line for one registration year card, with an
    // overdue/due-soon badge — same badge-pms classes the PMS column already
    // uses, so an expiring registration reads the same way an overdue PMS
    // date does elsewhere on this page. Omitted entirely for older rows that
    // predate expiry_date being tracked (reg.expiry_date is null then).
    function registrationExpiryLine(reg) {
        if (!reg.expiry_date) return '';
        let badge = '';
        if (reg.expiry_status === 'overdue') {
            badge = ' <span class="badge-pms pms-badge-overdue"><i class="fas fa-exclamation-triangle"></i> Expired</span>';
        } else if (reg.expiry_status === 'soon') {
            badge = ' <span class="badge-pms pms-badge-soon"><i class="fas fa-clock"></i> Due soon</span>';
        }
        return `<p class="reg-uploaded-date"><i class="fas fa-calendar-check"></i>Valid until ${reg.expiry_date}${badge}</p>`;
    }

    function loadHistory(id) {
        currentHistoryVehicleId = id;
        $('#registration_vehicle_id').val(id);
        $('#addRegistrationForm').hide();
        $('#addRegistrationForm')[0].reset();
        // Default "Valid Until" to one year out — the common case — editable
        // before upload if the actual OR/CR/Insurance expiry differs.
        $('#registration_expiry_date').val(new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().slice(0, 10));
        $('#registrationErrorText').hide().text('');
        $('#historyModalSubtitle').text('');
        $('#historySummaryBar').hide();
        $('#historyModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');

        $.get(vehicleRoute('history', id), function(data) {
            $('#historyModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} — Registration History`);
            $('#historyModalSubtitle').text(data.make_model);

            let html = '';
            if (data.registrations.length === 0) {
                html = '<div class="alert alert-info">No document history found yet. Use "Add Registration" above to upload the first OR/CR/Insurance.</div>';
            } else {
                const yearWord = data.registrations.length === 1 ? 'year' : 'years';
                $('#historySummaryText').text(`${data.registrations.length} registration ${yearWord} on file — most recent first`);
                $('#historySummaryBar').show();

                // Backend already orders registrations latest-year-first, so the
                // very first card in the list is always the vehicle's current,
                // in-force registration — flagged here rather than re-sorting.
                data.registrations.forEach(function(reg, index) {
                    const isLatest = index === 0;
                    html += `
                    <div class="reg-year-card ${isLatest ? 'is-latest' : ''}">
                        <div class="reg-year-head">
                            <div class="reg-year-badge-wrap">
                                <span class="reg-year-badge">${reg.year}</span>
                                ${isLatest ? '<span class="reg-current-chip"><i class="fas fa-check mr-1"></i>Current</span>' : ''}
                            </div>
                            <span class="reg-uploader-chip"><i class="fas fa-user-edit"></i>Encoded by ${reg.uploader}</span>
                        </div>
                        <p class="reg-uploaded-date"><i class="fas fa-clock"></i>Uploaded ${reg.date}</p>
                        ${registrationExpiryLine(reg)}
                        <div class="reg-doc-grid">
                            ${registrationDocChip('Official Receipt', 'fa-receipt', 'doc-or', reg.or_url)}
                            ${registrationDocChip('Cert. of Registration', 'fa-id-card', 'doc-cr', reg.cr_url)}
                            ${registrationDocChip('Insurance', 'fa-shield-alt', 'doc-ins', reg.insurance_url)}
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

    // Combined Maintenance & PMS + Repair timeline for one vehicle — every job ever
    // logged against it, regardless of stage or which of the two modules it was
    // opened from, so nothing has to be pieced together by filtering two separate
    // pages. Read-only: no edit/delete actions here, just the record of what's
    // been done — those actions stay on the Maintenance & PMS / Repairs pages
    // themselves, where the process-flow buttons (checklist, requisition, complete)
    // already live.
    //
    // Server-side paginated (10 at a time) with search + module filter, same
    // reasoning as the Maintenance & PMS / Repairs DataTables: a vehicle in long
    // service can rack up hundreds of records, and rendering all of them into
    // this modal at once would make it slow to open and effectively unbrowsable.
    // currentServiceHistory tracks the modal's own paging/filter state between
    // the Prev/Next clicks and the search box's keyup handler below — it's reset
    // to page 1 with no filters every time the modal is opened for a (possibly
    // different) vehicle.
    let currentServiceHistory = { vehicleId: null, start: 0, length: 10, search: '', module: '' };

    function loadServiceHistory() {
        const state = currentServiceHistory;
        $('#serviceHistoryBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#serviceHistoryPagination').hide();

        $.get(vehicleRoute('serviceHistory', state.vehicleId), {
            start: state.start,
            length: state.length,
            search: state.search,
            module: state.module,
        }, function(data) {
            $('#serviceHistoryModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} — Vehicle Records`);

            $('#shSummaryTotal').text(data.summary.total);
            $('#shSummaryMaintenance').text(data.summary.maintenance);
            $('#shSummaryRepairs').text(data.summary.repairs);
            $('#serviceHistorySummary').toggle(data.summary.total > 0);

            if (data.summary.total === 0) {
                $('#serviceHistoryBody').html('<div class="alert alert-info">No maintenance or repair records logged for this vehicle yet.</div>');
                return;
            }
            if (data.records.length === 0) {
                $('#serviceHistoryBody').html('<div class="alert alert-info">No jobs match this search/filter.</div>');
                return;
            }

            let html = '';
            data.records.forEach(function(r) {
                const moduleBadge = r.module === 'Repair'
                    ? '<span class="badge badge-warning px-2 py-1">Repair</span>'
                    : '<span class="badge badge-primary px-2 py-1">Maintenance &amp; PMS</span>';
                // Every Repair-module record's type_label is just "Repair" too (maintenance_type
                // is always REPAIR there) — showing it again next to the module badge above would
                // just repeat the same word, so it's only shown for Maintenance & PMS, where the
                // type actually varies (PMS, Oil Change, Tire Change, etc.).
                const typeBadge = r.module === 'Repair'
                    ? ''
                    : `<span class="badge badge-${r.type_color} px-2 py-1">${r.type_label}</span>`;
                const stageBadge = `<span class="badge badge-${r.stage_color} px-2 py-1">${r.stage_label}</span>`;
                const dateLabel = r.is_placeholder_date
                    ? `${r.service_date} <span class="text-muted">(requested)</span>`
                    : r.service_date;
                const costLine = r.cost !== null
                    ? `<div><i class="fas fa-coins text-muted mr-1"></i> &#8369;${Number(r.cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>`
                    : '';
                const odometerLine = r.odometer_km
                    ? `<div><i class="fas fa-tachometer-alt text-muted mr-1"></i> ${Number(r.odometer_km).toLocaleString()} km</div>`
                    : '';
                const performedLine = r.performed_by
                    ? `<div><i class="fas fa-user-cog text-muted mr-1"></i> ${r.performed_by}</div>`
                    : '';
                const nextDueLine = r.next_due_date
                    ? `<div><i class="fas fa-calendar-check text-muted mr-1"></i> Next due: ${r.next_due_date}</div>`
                    : '';

                html += `
                <div class="history-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:6px;">
                        <div>${moduleBadge} ${typeBadge} ${stageBadge}</div>
                        ${r.control_number ? `<span class="text-muted small">${r.control_number}</span>` : ''}
                    </div>
                    ${r.description ? `<p class="mb-2">${r.description}</p>` : ''}
                    <div class="small text-dark" style="line-height:1.9;">
                        <div><i class="fas fa-calendar text-muted mr-1"></i> ${dateLabel}</div>
                        ${odometerLine}
                        ${costLine}
                        ${performedLine}
                        ${nextDueLine}
                    </div>
                    <p class="text-muted small mb-0 mt-2"><i class="fas fa-clock"></i> Logged by ${r.logged_by} on ${r.logged_at}</p>
                </div>`;
            });
            $('#serviceHistoryBody').html(html);

            // Pagination footer — only shown once there's more than one page, same
            // "don't clutter the UI for the common small-vehicle case" reasoning as
            // the summary cards above.
            if (data.recordsFiltered > state.length) {
                const shownFrom = state.start + 1;
                const shownTo = Math.min(state.start + state.length, data.recordsFiltered);
                $('#shPaginationInfo').text(`Showing ${shownFrom}–${shownTo} of ${data.recordsFiltered} job${data.recordsFiltered === 1 ? '' : 's'}`);
                $('#shPrevBtn').prop('disabled', state.start <= 0);
                $('#shNextBtn').prop('disabled', state.start + state.length >= data.recordsFiltered);
                $('#serviceHistoryPagination').show();
            } else {
                $('#serviceHistoryPagination').hide();
            }
        }).fail(function() {
            $('#serviceHistoryBody').html('<div class="alert alert-danger">Error loading maintenance/repair history. You may not have permission.</div>');
            $('#serviceHistoryPagination').hide();
        });
    }

    // ---------------- Trip Logs tab ----------------
    let currentTripHistory = { vehicleId: null, start: 0, length: 10, search: '' };

    function loadTripHistory() {
        const state = currentTripHistory;
        $('#tripHistoryBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#tripHistoryPagination').hide();

        $.get(vehicleRoute('tripLogsHistory', state.vehicleId), {
            start: state.start,
            length: state.length,
            search: state.search,
        }, function(data) {
            $('#serviceHistoryModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} — Vehicle Records`);

            $('#thSummaryTotal').text(data.summary.total);
            $('#thSummaryRoundTrips').text(data.summary.round_trips);
            $('#thSummaryDistance').text(Number(data.summary.total_distance_km).toLocaleString());
            $('#tripHistorySummary').toggle(data.summary.total > 0);

            if (data.summary.total === 0) {
                $('#tripHistoryBody').html('<div class="alert alert-info">No trips logged for this vehicle yet.</div>');
                return;
            }
            if (data.records.length === 0) {
                $('#tripHistoryBody').html('<div class="alert alert-info">No trips match this search.</div>');
                return;
            }

            let html = '';
            data.records.forEach(function(r) {
                const legChip = r.leg === 'outbound'
                    ? '<span class="round-trip-chip">Round Trip &middot; Outbound</span>'
                    : (r.leg === 'return' ? '<span class="round-trip-chip">Round Trip &middot; Return</span>' : '');
                const timeLine = (r.departure_time || r.arrival_time)
                    ? `<div><i class="fas fa-clock text-muted mr-1"></i> ${r.departure_time || '—'} to ${r.arrival_time || '—'}</div>`
                    : '';
                const odometerLine = (r.odometer_start !== null || r.odometer_end !== null)
                    ? `<div><i class="fas fa-tachometer-alt text-muted mr-1"></i> ${r.odometer_start !== null ? Number(r.odometer_start).toLocaleString() : '—'} &rarr; ${r.odometer_end !== null ? Number(r.odometer_end).toLocaleString() : '—'}${r.distance_km !== null ? ` (${Number(r.distance_km).toLocaleString()} km)` : ''}</div>`
                    : '';
                const driverLine = r.driver_name
                    ? `<div><i class="fas fa-id-card text-muted mr-1"></i> ${r.driver_name}</div>`
                    : '';
                const passengersLine = r.passengers
                    ? `<div><i class="fas fa-users text-muted mr-1"></i> ${r.passengers}</div>`
                    : '';

                html += `
                <div class="history-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:6px;">
                        <div class="font-weight-bold">${r.origin} <i class="fas fa-arrow-right text-muted mx-1" style="font-size:11px;"></i> ${r.destination}${legChip}</div>
                        <span class="text-muted small">${r.trip_date}</span>
                    </div>
                    ${r.purpose ? `<p class="mb-2">${r.purpose}</p>` : ''}
                    <div class="small text-dark" style="line-height:1.9;">
                        ${timeLine}
                        ${odometerLine}
                        ${driverLine}
                        ${passengersLine}
                    </div>
                    ${r.remarks ? `<p class="text-muted small mb-0 mt-2"><i class="fas fa-sticky-note mr-1"></i> ${r.remarks}</p>` : ''}
                    <p class="text-muted small mb-0 mt-2"><i class="fas fa-clock"></i> Logged on ${r.logged_at}</p>
                </div>`;
            });
            $('#tripHistoryBody').html(html);

            if (data.recordsFiltered > state.length) {
                const shownFrom = state.start + 1;
                const shownTo = Math.min(state.start + state.length, data.recordsFiltered);
                $('#thPaginationInfo').text(`Showing ${shownFrom}–${shownTo} of ${data.recordsFiltered} trip${data.recordsFiltered === 1 ? '' : 's'}`);
                $('#thPrevBtn').prop('disabled', state.start <= 0);
                $('#thNextBtn').prop('disabled', state.start + state.length >= data.recordsFiltered);
                $('#tripHistoryPagination').show();
            } else {
                $('#tripHistoryPagination').hide();
            }
        }).fail(function() {
            $('#tripHistoryBody').html('<div class="alert alert-danger">Error loading trip logs. You may not have permission.</div>');
            $('#tripHistoryPagination').hide();
        });
    }

    // ---------------- Fuel Monitoring tab ----------------
    let currentFuelHistory = { vehicleId: null, start: 0, length: 10 };

    function loadFuelHistory() {
        const state = currentFuelHistory;
        $('#fuelHistoryBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#fuelHistoryPagination').hide();

        $.get(vehicleRoute('fuelLogsHistory', state.vehicleId), {
            start: state.start,
            length: state.length,
        }, function(data) {
            $('#serviceHistoryModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} — Vehicle Records`);

            $('#fhSummaryTotal').text(data.summary.total);
            $('#fhSummaryLiters').text(Number(data.summary.total_liters).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' L');
            $('#fhSummaryCost').html('&#8369;' + Number(data.summary.total_cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#fhSummaryKml').text(data.summary.avg_kml !== null ? Number(data.summary.avg_kml).toFixed(2) : '—');
            $('#fuelHistorySummary').toggle(data.summary.total > 0);

            if (data.summary.total === 0) {
                $('#fuelHistoryBody').html('<div class="alert alert-info">No refuels logged for this vehicle yet.</div>');
                return;
            }

            let html = '';
            data.records.forEach(function(r) {
                const effLine = (r.km_per_liter !== null && r.distance_km !== null)
                    ? `<div><i class="fas fa-bolt text-muted mr-1"></i> ${Number(r.distance_km).toLocaleString()} km since last &middot; ${Number(r.km_per_liter).toFixed(2)} km/L</div>`
                    : `<div class="text-muted"><i class="fas fa-bolt mr-1"></i> First logged refuel for this vehicle</div>`;
                const driverLine = r.driver_name
                    ? `<div><i class="fas fa-id-card text-muted mr-1"></i> ${r.driver_name}</div>`
                    : '';
                const receiptLine = r.receipt_url
                    ? `<div><a href="${r.receipt_url}" target="_blank"><i class="fas fa-receipt mr-1"></i> View Receipt</a></div>`
                    : '';

                html += `
                <div class="history-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:6px;">
                        <div class="font-weight-bold">${Number(r.liters).toFixed(2)} L &middot; &#8369;${Number(r.total_cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                        <span class="text-muted small">${r.refuel_date}</span>
                    </div>
                    <div class="small text-dark" style="line-height:1.9;">
                        <div><i class="fas fa-tachometer-alt text-muted mr-1"></i> ${Number(r.odometer_reading).toLocaleString()} km</div>
                        ${effLine}
                        ${r.price_per_liter !== null ? `<div><i class="fas fa-coins text-muted mr-1"></i> &#8369;${Number(r.price_per_liter).toFixed(2)}/L</div>` : ''}
                        ${driverLine}
                        ${receiptLine}
                    </div>
                    <p class="text-muted small mb-0 mt-2"><i class="fas fa-clock"></i> Logged on ${r.logged_at}</p>
                </div>`;
            });
            $('#fuelHistoryBody').html(html);

            if (data.recordsFiltered > state.length) {
                const shownFrom = state.start + 1;
                const shownTo = Math.min(state.start + state.length, data.recordsFiltered);
                $('#fhPaginationInfo').text(`Showing ${shownFrom}–${shownTo} of ${data.recordsFiltered} refuel${data.recordsFiltered === 1 ? '' : 's'}`);
                $('#fhPrevBtn').prop('disabled', state.start <= 0);
                $('#fhNextBtn').prop('disabled', state.start + state.length >= data.recordsFiltered);
                $('#fuelHistoryPagination').show();
            } else {
                $('#fuelHistoryPagination').hide();
            }
        }).fail(function() {
            $('#fuelHistoryBody').html('<div class="alert alert-danger">Error loading fuel logs. You may not have permission.</div>');
            $('#fuelHistoryPagination').hide();
        });
    }

    // ---------------- Accidents tab ----------------
    let currentAccidentHistory = { vehicleId: null, start: 0, length: 10, search: '' };

    function loadAccidentHistory() {
        const state = currentAccidentHistory;
        $('#accidentHistoryBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#accidentHistoryPagination').hide();

        $.get(vehicleRoute('accidentsHistory', state.vehicleId), {
            start: state.start,
            length: state.length,
            search: state.search,
        }, function(data) {
            $('#serviceHistoryModalTitle').html(`<i class="fas fa-folder-open mr-2"></i> ${data.plate_number} — Vehicle Records`);

            $('#ahSummaryTotal').text(data.summary.total);
            $('#ahSummaryMinor').text(data.summary.minor);
            $('#ahSummaryModerate').text(data.summary.moderate);
            $('#ahSummaryMajor').text(data.summary.major);
            $('#accidentHistorySummary').toggle(data.summary.total > 0);

            if (data.summary.total === 0) {
                $('#accidentHistoryBody').html('<div class="alert alert-info">No accidents logged for this vehicle yet.</div>');
                return;
            }
            if (data.records.length === 0) {
                $('#accidentHistoryBody').html('<div class="alert alert-info">No reports match this search.</div>');
                return;
            }

            const severityBadge = { MINOR: 'badge-info', MODERATE: 'badge-warning', MAJOR: 'badge-danger' };

            let html = '';
            data.records.forEach(function(r) {
                const badgeClass = severityBadge[r.severity] || 'badge-secondary';
                const costLine = r.estimated_cost !== null
                    ? `<div><i class="fas fa-coins text-muted mr-1"></i> &#8369;${Number(r.estimated_cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} estimated</div>`
                    : '';
                const driverLine = r.driver_name
                    ? `<div><i class="fas fa-id-card text-muted mr-1"></i> ${r.driver_name}</div>`
                    : '';
                const reportLine = r.police_report_no
                    ? `<div><i class="fas fa-file-alt text-muted mr-1"></i> Police Report #${r.police_report_no}</div>`
                    : '';
                const photoLine = r.photo_url
                    ? `<div><a href="${r.photo_url}" target="_blank"><i class="fas fa-image mr-1"></i> View Photo</a></div>`
                    : '';

                html += `
                <div class="history-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2" style="gap:6px;">
                        <div><span class="badge ${badgeClass} px-2 py-1">${r.severity_label}</span> <span class="font-weight-bold ml-1">${r.location}</span></div>
                        <span class="text-muted small">${r.accident_date}${r.accident_time ? ' · ' + r.accident_time : ''}</span>
                    </div>
                    ${r.description ? `<p class="mb-2">${r.description}</p>` : ''}
                    <div class="small text-dark" style="line-height:1.9;">
                        ${costLine}
                        ${driverLine}
                        ${reportLine}
                        ${photoLine}
                    </div>
                    <p class="text-muted small mb-0 mt-2"><i class="fas fa-clock"></i> Logged on ${r.logged_at}</p>
                </div>`;
            });
            $('#accidentHistoryBody').html(html);

            if (data.recordsFiltered > state.length) {
                const shownFrom = state.start + 1;
                const shownTo = Math.min(state.start + state.length, data.recordsFiltered);
                $('#ahPaginationInfo').text(`Showing ${shownFrom}–${shownTo} of ${data.recordsFiltered} report${data.recordsFiltered === 1 ? '' : 's'}`);
                $('#ahPrevBtn').prop('disabled', state.start <= 0);
                $('#ahNextBtn').prop('disabled', state.start + state.length >= data.recordsFiltered);
                $('#accidentHistoryPagination').show();
            } else {
                $('#accidentHistoryPagination').hide();
            }
        }).fail(function() {
            $('#accidentHistoryBody').html('<div class="alert alert-danger">Error loading accident records. You may not have permission.</div>');
            $('#accidentHistoryPagination').hide();
        });
    }

    // ---------------- Tab switching ----------------
    // Each tab's data loads lazily, the first time it's activated for the
    // currently-open vehicle — vrLoadedTabs is reset every time the modal is
    // opened (possibly for a different vehicle) so switching back to an
    // already-loaded tab doesn't re-fetch anything already on screen.
    let vrLoadedTabs = {};
    const vrPanes = { service: '#vrPaneService', trips: '#vrPaneTrips', fuel: '#vrPaneFuel', accidents: '#vrPaneAccidents' };
    const vrLoaders = { service: loadServiceHistory, trips: loadTripHistory, fuel: loadFuelHistory, accidents: loadAccidentHistory };

    $('#vrTabNav').on('click', '.nav-link', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        if ($(this).hasClass('active')) return;

        $('#vrTabNav .nav-link').removeClass('active');
        $(this).addClass('active');
        $.each(vrPanes, function(key, selector) { $(selector).toggle(key === tab); });

        if (!vrLoadedTabs[tab]) {
            vrLoadedTabs[tab] = true;
            vrLoaders[tab]();
        }
    });

    $('#vehiclesTable').on('click', '.btn-service-history', function() {
        let id = $(this).data('id');
        // Fresh page/filter state every time the modal opens — otherwise a search
        // or page position left over from a previous vehicle (or an earlier look
        // at this same one) would carry over and quietly hide records.
        currentServiceHistory = { vehicleId: id, start: 0, length: 10, search: '', module: '' };
        currentTripHistory = { vehicleId: id, start: 0, length: 10, search: '' };
        currentFuelHistory = { vehicleId: id, start: 0, length: 10 };
        currentAccidentHistory = { vehicleId: id, start: 0, length: 10, search: '' };
        vrLoadedTabs = { service: true };

        $('#shSearchInput').val('');
        $('#shModuleFilter').val('');
        $('#thSearchInput').val('');
        $('#ahSearchInput').val('');

        // Always reopen on the Maintenance & Repairs tab, same starting point
        // regardless of which tab was active the last time this modal closed.
        $('#vrTabNav .nav-link').removeClass('active');
        $('#vrTabNav .nav-link[data-tab="service"]').addClass('active');
        $.each(vrPanes, function(key, selector) { $(selector).toggle(key === 'service'); });

        $('#serviceHistoryModal').modal('show');
        loadServiceHistory();
    });

    let shSearchDebounce;
    $('#shSearchInput').on('keyup input', function() {
        clearTimeout(shSearchDebounce);
        const value = $(this).val();
        shSearchDebounce = setTimeout(function() {
            currentServiceHistory.search = value;
            currentServiceHistory.start = 0;
            loadServiceHistory();
        }, 300);
    });

    $('#shModuleFilter').on('change', function() {
        currentServiceHistory.module = $(this).val();
        currentServiceHistory.start = 0;
        loadServiceHistory();
    });

    $('#shPrevBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentServiceHistory.start = Math.max(0, currentServiceHistory.start - currentServiceHistory.length);
        loadServiceHistory();
    });

    $('#shNextBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentServiceHistory.start += currentServiceHistory.length;
        loadServiceHistory();
    });

    let thSearchDebounce;
    $('#thSearchInput').on('keyup input', function() {
        clearTimeout(thSearchDebounce);
        const value = $(this).val();
        thSearchDebounce = setTimeout(function() {
            currentTripHistory.search = value;
            currentTripHistory.start = 0;
            loadTripHistory();
        }, 300);
    });

    $('#thPrevBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentTripHistory.start = Math.max(0, currentTripHistory.start - currentTripHistory.length);
        loadTripHistory();
    });

    $('#thNextBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentTripHistory.start += currentTripHistory.length;
        loadTripHistory();
    });

    $('#fhPrevBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentFuelHistory.start = Math.max(0, currentFuelHistory.start - currentFuelHistory.length);
        loadFuelHistory();
    });

    $('#fhNextBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentFuelHistory.start += currentFuelHistory.length;
        loadFuelHistory();
    });

    let ahSearchDebounce;
    $('#ahSearchInput').on('keyup input', function() {
        clearTimeout(ahSearchDebounce);
        const value = $(this).val();
        ahSearchDebounce = setTimeout(function() {
            currentAccidentHistory.search = value;
            currentAccidentHistory.start = 0;
            loadAccidentHistory();
        }, 300);
    });

    $('#ahPrevBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentAccidentHistory.start = Math.max(0, currentAccidentHistory.start - currentAccidentHistory.length);
        loadAccidentHistory();
    });

    $('#ahNextBtn').on('click', function() {
        if ($(this).prop('disabled')) return;
        currentAccidentHistory.start += currentAccidentHistory.length;
        loadAccidentHistory();
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

    // ---------------- AI Document Intelligence: auto-fill from OR photo ----------------
    // Reuses the same file the officer is already required to upload (no second picker) —
    // as soon as it's chosen, it's sent off to be read, and whatever comes back pre-fills
    // the fields above so there's less to type. Every field it fills stays a normal,
    // editable input, so a misread is just corrected before Save like a typo would be.
    // Gated on the same server-side flag as the hint text above: when no API key is
    // configured yet, this stays completely silent instead of firing a call just to
    // show a "not configured" message on every single upload.
    const aiDocumentScanningEnabled = @json($aiDocumentScanningEnabled ?? false);

    $('#or_file').on('change', function() {
        const file = this.files && this.files[0];
        const statusEl = $('#orScanStatus');
        // Accept photos as well as PDF exports — a scanned/saved OR is very
        // commonly a PDF, and the backend now reads both the same way.
        const isScannable = file && (/^image\//.test(file.type) || file.type === 'application/pdf');
        if (!aiDocumentScanningEnabled || !isScannable) {
            statusEl.hide();
            return;
        }

        statusEl.removeClass('error success').addClass('text-muted').show()
            .html('<i class="fas fa-spinner fa-spin"></i> Reading document…');

        const formData = new FormData();
        formData.append('image', file);

        $.ajax({
            url: "{{ route('ai.extract-vehicle-document') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(res) {
            if (!res.success) {
                statusEl.removeClass('text-muted success').addClass('error').text(res.message || 'Could not read this document.');
                return;
            }

            const d = res.data || {};
            let filled = 0;
            const setIfEmpty = function(selector, value) {
                if (value === null || value === undefined || value === '') return;
                const $field = $(selector);
                if (!$field.val()) { $field.val(value); filled++; }
            };

            setIfEmpty('#plate_number', d.plate_number);
            setIfEmpty('#make', d.make);
            setIfEmpty('#model', d.model);
            setIfEmpty('#year_model', d.year_model);
            setIfEmpty('#color', d.color);
            setIfEmpty('[name="engine_number"]', d.engine_number);
            setIfEmpty('[name="chassis_number"]', d.chassis_number);

            if (filled > 0) {
                statusEl.removeClass('text-muted error').addClass('success')
                    .html('<i class="fas fa-check"></i> Auto-filled ' + filled + ' field(s) — please review before saving.');
                $('.live-check-field').trigger('input');
            } else {
                statusEl.removeClass('text-muted success').addClass('error').text('No readable fields found — please enter details manually.');
            }
        }).fail(function() {
            statusEl.removeClass('text-muted success').addClass('error').text('Scanning failed — please enter details manually.');
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
            toggleBerFields($('#edit_status'));
            $('#edit_ber_sub_status').val(data.ber_sub_status);
            toggleDisposalDate($('#edit_ber_sub_status'));
            $('#edit_disposal_date').val(data.disposal_date);
            $('#edit_source').val(data.source);

            // Read-only context — VehicleController stamps/clears this itself
            // whenever status changes, so it's shown here, never edited.
            const $unsNote = $('#edit_unserviceable_note');
            if (data.status === 'UNSERVICEABLE' && data.unserviceable_since) {
                $unsNote.text('Unserviceable since ' + data.unserviceable_since + ' (' + data.days_unserviceable + ' days).').removeClass('d-none');
            } else {
                $unsNote.addClass('d-none').text('');
            }
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

        // Re-sync the BER sub-status/disposal-date wrappers to whatever Status
        // currently holds — the form doesn't get a hard .reset() between opens,
        // so a leftover BER selection from a prior open would otherwise leave
        // the wrapper's visibility out of sync with the field it's guarding.
        toggleBerFields($('#status'));

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