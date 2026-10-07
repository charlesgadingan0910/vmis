@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | Dashboard</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .welcome-banner{
    background:linear-gradient(135deg, #1e293b, #0f172a);
    border-radius:14px; padding:24px 28px; color:#fff; margin-bottom:22px; position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
  }
  .welcome-banner::after{content:'';position:absolute;top:-60%;right:-10%;width:50%;height:220%;background:radial-gradient(circle,rgba(59,130,246,0.18),transparent 65%);}
  .welcome-banner h4{margin:0; font-weight:700; letter-spacing:-0.3px; position:relative; z-index:1;}
  .welcome-banner p{margin:4px 0 0; color:#94a3b8; font-size:13.5px; position:relative; z-index:1;}
  .welcome-banner .badge-role{
    background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3);
    font-size:11px; font-weight:700; letter-spacing:0.05em; padding:5px 10px; border-radius:20px;
  }
  .welcome-meta{ position:relative; z-index:1; text-align:right; }
  .welcome-clock{ font-size:20px; font-weight:800; color:#fff; letter-spacing:0.5px; font-variant-numeric: tabular-nums; }
  .welcome-date{ font-size:12px; color:#94a3b8; margin-top:2px; }
  .welcome-scope{ display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:11.5px; color:#cbd5e1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); padding:4px 10px; border-radius:20px; position:relative; z-index:1; }


  /* ============================================================
     Fleet-at-a-glance KPI strip — one cohesive card with internal
     dividers instead of four look-alike boxes repeated edge to edge.
     Used for the top-line Fleet Overview counts. ============== */
  .kpi-strip-panel { background: #fff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 4px 12px rgba(15,23,42,0.03); display: flex; flex-wrap: wrap; margin-bottom: 16px; overflow: hidden; }
  .kpi-strip-item { flex: 1 1 0; min-width: 190px; display: flex; align-items: center; gap: 14px; padding: 20px 22px; border-right: 1px solid #f1f4f8; }
  .kpi-strip-item:last-child { border-right: none; }
  .kpi-strip-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex: none; }
  .kpi-strip-icon.c-vehicles { background: rgba(59,130,246,0.12); color: #3b82f6; }
  .kpi-strip-icon.c-drivers { background: rgba(139,92,246,0.12); color: #7c3aed; }
  .kpi-strip-icon.c-users { background: rgba(20,184,166,0.12); color: #0d9488; }
  .kpi-strip-icon.c-records { background: rgba(99,102,241,0.12); color: #6366f1; }
  .kpi-strip-value { font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .kpi-strip-label { font-size: 11.5px; font-weight: 600; color: #64748b; margin-top: 2px; }
  @media (max-width: 991px) { .kpi-strip-item { flex: 1 1 50%; border-right: none; border-bottom: 1px solid #f1f4f8; } .kpi-strip-item:nth-child(odd) { border-right: 1px solid #f1f4f8; } }
  @media (max-width: 575px) { .kpi-strip-item { flex: 1 1 100%; border-right: none !important; } }

  /* ============================================================
     Fleet Health — two compliance rings side by side (PMS,
     Registration) instead of eight near-identical icon boxes. The
     ring's color itself carries the status (good/warn/critical),
     same reasoning as a status-tagged meter. ==================== */
  .health-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
  @media (max-width: 860px) { .health-row { grid-template-columns: 1fr; } }
  .health-card { background: #fff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 4px 12px rgba(15,23,42,0.03); padding: 20px 22px; }
  .health-card-title { font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
  .health-card-title i { color: #94a3b8; font-size: 12px; }
  .health-card-body { display: flex; align-items: center; gap: 22px; }
  .health-ring-wrap { position: relative; width: 102px; height: 102px; flex: none; }
  .health-ring { width: 102px; height: 102px; border-radius: 50%; background: conic-gradient(var(--ring-color, #3b82f6) calc(var(--pct, 0) * 3.6deg), #eef1f6 0deg); }
  .health-ring-inner { position: absolute; inset: 11px; background: #fff; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .health-ring-pct { font-size: 19px; font-weight: 800; color: #0f172a; line-height: 1; }
  .health-ring-lbl { font-size: 8.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: .04em; margin-top: 2px; }
  .health-ring.ring-good { --ring-color: #16a34a; }
  .health-ring.ring-warn { --ring-color: #d97706; }
  .health-ring.ring-crit { --ring-color: #dc2626; }
  .health-metrics { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 11px; }
  .health-metric-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12.5px; }
  .health-metric-label { display: flex; align-items: center; gap: 8px; color: #475569; font-weight: 600; min-width: 0; }
  .health-metric-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
  .health-metric-value { font-weight: 800; color: #0f172a; flex: none; }

  /* ============================================================
     Fuel Monitoring hero panel — one headline metric (efficiency)
     plus supporting figures in the same card, instead of a fourth
     repeat of the icon-box grid. ================================ */
  .fuel-hero-panel { background: linear-gradient(135deg, #fffdf7, #fff); border-radius: 16px; border: 1px solid #fde8c6; box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 4px 12px rgba(15,23,42,0.03); padding: 20px 24px; margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; gap: 22px; }
  .fuel-hero-main { display: flex; align-items: center; gap: 16px; padding-right: 24px; border-right: 1px solid #fde8c6; }
  @media (max-width: 860px) { .fuel-hero-main { border-right: none; padding-right: 0; width: 100%; } }
  .fuel-hero-icon { width: 58px; height: 58px; border-radius: 15px; background: rgba(217,119,6,0.14); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 23px; flex: none; }
  .fuel-hero-value { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1; white-space: nowrap; }
  .fuel-hero-label { font-size: 11.5px; color: #92400e; font-weight: 700; margin-top: 4px; text-transform: uppercase; letter-spacing: .03em; }
  .fuel-mini-stats { display: flex; flex: 1; flex-wrap: wrap; gap: 22px; }
  .fuel-mini-stat { display: flex; align-items: center; gap: 11px; min-width: 150px; }
  .fuel-mini-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex: none; }
  .fuel-mini-icon.c-logs { background: rgba(217,119,6,0.12); color: #d97706; }
  .fuel-mini-icon.c-liters { background: rgba(14,165,233,0.12); color: #0284c7; }
  .fuel-mini-icon.c-cost { background: rgba(220,38,38,0.1); color: #dc2626; }
  .fuel-mini-value { font-size: 16px; font-weight: 800; color: #0f172a; line-height: 1.2; }
  .fuel-mini-label { font-size: 10.5px; color: #94a3b8; font-weight: 600; }

  .dash-panel { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; margin-bottom: 22px; height: calc(100% - 22px); }
  .dash-panel-header { padding: 18px 22px; border-bottom: 1px solid #eef1f6; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .dash-panel-header h6 { font-weight: 800; color: #0f172a; margin: 0; font-size: 14.5px; }
  .dash-panel-header .view-all-link { font-size: 12px; font-weight: 700; color: #3b82f6; text-decoration: none; }
  .dash-panel-header .view-all-link:hover { text-decoration: underline; }
  .dash-panel-body { padding: 20px 22px; }
  .dash-row { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; align-items: stretch; }
  @media (max-width: 991.98px) { .dash-row { grid-template-columns: 1fr; } .dash-panel { height: auto; } }

  /* Vehicle status donut + legend */
  .donut-wrap { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
  .donut-canvas-box { width: 140px; height: 140px; position: relative; flex-shrink: 0; }
  .donut-center-label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .donut-center-label .pct { font-size: 22px; font-weight: 800; color: #0f172a; }
  .donut-center-label .lbl { font-size: 9.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: .04em; }
  .donut-legend { flex: 1; min-width: 160px; }
  .legend-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; font-size: 12.5px; }
  .legend-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 8px; }
  .legend-label { color: #475569; font-weight: 600; }
  .legend-value { font-weight: 800; color: #0f172a; }

  /* Vehicle type chips (same pattern as Vehicle Inventory) */
  .type-breakdown-chips{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px;}
  .type-chip{
    display:inline-flex;align-items:center;gap:8px;padding:7px 7px 7px 12px;border-radius:9px;
    background:#f8fafc;border:1.5px solid #e2e8f0;font-size:12.5px;font-weight:600;color:#334155;
  }
  .type-chip .count-badge{
    background:rgba(59,130,246,0.12);color:#3b82f6;font-size:11px;font-weight:800;
    padding:2px 8px;border-radius:20px;min-width:20px;text-align:center;
  }
  .breakdown-empty{font-size:13px;color:#94a3b8;}

  /* Recent maintenance activity feed */
  .activity-list { max-height: 320px; overflow-y: auto; }
  .activity-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f8fafc; }
  .activity-item:last-child { border-bottom: none; padding-bottom: 0; }
  .activity-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
  .activity-body { min-width: 0; flex: 1; }
  .activity-title { font-size: 13px; font-weight: 700; color: #0f172a; }
  .activity-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
  .activity-time { font-size: 11px; color: #94a3b8; white-space: nowrap; flex-shrink: 0; }
  .activity-cost { font-size: 12px; font-weight: 800; color: #0284c7; margin-top: 3px; }
  .activity-empty { text-align: center; padding: 30px 10px; color: #94a3b8; font-size: 13px; }

  /* Driver / license monitoring bars */
  .monitor-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
  .monitor-stat-row:last-child { border-bottom: none; }
  .monitor-stat-label { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: #334155; }
  .monitor-stat-dot { width: 9px; height: 9px; border-radius: 50%; }
  .monitor-stat-value { font-size: 16px; font-weight: 800; color: #0f172a; }

  /* Role breakdown chips (colors match System Users page badges) */
  .role-chip-list { display: flex; flex-direction: column; gap: 10px; }
  .role-chip-row { display: flex; align-items: center; justify-content: space-between; }
  .role-chip-name { font-size: 12.5px; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
  .role-super-administrator { background: rgba(220,38,38,0.1); color: #dc2626; }
  .role-administrator { background: rgba(217,119,6,0.12); color: #b45309; }
  .role-unit-administrator { background: rgba(59,130,246,0.12); color: #2563eb; }
  .role-station-administrator { background: rgba(8,145,178,0.12); color: #0e7490; }
  .role-viewer { background: rgba(100,116,139,0.12); color: #475569; }
  .role-chip-count { font-weight: 800; color: #0f172a; font-size: 13.5px; }

  /* System overview strip */
  .overview-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 22px; }
  @media (max-width: 767.98px) { .overview-strip { grid-template-columns: 1fr; } }
  .overview-tile { background: #fff; border: 1px solid #eef1f6; border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
  .overview-tile i { font-size: 20px; color: #94a3b8; width: 30px; text-align: center; }
  .overview-tile .ov-num { font-size: 19px; font-weight: 800; color: #0f172a; }
  .overview-tile .ov-lbl { font-size: 11.5px; color: #64748b; }

  .quick-links-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 4px; }
  @media (max-width: 767.98px) { .quick-links-grid { grid-template-columns: 1fr; } }
  .quick-link-card {
    background: #fff; border-radius: 14px; border: 1px solid #eef1f6; padding: 18px 20px;
    display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04); transition: transform 0.2s, box-shadow 0.2s;
  }
  .quick-link-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15,23,42,0.08); color: inherit; text-decoration: none; }
  .quick-link-card.disabled { opacity: .55; cursor: not-allowed; }
  .quick-link-card.disabled:hover { transform: none; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
  .quick-link-icon { width: 44px; height: 44px; border-radius: 11px; background: rgba(59,130,246,0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 17px; flex: none; }
  .quick-link-card .ql-title { font-weight: 700; font-size: 14px; color: #0f172a; }
  .quick-link-card .ql-sub { font-size: 12px; color: #94a3b8; margin-top: 1px; }

  .section-heading { font-size: 12.5px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; margin: 6px 0 12px; }

  /* Unserviceable 90+ days admin alert (VMIS Additional Updates item 3) */
  .uns-alert-panel { background: #fffbfb; border: 1px solid #fecaca; border-radius: 14px; margin-bottom: 22px; overflow: hidden; }
  .uns-alert-header { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #fee2e2; flex-wrap: wrap; }
  .uns-alert-header > i { font-size: 20px; color: #dc2626; flex-shrink: 0; }
  .uns-alert-header-text { flex: 1; min-width: 200px; }
  .uns-alert-title { font-weight: 800; color: #0f172a; font-size: 14px; }
  .uns-alert-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
  .uns-alert-body { padding: 6px 20px 14px; }
  .uns-alert-row { display: flex; align-items: center; gap: 14px; padding: 9px 0; border-top: 1px solid #fee2e2; }
  .uns-alert-row:first-child { border-top: none; }
  .uns-alert-plate { font-family: 'Courier New', monospace; font-weight: 800; font-size: 12.5px; background: #1e293b; color: #fff; padding: 4px 10px; border-radius: 6px; flex-shrink: 0; }
  .uns-alert-info { flex: 1; min-width: 0; }
  .uns-alert-name { font-weight: 700; font-size: 13px; color: #0f172a; }
  .uns-alert-loc { font-size: 11.5px; color: #64748b; margin-top: 2px; }
  .uns-alert-days { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0; white-space: nowrap; }

  /* Predictive Maintenance alert — digitized Technical Inspection Report checklist
     flagging parts needing attention now or trending toward failure. Same layout
     as the Unserviceable panel above, amber instead of red so the two read as
     related-but-distinct at a glance (this one is "watch/plan", not "already broken"). */
  .risk-alert-panel { background: #fffdf5; border: 1px solid #fde68a; border-radius: 14px; margin-bottom: 22px; overflow: hidden; }
  .risk-alert-header { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #fef3c7; flex-wrap: wrap; }
  .risk-alert-header > i { font-size: 20px; color: #d97706; flex-shrink: 0; }
  .risk-alert-header-text { flex: 1; min-width: 200px; }
  .risk-alert-title { font-weight: 800; color: #0f172a; font-size: 14px; }
  .risk-alert-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
  .risk-alert-body { padding: 6px 20px 14px; }
  .risk-alert-row { display: flex; align-items: center; gap: 14px; padding: 9px 0; border-top: 1px solid #fef3c7; }
  .risk-alert-row:first-child { border-top: none; }
  .risk-alert-plate { font-family: 'Courier New', monospace; font-weight: 800; font-size: 12.5px; background: #1e293b; color: #fff; padding: 4px 10px; border-radius: 6px; flex-shrink: 0; }
  .risk-alert-info { flex: 1; min-width: 0; }
  .risk-alert-name { font-weight: 700; font-size: 13px; color: #0f172a; }
  .risk-alert-loc { font-size: 11.5px; color: #64748b; margin-top: 2px; }
  .risk-alert-part { font-size: 11.5px; color: #92400e; margin-top: 2px; }
  .risk-alert-tag { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; background: #fffbeb; color: #d97706; border: 1px solid #fde68a; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0; white-space: nowrap; }

  /* Registration (OR/CR/Insurance) due/expired alert — same layout as the
     Unserviceable/Predictive-Maintenance panels above; amber header like the
     risk panel since it mixes already-overdue and due-soon vehicles rather
     than being purely critical, matching the Registration Due panel's theme
     on Vehicle Inventory. Each row's own badge still goes red when overdue. */
  .reg-due-panel { background: #fffdf5; border: 1px solid #fde68a; border-radius: 14px; margin-bottom: 22px; overflow: hidden; }
  .reg-due-header { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #fef3c7; flex-wrap: wrap; }
  .reg-due-header > i { font-size: 20px; color: #d97706; flex-shrink: 0; }
  .reg-due-header-text { flex: 1; min-width: 200px; }
  .reg-due-title { font-weight: 800; color: #0f172a; font-size: 14px; }
  .reg-due-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
  .reg-due-body { padding: 6px 20px 14px; }
  .reg-due-row { display: flex; align-items: center; gap: 14px; padding: 9px 0; border-top: 1px solid #fef3c7; }
  .reg-due-row:first-child { border-top: none; }
  .reg-due-plate { font-family: 'Courier New', monospace; font-weight: 800; font-size: 12.5px; background: #1e293b; color: #fff; padding: 4px 10px; border-radius: 6px; flex-shrink: 0; }
  .reg-due-info { flex: 1; min-width: 0; }
  .reg-due-name { font-weight: 700; font-size: 13px; color: #0f172a; }
  .reg-due-loc { font-size: 11.5px; color: #64748b; margin-top: 2px; }
  .reg-due-tag { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0; white-space: nowrap; }
  .reg-due-tag.is-overdue { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  .reg-due-tag.is-soon { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
</style>
@endsection

@section('nav-title', 'VMIS | Dashboard')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="welcome-banner">
            <div>
                <h4>Welcome back, {{ trim((auth()->user()->rank ? auth()->user()->rank.' ' : '').auth()->user()->firstname.' '.auth()->user()->lastname) }}</h4>
                <p>Here's what's happening with the PRO5 vehicles today.</p>
                <span class="badge-role">{{ $role }}</span>
                @if ($scopeLabel)
                    <span class="welcome-scope"><i class="fas fa-map-marker-alt"></i> Scoped to {{ $scopeLabel }}</span>
                @endif
            </div>
            <div class="welcome-meta">
                <div class="welcome-clock" id="liveClock">--:--:--</div>
                <div class="welcome-date" id="liveDate">{{ now()->format('l, F j, Y') }}</div>
            </div>
        </div>

        @if(($vehicleStats['unserviceable_alert'] ?? 0) > 0)
        <div class="uns-alert-panel">
            <div class="uns-alert-header">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="uns-alert-header-text">
                    <div class="uns-alert-title">{{ $vehicleStats['unserviceable_alert'] }} vehicle{{ $vehicleStats['unserviceable_alert'] === 1 ? '' : 's' }} Unserviceable for 90+ days</div>
                    <div class="uns-alert-sub">Needs a disposition decision — repair it, reclassify as BER, or otherwise resolve the status.</div>
                </div>
                <a href="{{ route('vehicles.index') }}" class="view-all-link">Review <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="uns-alert-body">
                @foreach ($unserviceableAlerts as $v)
                    <div class="uns-alert-row">
                        <span class="uns-alert-plate">{{ strtoupper($v->plate_number) }}</span>
                        <div class="uns-alert-info">
                            <div class="uns-alert-name">{{ $v->make }} {{ $v->model }}</div>
                            <div class="uns-alert-loc">{{ optional($v->unit)->unit_name ?? 'Unassigned unit' }}{{ $v->station ? ' · '.$v->station->station_name : '' }}</div>
                        </div>
                        <span class="uns-alert-days"><i class="fas fa-exclamation-triangle"></i> {{ $v->daysUnserviceable() }}d</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if ($registrationAlerts->isNotEmpty())
        <div class="reg-due-panel">
            <div class="reg-due-header">
                <i class="fas fa-calendar-times"></i>
                <div class="reg-due-header-text">
                    <div class="reg-due-title">{{ $registrationAlerts->count() }} vehicle{{ $registrationAlerts->count() === 1 ? '' : 's' }} due for OR/CR/Insurance registration</div>
                    <div class="reg-due-sub">Already expired or expiring within {{ \App\Models\Vehicle::REGISTRATION_DUE_SOON_DAYS }} days — upload the new year's documents via that vehicle's Docs button.</div>
                </div>
                <a href="{{ route('vehicles.index') }}" class="view-all-link">Review <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="reg-due-body">
                @foreach ($registrationAlerts as $v)
                    @php $daysLeft = $v->registrationDaysRemaining(); @endphp
                    <div class="reg-due-row">
                        <span class="reg-due-plate">{{ strtoupper($v->plate_number) }}</span>
                        <div class="reg-due-info">
                            <div class="reg-due-name">{{ $v->make }} {{ $v->model }}</div>
                            <div class="reg-due-loc">{{ optional($v->unit)->unit_name ?? 'Unassigned unit' }}{{ $v->station ? ' · '.$v->station->station_name : '' }} &middot; Valid until {{ $v->latestRegistration->expiry_date->format('M d, Y') }}</div>
                        </div>
                        @if ($daysLeft < 0)
                            <span class="reg-due-tag is-overdue"><i class="fas fa-exclamation-triangle"></i> {{ abs($daysLeft) }}d overdue</span>
                        @else
                            <span class="reg-due-tag is-soon"><i class="fas fa-clock"></i> Due in {{ $daysLeft }}d</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if ($atRiskComponents->isNotEmpty())
        <div class="risk-alert-panel">
            <div class="risk-alert-header">
                <i class="fas fa-diagnoses"></i>
                <div class="risk-alert-header-text">
                    <div class="risk-alert-title">{{ $atRiskComponents->count() }} part{{ $atRiskComponents->count() === 1 ? '' : 's' }} flagged by the digital Technical Inspection Report</div>
                    <div class="risk-alert-sub">Either currently flagged, or repeatedly marked Repairable — plan a repair or replacement before it fails.</div>
                </div>
            </div>
            <div class="risk-alert-body">
                @foreach ($atRiskComponents as $risk)
                    @php
                        $v = $atRiskVehicles->get($risk['vehicle_id']);
                    @endphp
                    <div class="risk-alert-row">
                        <span class="risk-alert-plate">{{ $v ? strtoupper($v->plate_number) : '—' }}</span>
                        <div class="risk-alert-info">
                            <div class="risk-alert-name">{{ $v ? trim($v->make.' '.$v->model) : 'Vehicle removed' }}</div>
                            <div class="risk-alert-loc">{{ $v ? (optional($v->unit)->unit_name ?? 'Unassigned unit').($v->station ? ' · '.$v->station->station_name : '') : '' }}</div>
                            <div class="risk-alert-part"><i class="fas fa-cog mr-1"></i>{{ $risk['system_category'] }} — {{ $risk['component_name'] }}</div>
                        </div>
                        <span class="risk-alert-tag"><i class="fas fa-{{ $risk['is_recurring'] ? 'redo' : 'exclamation-circle' }}"></i> {{ $risk['reason'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @php
            // Registration compliance %, lifted out of the card markup below so
            // it's computed once and the ring/metrics can both reference it.
            $regCompliantPct = $vehicleStats['total'] > 0
                ? (int) round((($vehicleStats['total'] - $vehicleStats['registration_overdue'] - $vehicleStats['registration_due_soon'] - $vehicleStats['registration_not_on_file']) / $vehicleStats['total']) * 100)
                : 0;
            // Same good/warn/critical banding for both rings — >=80% reads as
            // healthy, >=50% needs attention soon, below that is critical.
            $ringStatus = fn ($pct) => $pct >= 80 ? 'ring-good' : ($pct >= 50 ? 'ring-warn' : 'ring-crit');
        @endphp

        <!-- Fleet Overview — one strip, four figures, instead of four separate cards -->
        <div class="kpi-strip-panel">
            <div class="kpi-strip-item">
                <div class="kpi-strip-icon c-vehicles"><i class="fas fa-car-side"></i></div>
                <div><div class="kpi-strip-value">{{ $vehicleStats['total'] }}</div><div class="kpi-strip-label">Total Vehicles</div></div>
            </div>
            <div class="kpi-strip-item">
                <div class="kpi-strip-icon c-drivers"><i class="fas fa-id-card"></i></div>
                <div><div class="kpi-strip-value">{{ $driverStats['total'] }}</div><div class="kpi-strip-label">Total Personnel</div></div>
            </div>
            <div class="kpi-strip-item">
                <div class="kpi-strip-icon c-users"><i class="fas fa-users-cog"></i></div>
                <div><div class="kpi-strip-value">{{ $userStats['total'] }}</div><div class="kpi-strip-label">System Users &middot; {{ $userStats['online'] }} online</div></div>
            </div>
            <div class="kpi-strip-item">
                <div class="kpi-strip-icon c-records"><i class="fas fa-clipboard-list"></i></div>
                <div><div class="kpi-strip-value">{{ $maintenanceStats['total'] }}</div><div class="kpi-strip-label">Maintenance Records</div></div>
            </div>
        </div>

        <!-- Fleet Health — PMS compliance and Registration compliance as two
             rings side by side, each with its supporting figures beside it,
             so the headline % and its breakdown read as one picture instead
             of four flat boxes apiece. -->
        <div class="health-row">
            <div class="health-card">
                <div class="health-card-title"><i class="fas fa-tools"></i> PMS &amp; Maintenance Health</div>
                <div class="health-card-body">
                    <div class="health-ring-wrap">
                        <div class="health-ring {{ $ringStatus($vehicleStats['serviceable_pct']) }}" style="--pct: {{ $vehicleStats['serviceable_pct'] }};">
                            <div class="health-ring-inner">
                                <div class="health-ring-pct">{{ $vehicleStats['serviceable_pct'] }}%</div>
                                <div class="health-ring-lbl">Serviceable</div>
                            </div>
                        </div>
                    </div>
                    <div class="health-metrics">
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#d97706;"></span>Due Soon (PMS)</span>
                            <span class="health-metric-value">{{ $vehicleStats['due_soon'] }}</span>
                        </div>
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#dc2626;"></span>Overdue (PMS)</span>
                            <span class="health-metric-value">{{ $vehicleStats['overdue'] }}</span>
                        </div>
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#0284c7;"></span>Spent This Month</span>
                            <span class="health-metric-value">&#8369;{{ number_format($maintenanceStats['this_month_cost'] ?? 0, 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="health-card">
                <div class="health-card-title"><i class="fas fa-id-card"></i> Registration Health</div>
                <div class="health-card-body">
                    <div class="health-ring-wrap">
                        <div class="health-ring {{ $ringStatus($regCompliantPct) }}" style="--pct: {{ $regCompliantPct }};">
                            <div class="health-ring-inner">
                                <div class="health-ring-pct">{{ $regCompliantPct }}%</div>
                                <div class="health-ring-lbl">Compliant</div>
                            </div>
                        </div>
                    </div>
                    <div class="health-metrics">
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#d97706;"></span>Due Soon (Registration)</span>
                            <span class="health-metric-value">{{ $vehicleStats['registration_due_soon'] }}</span>
                        </div>
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#dc2626;"></span>Overdue (Registration)</span>
                            <span class="health-metric-value">{{ $vehicleStats['registration_overdue'] }}</span>
                        </div>
                        <div class="health-metric-row">
                            <span class="health-metric-label"><span class="health-metric-dot" style="background:#475569;"></span>No Registration on File</span>
                            <span class="health-metric-value">{{ $vehicleStats['registration_not_on_file'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fuel Monitoring — one headline metric (efficiency) with its three
             supporting figures beside it in the same card, amber-accented to
             read as its own module rather than a third repeat of the grid. -->
        <div class="fuel-hero-panel">
            <div class="fuel-hero-main">
                <div class="fuel-hero-icon"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="fuel-hero-value">{{ $fuelStats['avg_kml'] !== null ? number_format($fuelStats['avg_kml'], 2) : '—' }}<span style="font-size:14px;font-weight:700;color:#92400e;">&nbsp;km/L</span></div>
                    <div class="fuel-hero-label">Avg. Fleet Efficiency</div>
                </div>
            </div>
            <div class="fuel-mini-stats">
                <div class="fuel-mini-stat">
                    <div class="fuel-mini-icon c-logs"><i class="fas fa-gas-pump"></i></div>
                    <div><div class="fuel-mini-value">{{ $fuelStats['total_logs'] }}</div><div class="fuel-mini-label">Refuels Logged</div></div>
                </div>
                <div class="fuel-mini-stat">
                    <div class="fuel-mini-icon c-liters"><i class="fas fa-tint"></i></div>
                    <div><div class="fuel-mini-value">{{ number_format($fuelStats['liters_month'] ?? 0, 0) }} L</div><div class="fuel-mini-label">Liters This Month</div></div>
                </div>
                <div class="fuel-mini-stat">
                    <div class="fuel-mini-icon c-cost"><i class="fas fa-coins"></i></div>
                    <div><div class="fuel-mini-value">&#8369;{{ number_format($fuelStats['cost_month'] ?? 0, 0) }}</div><div class="fuel-mini-label">Fuel Cost This Month</div></div>
                </div>
            </div>
        </div>

        <div class="dash-panel" style="margin-bottom:22px;">
            <div class="dash-panel-header">
                <h6><i class="fas fa-gas-pump mr-2 text-primary"></i>Recent Refuels</h6>
                <a href="{{ route('fuel-logs.index') }}" class="view-all-link">View All <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="dash-panel-body">
                <div class="activity-list">
                    @forelse ($recentFuelLogs as $log)
                        @php $kml = $log->kmPerLiter(); @endphp
                        <div class="activity-item">
                            <div class="activity-icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-gas-pump"></i></div>
                            <div class="activity-body">
                                <div class="activity-title">{{ $log->vehicle ? strtoupper($log->vehicle->plate_number) : 'Vehicle removed' }} &middot; {{ number_format((float) $log->liters, 2) }} L</div>
                                <div class="activity-sub">{{ $log->vehicle ? trim($log->vehicle->make.' '.$log->vehicle->model) : '' }}{{ $log->driver ? ' · '.trim($log->driver->firstname.' '.$log->driver->lastname) : '' }}{{ $kml !== null ? ' · '.number_format($kml, 2).' km/L' : '' }}</div>
                                <div class="activity-cost">&#8369;{{ number_format((float) $log->total_cost, 2) }}</div>
                            </div>
                            <div class="activity-time">{{ $log->refuel_date->format('M d, Y') }}</div>
                        </div>
                    @empty
                        <div class="activity-empty"><i class="fas fa-gas-pump mb-2" style="font-size:22px;display:block;"></i>No refuels logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="dash-row">
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h6><i class="fas fa-chart-pie mr-2 text-primary"></i>Vehicle Status</h6>
                    <a href="{{ route('vehicles.index') }}" class="view-all-link">View Vehicles <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="dash-panel-body">
                    <div class="donut-wrap">
                        <div class="donut-canvas-box">
                            <canvas id="vehicleStatusChart"></canvas>
                            <div class="donut-center-label">
                                <div class="pct">{{ $vehicleStats['total'] }}</div>
                                <div class="lbl">Vehicles</div>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div class="legend-row"><span><span class="legend-dot" style="background:#16a34a;"></span><span class="legend-label">Serviceable</span></span><span class="legend-value">{{ $vehicleStats['serviceable'] }}</span></div>
                            <div class="legend-row"><span><span class="legend-dot" style="background:#d97706;"></span><span class="legend-label">Unserviceable</span></span><span class="legend-value">{{ $vehicleStats['unserviceable'] }}</span></div>
                            <div class="legend-row"><span><span class="legend-dot" style="background:#dc2626;"></span><span class="legend-label">BER</span></span><span class="legend-value">{{ $vehicleStats['ber'] }}</span></div>
                        </div>
                    </div>

                    <div class="section-heading" style="margin-top:20px;">Vehicles by Type</div>
                    <div class="type-breakdown-chips">
                        @forelse ($typeBreakdown as $type)
                            <div class="type-chip"><span>{{ $type->name }}</span><span class="count-badge">{{ $type->vehicles_count }}</span></div>
                        @empty
                            <span class="breakdown-empty">No vehicles on file yet.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h6><i class="fas fa-tools mr-2 text-primary"></i>Recent Maintenance Activity</h6>
                    <a href="{{ route('maintenance.index') }}" class="view-all-link">View All <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="dash-panel-body">
                    <div class="activity-list">
                        @forelse ($recentMaintenance as $record)
                            @php
                                $typeLabel = \App\Models\MaintenanceRecord::TYPES[$record->maintenance_type] ?? $record->maintenance_type;
                                $iconMap = ['PMS' => 'fa-tools', 'REPAIR' => 'fa-wrench', 'OIL_CHANGE' => 'fa-oil-can', 'TIRE_CHANGE' => 'fa-circle-notch', 'BATTERY' => 'fa-car-battery', 'EMERGENCY' => 'fa-exclamation-circle', 'OTHER' => 'fa-ellipsis-h'];
                                $icon = $iconMap[$record->maintenance_type] ?? 'fa-tools';
                                $iconColorMap = [
                                    'PMS' => ['#eff6ff', '#3b82f6'], 'REPAIR' => ['#fffbeb', '#d97706'],
                                    'OIL_CHANGE' => ['#f0fdf4', '#16a34a'], 'TIRE_CHANGE' => ['#ecfeff', '#0891b2'],
                                    'BATTERY' => ['#f8fafc', '#64748b'], 'EMERGENCY' => ['#fef2f2', '#dc2626'],
                                    'OTHER' => ['#f8fafc', '#94a3b8'],
                                ];
                                [$iconBg, $iconFg] = $iconColorMap[$record->maintenance_type] ?? ['#f8fafc', '#64748b'];
                            @endphp
                            <div class="activity-item">
                                <div class="activity-icon" style="background:{{ $iconBg }};color:{{ $iconFg }};"><i class="fas {{ $icon }}"></i></div>
                                <div class="activity-body">
                                    <div class="activity-title">{{ $record->vehicle ? strtoupper($record->vehicle->plate_number) : 'Vehicle removed' }} — {{ $typeLabel }}</div>
                                    <div class="activity-sub">{{ $record->vehicle ? trim($record->vehicle->make.' '.$record->vehicle->model) : '' }} &middot; logged by {{ optional($record->recorder)->fullname ?? 'System' }}</div>
                                    @if ($record->cost)
                                        <div class="activity-cost">&#8369;{{ number_format($record->cost, 2) }}</div>
                                    @endif
                                </div>
                                <div class="activity-time">{{ $record->created_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="activity-empty"><i class="fas fa-clipboard-list mb-2" style="font-size:22px;display:block;"></i>No maintenance activity logged yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="dash-row">
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h6><i class="fas fa-id-card mr-2 text-primary"></i>Driver &amp; License Monitoring</h6>
                    <a href="{{ route('drivers.index') }}" class="view-all-link">View All <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="dash-panel-body">
                    <div class="monitor-stat-row">
                        <span class="monitor-stat-label"><span class="monitor-stat-dot" style="background:#16a34a;"></span>Active Drivers</span>
                        <span class="monitor-stat-value">{{ $driverStats['active'] }}</span>
                    </div>
                    <div class="monitor-stat-row">
                        <span class="monitor-stat-label"><span class="monitor-stat-dot" style="background:#d97706;"></span>Licenses Expiring (30 days)</span>
                        <span class="monitor-stat-value">{{ $driverStats['expiring'] }}</span>
                    </div>
                    <div class="monitor-stat-row">
                        <span class="monitor-stat-label"><span class="monitor-stat-dot" style="background:#dc2626;"></span>Licenses Expired</span>
                        <span class="monitor-stat-value">{{ $driverStats['expired'] }}</span>
                    </div>
                    <div class="monitor-stat-row">
                        <span class="monitor-stat-label"><span class="monitor-stat-dot" style="background:#94a3b8;"></span>Total on File</span>
                        <span class="monitor-stat-value">{{ $driverStats['total'] }}</span>
                    </div>
                </div>
            </div>

            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h6><i class="fas fa-users-cog mr-2 text-primary"></i>System Users by Role</h6>
                    <a href="{{ route('users.index') }}" class="view-all-link">Manage <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="dash-panel-body">
                    <div class="role-chip-list">
                        @forelse ($userRoleBreakdown as $accountType => $count)
                            <div class="role-chip-row">
                                <span class="role-chip-name role-{{ \Illuminate\Support\Str::slug($accountType) }}">{{ $accountType }}</span>
                                <span class="role-chip-count">{{ $count }}</span>
                            </div>
                        @empty
                            <span class="breakdown-empty">No system users on file yet.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if ($systemOverview)
            <div class="section-heading">System Overview</div>
            <div class="overview-strip">
                <div class="overview-tile"><i class="fas fa-sitemap"></i><div><div class="ov-num">{{ $systemOverview['units'] }}</div><div class="ov-lbl">Units</div></div></div>
                <div class="overview-tile"><i class="fas fa-building"></i><div><div class="ov-num">{{ $systemOverview['stations'] }}</div><div class="ov-lbl">Stations</div></div></div>
                <div class="overview-tile"><i class="fas fa-tags"></i><div><div class="ov-num">{{ $systemOverview['vehicle_types'] }}</div><div class="ov-lbl">Vehicle Types</div></div></div>
            </div>
        @endif

        <div class="section-heading">Quick Links</div>
        <div class="quick-links-grid">
            <a href="{{ route('vehicles.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-car-side"></i></div>
                <div><div class="ql-title">Vehicle Inventory</div><div class="ql-sub">View and manage your vehicles</div></div>
            </a>
            <a href="{{ route('maintenance.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-tools"></i></div>
                <div><div class="ql-title">Maintenance &amp; PMS</div><div class="ql-sub">Log service, track schedules</div></div>
            </a>
            <a href="{{ route('drivers.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-id-card"></i></div>
                <div><div class="ql-title">Driver Management</div><div class="ql-sub">Manage personnel profiles</div></div>
            </a>
            <a href="{{ route('scan.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-qrcode"></i></div>
                <div><div class="ql-title">Scan QR Code</div><div class="ql-sub">Look up a vehicle instantly</div></div>
            </a>
            <a href="{{ route('fuel-logs.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-gas-pump"></i></div>
                <div><div class="ql-title">Fuel Monitoring</div><div class="ql-sub">Log refuels, track efficiency</div></div>
            </a>
            <a href="{{ route('vehicle-types.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-tags"></i></div>
                <div><div class="ql-title">Vehicle Types</div><div class="ql-sub">Manage vehicle categories</div></div>
            </a>
            <a href="{{ route('users.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-users-cog"></i></div>
                <div><div class="ql-title">System Users</div><div class="ql-sub">Accounts and access</div></div>
            </a>
        </div>

    </div>
</section>
@endsection

@section('script')
<script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if (session('success'))
        toastr.success(@json(session('success')));
    @endif
    @if (session('error'))
        toastr.error(@json(session('error')));
    @endif

    // Live clock — purely cosmetic, keeps the dashboard feeling "alive" without any
    // extra requests; the date underneath is server-rendered so it's correct even
    // before this fires.
    function tickClock() {
        var el = document.getElementById('liveClock');
        if (!el) return;
        var now = new Date();
        var pad = function (n) { return n.toString().padStart(2, '0'); };
        // 12-hour format with AM/PM — easier for most users to read at a
        // glance than 24-hour time.
        var hours = now.getHours();
        var meridiem = hours >= 12 ? 'PM' : 'AM';
        var hours12 = hours % 12;
        if (hours12 === 0) hours12 = 12;
        el.textContent = pad(hours12) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + meridiem;
    }
    tickClock();
    setInterval(tickClock, 1000);

    var ctx = document.getElementById('vehicleStatusChart');
    if (ctx && window.Chart) {
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Serviceable', 'Unserviceable', 'BER'],
                datasets: [{
                    data: [{{ $vehicleStats['serviceable'] }}, {{ $vehicleStats['unserviceable'] }}, {{ $vehicleStats['ber'] }}],
                    backgroundColor: ['#16a34a', '#d97706', '#dc2626'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 72,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var label = data.labels[item.index] || '';
                            var value = data.datasets[0].data[item.index];
                            return label + ': ' + value;
                        }
                    }
                }
            }
        });
    }
});
</script>
@endsection
