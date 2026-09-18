@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Scan QR Code</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .scan-page-wrap{max-width:520px;margin:0 auto;}
  .scan-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04),0 10px 30px rgba(15,23,42,0.06);overflow:hidden;border:1px solid #eef1f6;}
  .scan-card-header{background:linear-gradient(135deg,#1e293b,#0f172a);padding:22px 24px;color:#fff;}
  .scan-card-header h5{margin:0;font-weight:800;display:flex;align-items:center;gap:10px;}
  .scan-card-header p{margin:6px 0 0;color:#94a3b8;font-size:13px;}
  .scan-viewport-wrap{padding:20px;}
  #qr-reader{width:100%;border-radius:12px;overflow:hidden;background:#000;min-height:280px;}
  #qr-reader video{border-radius:12px;}
  .scan-status{display:flex;align-items:center;gap:9px;font-size:13px;color:#64748b;padding:0 20px 16px;}
  .scan-status .dot{width:8px;height:8px;border-radius:50%;background:#cbd5e1;flex:none;}
  .scan-status.active .dot{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,0.15);}
  .scan-divider{display:flex;align-items:center;gap:12px;padding:4px 20px 14px;color:#94a3b8;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
  .scan-divider::before,.scan-divider::after{content:'';flex:1;height:1px;background:#eef1f6;}
  .scan-manual{padding:0 20px 22px;}
  .scan-manual label{font-size:12px;font-weight:700;color:#334155;margin-bottom:7px;display:block;}
  .scan-manual .input-group input{border:1.5px solid #e2e8f0;border-radius:9px 0 0 9px;height:44px;font-family:'Courier New',monospace;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
  .scan-manual .btn-go{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:0 9px 9px 0;font-weight:700;padding:0 20px;}
  .scan-hint{background:#f8fafc;border-top:1px solid #eef1f6;padding:14px 20px;font-size:12px;color:#94a3b8;display:flex;gap:8px;align-items:flex-start;}
  .scan-hint i{color:#3b82f6;margin-top:2px;}
</style>
@endsection

@section('nav-title', 'VMIS | Scan QR Code')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="scan-page-wrap">
            <div class="scan-card">
                <div class="scan-card-header">
                    <h5><i class="fas fa-qrcode"></i> Scan Vehicle QR</h5>
                    <p>Point your camera at the sticker on the vehicle.</p>
                </div>

                <div class="scan-viewport-wrap">
                    <div id="qr-reader"></div>
                </div>
                <div class="scan-status" id="scanStatus"><span class="dot"></span><span id="scanStatusText">Starting camera…</span></div>

                <div class="scan-divider">or enter the code manually</div>
                <div class="scan-manual">
                    <label for="manualCode">Code printed under the QR sticker</label>
                    <div class="input-group">
                        <input type="text" id="manualCode" class="form-control" placeholder="e.g. XVO7B8V00Q" maxlength="20">
                        <div class="input-group-append">
                            <button class="btn btn-go" id="manualGoBtn" type="button"><i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="scan-hint">
                    <i class="fas fa-info-circle"></i>
                    <span>Camera scanning needs a secure connection on real devices — if it doesn't start, use manual entry above instead.</span>
                </div>
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
    const scanBaseUrl = @json(url('/vehicles/scan'));

    function goToVehicle(code) {
        code = (code || '').trim().toUpperCase();
        if (!code) return;
        window.location.href = scanBaseUrl + '/' + encodeURIComponent(code);
    }

    function handleDecoded(decodedText) {
        // The QR encodes a full URL to /vehicles/scan/{code} — if that's what we got, just go there.
        // If someone scans a raw code instead of a full URL, fall back to building it ourselves.
        if (decodedText.indexOf(scanBaseUrl) === 0) {
            window.location.href = decodedText;
        } else {
            goToVehicle(decodedText);
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
            statusEl.textContent = 'Camera unavailable — use manual entry below';
            console.warn('QR camera start failed:', err);
        });
    } else {
        statusEl.textContent = 'Scanner library failed to load — use manual entry below';
    }

    document.getElementById('manualGoBtn').addEventListener('click', function () {
        goToVehicle(document.getElementById('manualCode').value);
    });
    document.getElementById('manualCode').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') goToVehicle(this.value);
    });
});
</script>
@endsection
