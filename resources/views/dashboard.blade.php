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

  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 575px) { .fleet-stats-grid { grid-template-columns: 1fr; } }

  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03); display: flex; align-items: center; gap: 16px; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .stat-card-modern.c-vehicles .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.c-drivers .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #7c3aed; }
  .stat-card-modern.c-users .stat-icon-wrapper { background: rgba(20, 184, 166, 0.12); color: #0d9488; }
  .stat-card-modern.c-records .stat-icon-wrapper { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
  .stat-card-modern.c-serviceable .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.c-soon .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }
  .stat-card-modern.c-overdue .stat-icon-wrapper { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
  .stat-card-modern.c-cost .stat-icon-wrapper { background: rgba(14, 165, 233, 0.12); color: #0284c7; }
  .stat-num-value { font-size: 23px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 11.5px; font-weight: 600; color: #64748b; margin-top: 3px; }
  .stat-sub-note { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }

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

        <div class="fleet-stats-grid">
            <div class="stat-card-modern c-vehicles">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div><div class="stat-num-value">{{ $vehicleStats['total'] }}</div><div class="stat-label-title">Total Vehicles</div></div>
            </div>
            <div class="stat-card-modern c-drivers">
                <div class="stat-icon-wrapper"><i class="fas fa-id-card"></i></div>
                <div><div class="stat-num-value">{{ $driverStats['total'] }}</div><div class="stat-label-title">Total Personnel</div></div>
            </div>
            <div class="stat-card-modern c-users">
                <div class="stat-icon-wrapper"><i class="fas fa-users-cog"></i></div>
                <div><div class="stat-num-value">{{ $userStats['total'] }}</div><div class="stat-label-title">System Users</div><div class="stat-sub-note">{{ $userStats['online'] }} online now</div></div>
            </div>
            <div class="stat-card-modern c-records">
                <div class="stat-icon-wrapper"><i class="fas fa-clipboard-list"></i></div>
                <div><div class="stat-num-value">{{ $maintenanceStats['total'] }}</div><div class="stat-label-title">Maintenance Records</div></div>
            </div>
        </div>

        <div class="fleet-stats-grid">
            <div class="stat-card-modern c-serviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-num-value">{{ $vehicleStats['serviceable_pct'] }}%</div><div class="stat-label-title">Vehicles Serviceable</div></div>
            </div>
            <div class="stat-card-modern c-soon">
                <div class="stat-icon-wrapper"><i class="fas fa-clock"></i></div>
                <div><div class="stat-num-value">{{ $vehicleStats['due_soon'] }}</div><div class="stat-label-title">Due Soon (PMS)</div></div>
            </div>
            <div class="stat-card-modern c-overdue">
                <div class="stat-icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-num-value">{{ $vehicleStats['overdue'] }}</div><div class="stat-label-title">Overdue (PMS)</div></div>
            </div>
            <div class="stat-card-modern c-cost">
                <div class="stat-icon-wrapper"><i class="fas fa-money-bill-wave"></i></div>
                <div><div class="stat-num-value">&#8369;{{ number_format($maintenanceStats['this_month_cost'] ?? 0, 0) }}</div><div class="stat-label-title">Spent This Month</div></div>
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
