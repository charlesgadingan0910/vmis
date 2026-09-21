@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | {{ $vehicle->plate_number }}</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  :root{ --vmis-navy-900:#0f172a; --vmis-navy-800:#1e293b; --vmis-blue:#3b82f6; --vmis-blue-dark:#1d4ed8; }

  .scan-result-wrap{max-width:560px;margin:0 auto;padding-bottom:calc(84px + env(safe-area-inset-bottom, 0px));}

  /* ---------- ID Card Hero ---------- */
  .id-card{
    background:linear-gradient(150deg, var(--vmis-navy-800) 0%, var(--vmis-navy-900) 100%);
    border-radius:22px; padding:24px 22px 22px; color:#fff; position:relative; overflow:hidden;
    margin-bottom:16px; box-shadow:0 1px 3px rgba(15,23,42,0.06), 0 20px 40px rgba(15,23,42,0.18);
  }
  .id-card::after{content:'';position:absolute;top:-35%;right:-15%;width:65%;height:65%;background:radial-gradient(circle, rgba(59,130,246,0.25), transparent 65%);}
  .id-card::before{
    content:''; position:absolute; inset:0;
    background-image:linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size:18px 18px;
  }
  .id-card-top{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
  .id-card-label{display:flex;align-items:center;gap:7px;font-size:10.5px;font-weight:700;letter-spacing:.08em;color:#93c5fd;text-transform:uppercase;}
  .id-card-label svg{width:14px;height:14px;}
  .verified-chip{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:#4ade80;background:rgba(34,197,94,0.14);border:1px solid rgba(34,197,94,0.3);padding:4px 9px;border-radius:20px;}
  .verified-chip svg{width:11px;height:11px;}

  .plate-badge-lg{position:relative;z-index:1;font-family:'Courier New',monospace;font-weight:800;font-size:26px;letter-spacing:.07em;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.16);display:inline-block;padding:9px 18px;border-radius:11px;}
  .veh-name{position:relative;z-index:1;font-size:19px;font-weight:700;margin-top:13px;}
  .veh-sub{position:relative;z-index:1;color:#94a3b8;font-size:12.5px;margin-top:3px;}
  .status-pill-lg{position:relative;z-index:1;display:inline-flex;align-items:center;gap:7px;font-size:11.5px;font-weight:700;padding:6px 13px;border-radius:20px;margin-top:13px;text-transform:capitalize;}
  .status-pill-lg.SERVICEABLE{background:rgba(34,197,94,0.16);color:#4ade80;}
  .status-pill-lg.UNSERVICEABLE{background:rgba(245,158,11,0.18);color:#fbbf24;}
  .status-pill-lg.BER{background:rgba(239,68,68,0.18);color:#f87171;}

  /* ---------- Info sections ---------- */
  .info-section{background:#fff;border-radius:16px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);margin-bottom:14px;overflow:hidden;}
  .info-section-head{padding:15px 18px;border-bottom:1px solid #f1f5f9;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;display:flex;align-items:center;gap:9px;}
  .info-section-head .ic{width:26px;height:26px;border-radius:8px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;flex:none;}
  .info-section-head .ic svg{width:14px;height:14px;color:var(--vmis-blue);}
  .info-grid{padding:17px 18px;display:grid;grid-template-columns:1fr 1fr;gap:16px 18px;}
  @media (max-width:420px){.info-grid{grid-template-columns:1fr;}}
  .info-field label{font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-weight:700;display:block;margin-bottom:4px;}
  .info-field .val{font-size:14.5px;font-weight:700;color:#0f172a;}

  .driver-block{display:flex;align-items:center;gap:13px;padding:17px 18px;}
  .driver-block .av{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--vmis-navy-800),var(--vmis-navy-900));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex:none;}
  .driver-block .nm{font-weight:700;color:#0f172a;font-size:14.5px;}
  .driver-block .sub{font-size:12px;color:#94a3b8;margin-top:1px;}
  .empty-note{padding:18px;color:#94a3b8;font-size:13.5px;font-style:italic;text-align:center;}

  .doc-row{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-top:1px solid #f8fafc;}
  .doc-row:first-child{border-top:none;}
  .doc-year{font-weight:700;color:#0f172a;font-size:13.5px;}
  .doc-meta{font-size:11.5px;color:#94a3b8;margin-top:2px;}
  .doc-links{display:flex;gap:8px;}
  .doc-links a{font-size:11.5px;font-weight:700;padding:6px 12px;border-radius:8px;background:#eff6ff;color:var(--vmis-blue);text-decoration:none;}
  .doc-links a:hover{background:#dbeafe;}

  .scan-footer{font-size:11px;color:#94a3b8;text-align:center;padding:6px 12px 4px;line-height:1.6;}

  /* ---------- Sticky native-app-style bottom bar ---------- */
  .scan-bottom-bar{
    position:fixed; left:0; right:0; bottom:0; z-index:60;
    background:rgba(255,255,255,0.92); backdrop-filter:blur(12px) saturate(160%);
    border-top:1px solid #eef1f6; padding:12px 18px calc(12px + env(safe-area-inset-bottom, 0px));
    display:flex; justify-content:center;
  }
  .scan-bottom-bar a{
    width:100%; max-width:520px; display:flex; align-items:center; justify-content:center; gap:9px;
    background:linear-gradient(135deg, var(--vmis-blue), var(--vmis-blue-dark)); color:#fff; text-decoration:none;
    font-weight:700; font-size:14px; padding:13px; border-radius:12px; box-shadow:0 6px 16px rgba(59,130,246,0.3);
  }
  .scan-bottom-bar a svg{width:17px;height:17px;}

  @media (min-width: 992px){
    /* Desktop viewers of this same page get a normal inline button instead of a fixed bar */
    .scan-bottom-bar{ position:static; background:none; backdrop-filter:none; border-top:none; padding:4px 0 0; }
    .scan-bottom-bar a{ box-shadow:0 2px 6px rgba(59,130,246,0.25); }
  }
</style>
@endsection

@section('nav-title', 'VMIS | Vehicle Profile')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="scan-result-wrap">

            <div class="id-card">
                <div class="id-card-top">
                    <div class="id-card-label">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3L20 6V11C20 16 16.5 19.8 12 21C7.5 19.8 4 16 4 11V6L12 3Z" stroke="#93c5fd" stroke-width="1.5"/></svg>
                        PRO5 Fleet Registry
                    </div>
                    <div class="verified-chip">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 12L10 18L20 6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Verified
                    </div>
                </div>
                <span class="plate-badge-lg">{{ strtoupper($vehicle->plate_number) }}</span>
                <div class="veh-name">{{ $vehicle->make }} {{ $vehicle->model }}</div>
                <div class="veh-sub">{{ $vehicle->year_model ?? 'N/A' }} &middot; {{ $vehicle->color ?? 'Unspecified color' }} &middot; {{ $vehicle->type->name ?? 'Unspecified type' }}</div>
                <span class="status-pill-lg {{ $vehicle->status }}"><i class="fas fa-circle" style="font-size:6px;"></i> {{ strtolower($vehicle->status) }}</span>
            </div>

            <div class="info-section">
                <div class="info-section-head">
                    <div class="ic"><svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 10H20.5" stroke="currentColor" stroke-width="1.6"/></svg></div>
                    Identification
                </div>
                <div class="info-grid">
                    <div class="info-field"><label>Engine Number</label><div class="val">{{ $vehicle->engine_number ?? '—' }}</div></div>
                    <div class="info-field"><label>Chassis Number</label><div class="val">{{ $vehicle->chassis_number ?? '—' }}</div></div>
                    <div class="info-field"><label>Odometer</label><div class="val">{{ number_format($vehicle->odometer_km) }} km</div></div>
                    <div class="info-field"><label>Next PMS</label><div class="val">{{ optional($vehicle->next_pms_date)->format('M d, Y') ?? 'Not scheduled' }}</div></div>
                </div>
            </div>

            <div class="info-section">
                <div class="info-section-head">
                    <div class="ic"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6"/><path d="M5 20C5 16.1 8.1 13.5 12 13.5C15.9 13.5 19 16.1 19 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div>
                    Assigned Driver
                </div>
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
                <div class="info-section-head">
                    <div class="ic"><svg viewBox="0 0 24 24" fill="none"><path d="M3 7C3 5.9 3.9 5 5 5H9L11 7.5H19C20.1 7.5 21 8.4 21 9.5V17C21 18.1 20.1 19 19 19H5C3.9 19 3 18.1 3 17V7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
                    Registration Documents
                </div>
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

<div class="scan-bottom-bar">
    <a href="{{ route('scan.index') }}">
        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="15" y="15.5" width="5.5" height="5.5" fill="#fff"/></svg>
        Scan Another Vehicle
    </a>
</div>
@endsection
