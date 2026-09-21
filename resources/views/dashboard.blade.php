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
    border-radius:14px; padding:24px 28px; color:#fff; margin-bottom:24px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
  }
  .welcome-banner h4{margin:0; font-weight:700; letter-spacing:-0.3px;}
  .welcome-banner p{margin:4px 0 0; color:#94a3b8; font-size:13.5px;}
  .welcome-banner .badge-role{
    background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3);
    font-size:11px; font-weight:700; letter-spacing:0.05em; padding:5px 10px; border-radius:20px;
  }

  /* Same stat-card pattern already used on Vehicle Inventory & Driver
     Management, reused here for one consistent visual language app-wide —
     including the same 2-column mobile stack that already works well there. */
  .fleet-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
  @media (max-width: 991px) { .fleet-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  .stat-card-modern { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; gap: 16px; transition: transform 0.2s; }
  .stat-card-modern:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
  .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex: none; }

  .stat-card-modern.vehicles .stat-icon-wrapper { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
  .stat-card-modern.serviceable .stat-icon-wrapper { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
  .stat-card-modern.personnel .stat-icon-wrapper { background: rgba(139, 92, 246, 0.12); color: #7c3aed; }
  .stat-card-modern.expiring .stat-icon-wrapper { background: rgba(245, 158, 11, 0.14); color: #d97706; }

  .stat-num-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.1; }
  .stat-label-title { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 3px; }

  .quick-links-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; }
  @media (max-width: 767.98px) { .quick-links-grid { grid-template-columns: 1fr; } }
  .quick-link-card {
    background: #fff; border-radius: 14px; border: 1px solid #eef1f6; padding: 18px 20px;
    display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04); transition: transform 0.2s, box-shadow 0.2s;
  }
  .quick-link-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15,23,42,0.08); color: inherit; text-decoration: none; }
  .quick-link-icon { width: 44px; height: 44px; border-radius: 11px; background: rgba(59,130,246,0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 17px; flex: none; }
  .quick-link-card .ql-title { font-weight: 700; font-size: 14px; color: #0f172a; }
  .quick-link-card .ql-sub { font-size: 12px; color: #94a3b8; margin-top: 1px; }
</style>
@endsection

@section('nav-title', 'VMIS | Dashboard')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="welcome-banner">
            <div>
                <h4>Welcome back, {{ trim((auth()->user()->rank ? auth()->user()->rank.' ' : '').auth()->user()->firstname.' '.auth()->user()->lastname) }}</h4>
                <p>Here's what's happening with the PRO5 fleet today.</p>
            </div>
            <span class="badge-role">{{ strtoupper(auth()->user()->account_type) }}</span>
        </div>

        <div class="fleet-stats-grid">
            <div class="stat-card-modern vehicles">
                <div class="stat-icon-wrapper"><i class="fas fa-car-side"></i></div>
                <div><div class="stat-num-value">{{ $stats['total_vehicles'] }}</div><div class="stat-label-title">Total Vehicles</div></div>
            </div>
            <div class="stat-card-modern serviceable">
                <div class="stat-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-num-value">{{ $stats['serviceable_vehicles'] }}</div><div class="stat-label-title">Serviceable</div></div>
            </div>
            <div class="stat-card-modern personnel">
                <div class="stat-icon-wrapper"><i class="fas fa-id-card"></i></div>
                <div><div class="stat-num-value">{{ $stats['total_drivers'] }}</div><div class="stat-label-title">Total Personnel</div></div>
            </div>
            <div class="stat-card-modern expiring">
                <div class="stat-icon-wrapper"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-num-value">{{ $stats['expiring_licenses'] }}</div><div class="stat-label-title">Expiring Licenses (30d)</div></div>
            </div>
        </div>

        <div class="quick-links-grid">
            <a href="{{ route('vehicles.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-car-side"></i></div>
                <div><div class="ql-title">Vehicle Inventory</div><div class="ql-sub">View and manage the fleet</div></div>
            </a>
            <a href="{{ route('scan.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-qrcode"></i></div>
                <div><div class="ql-title">Scan QR Code</div><div class="ql-sub">Look up a vehicle instantly</div></div>
            </a>
            <a href="{{ route('drivers.index') }}" class="quick-link-card">
                <div class="quick-link-icon"><i class="fas fa-id-card"></i></div>
                <div><div class="ql-title">Driver Management</div><div class="ql-sub">Manage personnel profiles</div></div>
            </a>
        </div>

    </div>
</section>
@endsection

@section('script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    @if (session('success'))
      toastr.success(@json(session('success')));
    @endif
    @if (session('error'))
      toastr.error(@json(session('error')));
    @endif
  });
</script>
@endsection
