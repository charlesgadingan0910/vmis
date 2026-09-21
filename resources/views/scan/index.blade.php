@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VMIS | Scan QR Code</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  :root{ --vmis-navy-900:#0f172a; --vmis-navy-800:#1e293b; --vmis-blue:#3b82f6; --vmis-blue-dark:#1d4ed8; }

  .scanner-page-wrap{max-width:480px;margin:0 auto;padding-bottom:env(safe-area-inset-bottom, 0px);}

  /* ---------- Hero ---------- */
  .scanner-hero{
    background:linear-gradient(135deg, var(--vmis-navy-800), var(--vmis-navy-900));
    border-radius:20px; padding:22px 22px 20px; color:#fff; position:relative; overflow:hidden;
    margin-bottom:16px; box-shadow:0 10px 30px rgba(15,23,42,0.18);
  }
  .scanner-hero::after{content:'';position:absolute;top:-40%;right:-15%;width:65%;height:65%;background:radial-gradient(circle, rgba(59,130,246,0.28), transparent 65%);}
  .scanner-hero-top{display:flex; align-items:center; gap:12px; position:relative; z-index:1;}
  .scanner-hero-icon{width:42px;height:42px;border-radius:12px;background:rgba(59,130,246,0.18);border:1px solid rgba(59,130,246,0.35);display:flex;align-items:center;justify-content:center;flex:none;}
  .scanner-hero-icon svg{width:22px;height:22px;}
  .scanner-hero h5{margin:0;font-weight:800;font-size:18px;letter-spacing:-.2px;}
  .scanner-hero p{margin:2px 0 0;color:#94a3b8;font-size:12.5px;}

  /* ---------- Viewfinder ---------- */
  .viewfinder-card{
    background:#000; border-radius:20px; overflow:hidden; position:relative;
    box-shadow:0 1px 3px rgba(15,23,42,0.06), 0 20px 40px rgba(15,23,42,0.16); margin-bottom:14px;
    aspect-ratio: 1 / 1;
  }
  #qr-reader{position:absolute; inset:0; width:100%; height:100%;}
  #qr-reader video{width:100%; height:100%; object-fit:cover;}

  .viewfinder-overlay{position:absolute; inset:0; z-index:5; pointer-events:none; display:flex; align-items:center; justify-content:center;}
  .viewfinder-frame{width:64%; aspect-ratio:1/1; position:relative;}
  .vf-corner{position:absolute; width:30px; height:30px; border-color:#60a5fa; opacity:0.95;}
  .vf-corner.tl{top:0; left:0; border-top:3px solid; border-left:3px solid; border-radius:8px 0 0 0;}
  .vf-corner.tr{top:0; right:0; border-top:3px solid; border-right:3px solid; border-radius:0 8px 0 0;}
  .vf-corner.bl{bottom:0; left:0; border-bottom:3px solid; border-left:3px solid; border-radius:0 0 0 8px;}
  .vf-corner.br{bottom:0; right:0; border-bottom:3px solid; border-right:3px solid; border-radius:0 0 8px 0;}
  .vf-scanline{
    position:absolute; left:4%; right:4%; height:2px; top:0;
    background:linear-gradient(90deg, transparent, #60a5fa 20%, #93c5fd 50%, #60a5fa 80%, transparent);
    box-shadow:0 0 8px 1px rgba(96,165,250,0.7);
    animation: vfScan 2.4s ease-in-out infinite;
  }
  @keyframes vfScan{
    0%{ top:4%; opacity:0; }
    10%{ opacity:1; }
    50%{ top:96%; opacity:1; }
    60%{ opacity:0; }
    100%{ top:4%; opacity:0; }
  }

  .scan-status-bar{
    display:flex; align-items:center; justify-content:center; gap:9px;
    padding:12px 16px 16px; font-size:13px; color:#64748b; font-weight:600;
  }
  .scan-status-bar .dot{width:8px;height:8px;border-radius:50%;background:#cbd5e1;flex:none; transition:all .2s ease;}
  .scan-status-bar.active .dot{background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,0.16);}
  .scan-status-bar.active{color:#16a34a;}

  /* ---------- Manual entry ---------- */
  .manual-divider{display:flex;align-items:center;gap:12px;margin:6px 0 16px;color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;}
  .manual-divider::before,.manual-divider::after{content:'';flex:1;height:1px;background:#eef1f6;}

  .manual-card{background:#fff;border-radius:16px;border:1px solid #eef1f6;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:18px 18px 20px;}
  .manual-card label{font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;display:block;}
  .manual-card label small{display:block;font-weight:500;color:#94a3b8;font-size:11px;margin-top:2px;text-transform:none;letter-spacing:0;}
  .manual-input-group{display:flex; gap:8px;}
  .manual-input-group input{
    flex:1; border:1.5px solid #e2e8f0; border-radius:11px; height:50px; padding:0 16px;
    font-family:'Courier New',monospace; font-weight:800; font-size:17px; letter-spacing:.06em;
    text-transform:uppercase; color:#0f172a; background:#f8fafc;
  }
  .manual-input-group input:focus{outline:none; border-color:var(--vmis-blue); background:#fff; box-shadow:0 0 0 3.5px rgba(59,130,246,0.12);}
  .manual-input-group input::placeholder{color:#cbd5e1; letter-spacing:.04em;}
  .btn-go{
    width:50px; height:50px; border-radius:11px; border:none; flex:none;
    background:linear-gradient(135deg, var(--vmis-blue), var(--vmis-blue-dark)); color:#fff;
    display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(59,130,246,0.3);
    transition:transform .15s ease;
  }
  .btn-go:active{transform:scale(0.94);}
  .btn-go svg{width:20px;height:20px;}

  .scan-hint{display:flex; gap:9px; align-items:flex-start; font-size:12px; color:#94a3b8; padding:16px 4px 4px; line-height:1.5;}
  .scan-hint svg{width:16px;height:16px;flex:none;margin-top:1px;color:#93c5fd;}

  @media (max-width: 420px){
    .scanner-hero h5{font-size:16.5px;}
  }
</style>
@endsection

@section('nav-title', 'VMIS | Scan QR Code')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="scanner-page-wrap">

            <div class="scanner-hero">
                <div class="scanner-hero-top">
                    <div class="scanner-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.3" stroke="#93c5fd" stroke-width="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.3" stroke="#93c5fd" stroke-width="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.3" stroke="#93c5fd" stroke-width="1.7"/><rect x="15" y="15.5" width="5.5" height="5.5" fill="#93c5fd"/></svg>
                    </div>
                    <div>
                        <h5>Scan Vehicle QR</h5>
                        <p>PRO5 Fleet Registry — point at the sticker</p>
                    </div>
                </div>
            </div>

            <div class="viewfinder-card">
                <div id="qr-reader"></div>
                <div class="viewfinder-overlay">
                    <div class="viewfinder-frame">
                        <div class="vf-corner tl"></div>
                        <div class="vf-corner tr"></div>
                        <div class="vf-corner bl"></div>
                        <div class="vf-corner br"></div>
                        <div class="vf-scanline"></div>
                    </div>
                </div>
            </div>
            <div class="scan-status-bar" id="scanStatus"><span class="dot"></span><span id="scanStatusText">Starting camera…</span></div>

            <div class="manual-divider">or enter manually</div>

            <div class="manual-card">
                <label for="manualPlate">
                    Plate number
                    <small>Printed under the QR code on the sticker</small>
                </label>
                <div class="manual-input-group">
                    <input type="text" id="manualPlate" placeholder="e.g. 12121212" maxlength="20" inputmode="text" autocomplete="off">
                    <button class="btn-go" id="manualGoBtn" type="button" aria-label="Look up vehicle">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>

            <div class="scan-hint">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8V13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                <span>Camera scanning needs a secure connection on real devices — if it doesn't start, the plate number above always works.</span>
            </div>

        </div>
    </div>
</section>
@endsection

@section('script')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusEl = document.getElementById('scanStatusText');
    const statusWrap = document.getElementById('scanStatus');
    const scanQrBaseUrl = @json(url('/vehicles/scan'));
    const scanPlateBaseUrl = @json(url('/vehicles/scan-plate'));

    function goToVehicleByPlate(plate) {
        plate = (plate || '').trim().toUpperCase();
        if (!plate) return;
        window.location.href = scanPlateBaseUrl + '/' + encodeURIComponent(plate);
    }

    function handleDecoded(decodedText) {
        // The QR encodes a full URL to /vehicles/scan/{code} — if that's what we got, just go there.
        if (decodedText.indexOf(scanQrBaseUrl) === 0) {
            window.location.href = decodedText;
        } else {
            // Fallback: someone scanned/pasted a raw code rather than a full URL.
            window.location.href = scanQrBaseUrl + '/' + encodeURIComponent(decodedText.trim());
        }
    }

    if (typeof Html5Qrcode !== 'undefined') {
        const html5QrCode = new Html5Qrcode('qr-reader');
        Html5Qrcode.getCameras().then(function (devices) {
            if (!devices || !devices.length) throw new Error('No camera found');
            const back = devices.find(function (d) { return /back|rear|environment/i.test(d.label); });
            const cameraId = back ? back.id : devices[0].id;
            return html5QrCode.start(
                cameraId,
                { fps: 10, qrbox: { width: 240, height: 240 } },
                handleDecoded
            );
        }).then(function () {
            statusWrap.classList.add('active');
            statusEl.textContent = 'Camera active — point at the sticker';
        }).catch(function (err) {
            statusEl.textContent = 'Camera unavailable — use the plate number below';
            console.warn('QR camera start failed:', err);
        });
    } else {
        statusEl.textContent = 'Scanner library failed to load — use the plate number below';
    }

    document.getElementById('manualGoBtn').addEventListener('click', function () {
        goToVehicleByPlate(document.getElementById('manualPlate').value);
    });
    document.getElementById('manualPlate').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') goToVehicleByPlate(this.value);
    });
});
</script>
@endsection
