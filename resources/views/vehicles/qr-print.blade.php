<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | Print QR Codes</title>
<style>
  @page { margin: 10mm; }
  *{box-sizing:border-box;}
  body{
    font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:32px 24px;color:#0f172a;
    background:linear-gradient(180deg,#f1f5f9,#e2e8f0);
  }

  .toolbar{
    max-width:920px;margin:0 auto 24px;padding:20px 26px;border-radius:16px;
    background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;
    display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;
    box-shadow:0 10px 30px rgba(15,23,42,0.18);
  }
  .toolbar-brand{display:flex;align-items:center;gap:12px;}
  .toolbar-brand img{height:34px;width:34px;object-fit:contain;border-radius:6px;background:#fff;padding:3px;}
  .toolbar-brand .divider{width:1px;height:28px;background:rgba(255,255,255,0.15);}
  .toolbar h4{margin:0;font-weight:800;font-size:16px;}
  .toolbar .sub{font-size:12px;color:#94a3b8;margin-top:2px;}
  .btn-print{
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;
    padding:11px 22px;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;
    box-shadow:0 2px 6px rgba(59,130,246,0.3);display:inline-flex;align-items:center;gap:8px;
  }
  .btn-print:hover{box-shadow:0 8px 18px rgba(59,130,246,0.4);}
  .btn-print svg{width:15px;height:15px;}

  .sticker-sheet{
    max-width:920px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fill, 2.1in);
    gap:16px;justify-content:center;
  }

  .sticker{
    width:2.1in;min-height:2.35in;background:#fff;border-radius:14px;border:1px solid #e2e8f0;
    box-shadow:0 4px 14px rgba(15,23,42,0.06);overflow:hidden;page-break-inside:avoid;
    display:flex;flex-direction:column;align-items:center;
  }
  .sticker-accent{width:100%;height:4px;background:linear-gradient(90deg,#3b82f6,#1d4ed8);flex:none;}
  .sticker-body{padding:12px 10px 14px;display:flex;flex-direction:column;align-items:center;text-align:center;flex:1;}
  .sticker-qr-frame{
    width:1.5in;height:1.5in;border:1px solid #f1f5f9;border-radius:10px;
    display:flex;align-items:center;justify-content:center;padding:6px;background:#fff;
  }
  .sticker-qr-frame img{width:100%;height:100%;}
  .sticker .plate{
    font-family:'Courier New',monospace;font-weight:800;font-size:13.5px;letter-spacing:.04em;
    margin-top:9px;background:#0f172a;color:#fff;padding:3px 11px;border-radius:6px;
  }
  .sticker .veh-type{font-size:9px;color:#64748b;letter-spacing:.03em;margin-top:6px;font-weight:600;}
  .sticker .wordmark{font-size:7.5px;color:#94a3b8;letter-spacing:.1em;text-transform:uppercase;margin-top:auto;padding-top:8px;font-weight:700;}

  @media (max-width: 640px){
    body{padding:20px 14px;}
    .toolbar{flex-direction:column;align-items:flex-start;padding:18px;}
    .btn-print{width:100%;justify-content:center;}
    .sticker-sheet{grid-template-columns:repeat(auto-fill, minmax(2.1in, 1fr));}
  }

  @media print {
    body{background:#fff;padding:0;}
    .toolbar{display:none;}
    .sticker-sheet{gap:12px;}
    .sticker{box-shadow:none;border:1px solid #cbd5e1;}
  }
</style>
</head>
<body>

<div class="toolbar">
    <div class="toolbar-brand">
        <img src="{{ asset('images/pnp-logo.png') }}" alt="PNP">
        <img src="{{ asset('images/pro5-logo.png') }}" alt="PRO5">
        <div class="divider"></div>
        <div>
            <h4>QR Stickers — {{ $vehicles->count() }} vehicle{{ $vehicles->count() === 1 ? '' : 's' }}</h4>
            <div class="sub">Each sticker is 2&quot; &times; 2&quot; — the standard size for a vehicle asset tag.</div>
        </div>
    </div>
    <button class="btn-print" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Print
    </button>
</div>

<div class="sticker-sheet">
    @foreach ($vehicles as $vehicle)
    <div class="sticker">
        <div class="sticker-accent"></div>
        <div class="sticker-body">
            <div class="sticker-qr-frame">
                <img src="{{ route('vehicles.qr-image', $vehicle) }}" alt="QR — {{ $vehicle->plate_number }}">
            </div>
            <div class="plate">{{ strtoupper($vehicle->plate_number) }}</div>
            <div class="veh-type">{{ strtoupper($vehicle->type->name ?? 'VEHICLE') }}</div>
            <div class="wordmark">PRO5 &middot; VMIS</div>
        </div>
    </div>
    @endforeach
</div>

</body>
</html>
