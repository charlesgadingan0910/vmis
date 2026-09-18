@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | {{ $vehicle->plate_number }}</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .scan-result-wrap{max-width:640px;margin:0 auto;}
  .vehicle-hero{background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:16px;padding:26px 24px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden;}
  .vehicle-hero::after{content:'';position:absolute;top:-30%;right:-20%;width:60%;height:60%;background:radial-gradient(circle,rgba(59,130,246,0.25),transparent 65%);}
  .vehicle-hero .plate-badge-lg{font-family:'Courier New',monospace;font-weight:800;font-size:24px;letter-spacing:.06em;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);display:inline-block;padding:8px 16px;border-radius:10px;position:relative;z-index:1;}
  .vehicle-hero .veh-name{font-size:19px;font-weight:700;margin-top:12px;position:relative;z-index:1;}
  .vehicle-hero .veh-sub{color:#94a3b8;font-size:13px;position:relative;z-index:1;}
  .vehicle-hero .status-pill-lg{position:relative;z-index:1;display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;padding:6px 14px;border-radius:20px;margin-top:14px;text-transform:capitalize;}
  .status-pill-lg.SERVICEABLE{background:rgba(34,197,94,0.16);color:#4ade80;}
  .status-pill-lg.UNSERVICEABLE{background:rgba(245,158,11,0.18);color:#fbbf24;}
  .status-pill-lg.BER{background:rgba(239,68,68,0.18);color:#f87171;}

  .info-section{background:#fff;border-radius:14px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);margin-bottom:14px;overflow:hidden;}
  .info-section-head{padding:14px 18px;border-bottom:1px solid #f1f5f9;font-size:12.5px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.03em;display:flex;align-items:center;gap:8px;}
  .info-section-head i{color:#3b82f6;}
  .info-grid{padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;}
  @media (max-width:480px){.info-grid{grid-template-columns:1fr;}}
  .info-field label{font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-weight:700;display:block;margin-bottom:3px;}
  .info-field .val{font-size:14px;font-weight:600;color:#0f172a;}

  .driver-block{display:flex;align-items:center;gap:12px;padding:16px 18px;}
  .driver-block .av{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1e293b,#334155);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:none;}
  .driver-block .nm{font-weight:700;color:#0f172a;font-size:14.5px;}
  .driver-block .sub{font-size:12px;color:#94a3b8;}
  .empty-note{padding:16px 18px;color:#94a3b8;font-size:13.5px;font-style:italic;}

  .doc-row{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid #f8fafc;}
  .doc-row:first-child{border-top:none;}
  .doc-year{font-weight:700;color:#0f172a;font-size:13.5px;}
  .doc-meta{font-size:11.5px;color:#94a3b8;}
  .doc-links a{font-size:12.5px;font-weight:600;margin-left:10px;color:#3b82f6;text-decoration:none;}
  .doc-links a:hover{text-decoration:underline;}

  .scan-footer{font-size:11.5px;color:#94a3b8;text-align:center;padding:14px 0 6px;}
</style>
@endsection

@section('nav-title', 'VMIS | Vehicle Profile')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="scan-result-wrap">

            <div class="vehicle-hero">
                <span class="plate-badge-lg">{{ strtoupper($vehicle->plate_number) }}</span>
                <div class="veh-name">{{ $vehicle->make }} {{ $vehicle->model }}</div>
                <div class="veh-sub">{{ $vehicle->year_model ?? 'N/A' }} &middot; {{ $vehicle->color ?? 'Unspecified color' }} &middot; {{ $vehicle->type->name ?? 'Unspecified type' }}</div>
                <span class="status-pill-lg {{ $vehicle->status }}"><i class="fas fa-circle" style="font-size:6px;"></i> {{ strtolower($vehicle->status) }}</span>
            </div>

            <div class="info-section">
                <div class="info-section-head"><i class="fas fa-id-card"></i> Identification</div>
                <div class="info-grid">
                    <div class="info-field"><label>Engine Number</label><div class="val">{{ $vehicle->engine_number ?? '—' }}</div></div>
                    <div class="info-field"><label>Chassis Number</label><div class="val">{{ $vehicle->chassis_number ?? '—' }}</div></div>
                    <div class="info-field"><label>Odometer</label><div class="val">{{ number_format($vehicle->odometer_km) }} km</div></div>
                    <div class="info-field"><label>Next PMS</label><div class="val">{{ optional($vehicle->next_pms_date)->format('M d, Y') ?? 'Not scheduled' }}</div></div>
                </div>
            </div>

            <div class="info-section">
                <div class="info-section-head"><i class="fas fa-user-shield"></i> Assigned Driver</div>
                @if ($vehicle->driver)
                <div class="driver-block">
                    <div class="av">{{ strtoupper(substr($vehicle->driver->firstname,0,1).substr($vehicle->driver->lastname,0,1)) }}</div>
                    <div>
                        <div class="nm">{{ $vehicle->driver->rank }} {{ $vehicle->driver->firstname }} {{ $vehicle->driver->lastname }}</div>
                        <div class="sub">{{ $vehicle->driver->contact_number ?? 'No contact number on file' }}</div>
                    </div>
                </div>
                @else
                <div class="empty-note">No driver currently assigned.</div>
                @endif
            </div>

            <div class="info-section">
                <div class="info-section-head"><i class="fas fa-folder-open"></i> Registration Documents</div>
                @forelse ($vehicle->registrations as $reg)
                <div class="doc-row">
                    <div>
                        <div class="doc-year">{{ $reg->registration_year }}</div>
                        <div class="doc-meta">Uploaded {{ $reg->created_at->format('M d, Y') }} by {{ optional($reg->uploader)->fullname ?? 'Unknown' }}</div>
                    </div>
                    <div class="doc-links">
                        <a href="{{ route('vehicles.document', ['path' => $reg->or_file_path]) }}" target="_blank">OR</a>
                        <a href="{{ route('vehicles.document', ['path' => $reg->cr_file_path]) }}" target="_blank">CR</a>
                    </div>
                </div>
                @empty
                <div class="empty-note">No registration documents uploaded yet.</div>
                @endforelse
            </div>

            <div class="scan-footer">
                Registry record encoded by {{ optional($vehicle->encoder)->fullname ?? 'Unknown User' }} on {{ $vehicle->created_at->format('M d, Y') }}
            </div>

        </div>
    </div>
</section>
@endsection
