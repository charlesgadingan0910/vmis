@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | Vehicle Not Found</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  :root{ --vmis-navy-900:#0f172a; --vmis-navy-800:#1e293b; --vmis-blue:#3b82f6; --vmis-blue-dark:#1d4ed8; }

  .nf-wrap{max-width:440px;margin:40px auto 0;text-align:center;padding:0 4px;}
  .nf-icon{
    width:76px;height:76px;border-radius:22px;margin:0 auto 22px;
    background:linear-gradient(150deg, var(--vmis-navy-800), var(--vmis-navy-900));
    display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;
    box-shadow:0 10px 26px rgba(15,23,42,0.2);
  }
  .nf-icon::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 70% 20%, rgba(239,68,68,0.22), transparent 65%);}
  .nf-icon svg{width:32px;height:32px;position:relative;z-index:1;}

  .nf-wrap h4{font-weight:800;color:#0f172a;margin-bottom:8px;letter-spacing:-.2px;}
  .nf-wrap p{color:#64748b;font-size:14px;line-height:1.6;margin-bottom:4px;}
  .nf-identifier{
    display:inline-block;font-family:'Courier New',monospace;font-weight:700;font-size:13px;
    background:#f1f5f9;color:#334155;padding:5px 12px;border-radius:8px;margin:10px 0 26px;letter-spacing:.03em;
  }

  .nf-actions{display:flex;flex-direction:column;gap:10px;}
  .nf-btn-primary{
    display:flex;align-items:center;justify-content:center;gap:9px;
    background:linear-gradient(135deg, var(--vmis-blue), var(--vmis-blue-dark)); color:#fff; text-decoration:none;
    font-weight:700; font-size:14px; padding:13px; border-radius:12px; box-shadow:0 6px 16px rgba(59,130,246,0.28);
  }
  .nf-btn-secondary{
    display:flex;align-items:center;justify-content:center;gap:9px;
    background:#fff; border:1.5px solid #e2e8f0; color:#334155; text-decoration:none;
    font-weight:700; font-size:14px; padding:12px; border-radius:12px;
  }
  .nf-actions svg{width:16px;height:16px;}

  .nf-hint{margin-top:26px;font-size:12px;color:#94a3b8;line-height:1.6;}
</style>
@endsection

@section('nav-title', 'VMIS | Not Found')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="nf-wrap">

            <div class="nf-icon">
                <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.3" stroke="#f87171" stroke-width="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.3" stroke="#f87171" stroke-width="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.3" stroke="#f87171" stroke-width="1.7"/><path d="M15 15.5L20.5 21M20.5 15.5L15 21" stroke="#f87171" stroke-width="1.8" stroke-linecap="round"/></svg>
            </div>

            <h4>Vehicle Not Found</h4>
            <p>
                @if ($type === 'plate')
                    No vehicle is registered with the plate number
                @else
                    This QR code isn't linked to any vehicle
                @endif
            </p>
            <span class="nf-identifier">{{ $identifier }}</span>

            <div class="nf-actions">
                <a href="{{ route('scan.index') }}" class="nf-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.3" stroke="#fff" stroke-width="1.7"/><rect x="15" y="15.5" width="5.5" height="5.5" fill="#fff"/></svg>
                    Try Scanning Again
                </a>
                <a href="{{ route('vehicles.index') }}" class="nf-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 12L10 18L20 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;"/><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 10H20.5" stroke="currentColor" stroke-width="1.6"/></svg>
                    Browse Vehicle Inventory
                </a>
            </div>

            <p class="nf-hint">
                @if ($type === 'plate')
                    Double-check the plate number for typos, or try scanning the QR sticker directly instead.
                @else
                    The sticker may be damaged, or this vehicle may have been removed from the registry.
                @endif
            </p>

        </div>
    </div>
</section>
@endsection
