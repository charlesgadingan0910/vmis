@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Live Monitoring</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endsection

@section('css')
<style>
  /* Same card/toolbar look as every other module index page — each view
     keeps its own copy of these rather than a shared stylesheet, matching
     the existing convention (see trip_logs/index.blade.php etc.). */
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; }
  .toolbar-header { padding: 18px 22px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .live-map-wrap { position: relative; height: calc(100vh - 230px); min-height: 460px; border-radius: 16px; overflow: hidden; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); }
  #liveMapEl { width: 100%; height: 100%; z-index: 1; }

  .plate-badge { display: inline-block; font-family: 'Courier New', monospace; font-weight: 800; font-size: 13px; background: #0f172a; color: #fff; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.5px; }

  /* ---------------- Active Trips sidebar — a floating card of individual
     trip "tiles" (icon avatar + plate + live stat chips) rather than a
     flat list of text rows, matching the stat-card language used
     elsewhere in VMIS (see trip_registrations' stat cards). ---------------- */
  .live-sidebar { position: absolute; top: 14px; left: 14px; width: 310px; max-width: calc(100% - 28px); max-height: calc(100% - 28px); overflow-y: auto; background: rgba(255, 255, 255, 0.98); border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16); border: 1px solid rgba(226, 232, 240, 0.7); z-index: 1000; }
  .live-sidebar-header { padding: 13px 16px; background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 14px 14px 0 0; font-weight: 800; font-size: 11.5px; color: #fff; display: flex; align-items: center; justify-content: space-between; text-transform: uppercase; letter-spacing: 0.4px; position: sticky; top: 0; z-index: 2; }
  .live-sidebar-title { display: flex; align-items: center; gap: 7px; }
  .live-sidebar-title i { color: #60a5fa; }
  .live-sidebar-count-badge { background: rgba(255, 255, 255, 0.16); color: #fff; font-weight: 800; font-size: 10.5px; padding: 3px 9px; border-radius: 20px; min-width: 20px; text-align: center; }

  .live-trip-list-inner { padding: 10px; display: flex; flex-direction: column; gap: 8px; }
  .live-trip-item { border-radius: 12px; border: 1px solid #eef1f6; background: #fff; padding: 11px 12px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease; }
  .live-trip-item:hover { border-color: #bfdbfe; box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
  .live-trip-item.is-stale { opacity: 0.68; }
  .live-trip-avatar { width: 36px; height: 36px; border-radius: 10px; background: rgba(37, 99, 235, 0.1); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
  .live-trip-item.is-stale .live-trip-avatar { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
  .live-trip-main { flex: 1; min-width: 0; }
  .live-trip-top-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .live-trip-plate { font-weight: 800; font-size: 12.5px; color: #0f172a; font-family: 'Courier New', monospace; letter-spacing: 0.3px; }
  .live-trip-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; flex-shrink: 0; animation: live-pulse 1.8s infinite; }
  .live-trip-item.is-stale .live-trip-status-dot { background: #f59e0b; animation: none; }
  .live-trip-driver { font-size: 11.5px; color: #64748b; margin-top: 2px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .live-trip-meta { font-size: 10px; color: #64748b; margin-top: 7px; display: flex; gap: 6px; flex-wrap: wrap; }
  .live-trip-meta span { display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; padding: 3px 8px; border-radius: 7px; font-weight: 700; }
  .live-trip-meta .text-warning { color: #b45309 !important; background: #fef3c7 !important; }
  .live-empty-state { padding: 34px 18px; text-align: center; color: #94a3b8; font-size: 12.5px; }

  .live-pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; display: inline-block; margin-right: 7px; animation: live-pulse 1.8s infinite; }
  @keyframes live-pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.45); } 70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }

  .live-updated-label { font-size: 10.5px; color: #94a3b8; margin-left: 10px; font-weight: 600; }
  .live-updated-label i { margin-right: 3px; }
  .live-updated-label.is-fetching i { animation: live-spin 0.8s linear infinite; }
  @keyframes live-spin { to { transform: rotate(360deg); } }

  /* ---------------- Vehicle marker — a detailed top-down car (inline
     SVG: glossy gradient body, wheels peeking out at all four corners,
     windshield/rear glass, roof panel, headlights/taillights, mirrors,
     a paint highlight streak) rather than a flat silhouette, so it reads
     as an actual vehicle instead of a blob even at marker size. Rotated
     in JS to the bearing of travel, inside a soft ring that pulses only
     while the trip is actually live, so "is this vehicle's data current"
     reads at a glance the same way the sidebar's pulse dot does.
     Position changes are animated in JS (see animateMarkerTo) rather
     than jumping on every 10s refresh — that, plus the marker actually
     looking like a car, is what sells "this is really moving" instead of
     a dot that teleports on a map every few seconds. The body's fill is
     an inline per-marker <linearGradient> (see carSvgMarkup() in the
     script below) — CSS alone can't fake the metallic-paint gradient, so
     only the flatter details (glass, wheels, lights, mirrors) are
     styled here. ---------------- */
  .vehicle-marker-wrap { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; position: relative; }
  /* Soft radial glow instead of a flat pulsing disc — premium nav-app
     "puck" feel (Waze/Google Maps live-location marker) rather than a
     plain ring. */
  .vehicle-marker-ring { position: absolute; inset: 0; border-radius: 50%; background: radial-gradient(circle, rgba(59, 130, 246, 0.4) 0%, rgba(59, 130, 246, 0.14) 55%, rgba(59, 130, 246, 0) 76%); animation: marker-pulse 2s ease-out infinite; }
  .vehicle-marker-wrap.is-stale .vehicle-marker-ring { display: none; }
  @keyframes marker-pulse { 0% { transform: scale(0.45); opacity: 0.9; } 100% { transform: scale(1); opacity: 0; } }
  /* Ground shadow — static (doesn't rotate with the car), so the vehicle
     reads as sitting on the map rather than a flat sticker. */
  .vehicle-marker-shadow { position: absolute; left: 50%; bottom: 1px; width: 24px; height: 8px; border-radius: 50%; background: radial-gradient(ellipse at center, rgba(15, 23, 42, 0.42) 0%, rgba(15, 23, 42, 0.16) 55%, rgba(15, 23, 42, 0) 80%); transform: translateX(-50%); }
  .vehicle-marker-wrap.is-stale .vehicle-marker-shadow { opacity: 0.55; }
  .vehicle-marker-car { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; transition: transform 0.2s ease; filter: drop-shadow(0 3px 4px rgba(15, 23, 42, 0.5)); }
  .car-wheel { fill: #0f172a; }
  .car-body { stroke: #1e3a8a; stroke-width: 0.6; }
  .car-shine { pointer-events: none; }
  .car-hood-seam { stroke: rgba(255, 255, 255, 0.35); stroke-width: 0.45; stroke-linecap: round; fill: none; }
  .car-headlight { fill: #fde68a; }
  .car-glass { fill: #bfdbfe; opacity: 0.92; }
  .car-roof { fill: rgba(15, 23, 42, 0.2); }
  .car-taillight { fill: #ef4444; }
  .car-mirror { fill: rgba(15, 23, 42, 0.55); }
  .car-highlight { stroke: rgba(255, 255, 255, 0.6); stroke-width: 1; stroke-linecap: round; fill: none; }
  .vehicle-marker-wrap.is-stale .vehicle-marker-car { filter: none; opacity: 0.85; }
  .vehicle-marker-wrap.is-stale .car-wheel { fill: #475569; }
  .vehicle-marker-wrap.is-stale .car-body { stroke: #475569; }
  .vehicle-marker-wrap.is-stale .car-headlight { fill: #e2e8f0; opacity: 0.6; }
  .vehicle-marker-wrap.is-stale .car-glass { fill: #e2e8f0; }
  .vehicle-marker-wrap.is-stale .car-roof { fill: rgba(100, 116, 139, 0.25); }
  .vehicle-marker-wrap.is-stale .car-taillight { fill: #94a3b8; }
  .vehicle-marker-wrap.is-stale .car-mirror { fill: rgba(100, 116, 139, 0.6); }
  .vehicle-marker-wrap.is-stale .car-highlight { stroke: rgba(255, 255, 255, 0.25); }
  .vehicle-marker-wrap.is-stale .car-hood-seam { stroke: rgba(255, 255, 255, 0.15); }

  /* ---------------- Marker popup — Leaflet's default popup is a plain
     square box of text; this re-skins the chrome once (wrapper/close
     button) and the trip-popup-* classes below lay it out as a small
     stat-card (plate badge + live/stale status chip, driver row, stat
     tiles) instead of a few bare lines. ---------------- */
  .leaflet-popup-content-wrapper { border-radius: 14px; padding: 0; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.2); overflow: hidden; }
  .leaflet-popup-content { margin: 0 !important; width: 252px !important; }
  .leaflet-popup-close-button { top: 9px !important; right: 10px !important; font-size: 18px !important; color: #94a3b8 !important; }
  .leaflet-popup-close-button:hover { color: #0f172a !important; }

  .trip-popup-header { padding: 14px 32px 11px 16px; border-bottom: 1px solid #f1f5f9; }
  .trip-popup-header-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .trip-popup-vehicle-label { font-size: 11.5px; color: #64748b; margin-top: 5px; font-weight: 600; }
  .trip-popup-status { font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; padding: 4px 8px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
  .trip-popup-status.is-live { background: #dcfce7; color: #15803d; }
  .trip-popup-status.is-stale { background: #fef3c7; color: #b45309; }
  .trip-popup-status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  .trip-popup-status.is-live .trip-popup-status-dot { animation: live-pulse 1.8s infinite; }

  .trip-popup-body { padding: 12px 16px 14px; }
  .trip-popup-driver { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
  .trip-popup-driver-avatar { width: 25px; height: 25px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }

  .trip-popup-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .trip-popup-stat { background: #f8fafc; border-radius: 10px; padding: 8px 9px; display: flex; align-items: center; gap: 8px; }
  .trip-popup-stat-icon { width: 26px; height: 26px; border-radius: 8px; background: rgba(37, 99, 235, 0.1); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
  .trip-popup-stat-value { font-size: 12.5px; font-weight: 800; color: #0f172a; line-height: 1.2; }
  .trip-popup-stat-label { font-size: 9px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; margin-top: 1px; }

  .trip-popup-alert { margin-top: 10px; background: #fef2f2; color: #b91c1c; font-size: 11px; font-weight: 600; padding: 7px 9px; border-radius: 8px; display: flex; align-items: center; gap: 6px; }
</style>
@endsection

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-card-container" style="margin-bottom: 16px;">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5><i class="fas fa-satellite-dish text-primary"></i> Live Monitoring</h5>
                    <p>Every vehicle currently on a GPS-tracked trip — updates automatically, no need to refresh.</p>
                </div>
                <div class="d-flex align-items-center">
                    <span class="live-pulse-dot"></span>
                    <span id="liveCountLabel" style="font-size: 12.5px; font-weight: 700; color: #16a34a;">Loading…</span>
                    <span id="liveUpdatedLabel" class="live-updated-label"></span>
                </div>
            </div>
        </div>

        <div class="live-map-wrap">
            <div id="liveMapEl"></div>
            <div class="live-sidebar">
                <div class="live-sidebar-header">
                    <span class="live-sidebar-title"><i class="fas fa-location-dot"></i> Active Trips</span>
                    <span id="liveSidebarCount" class="live-sidebar-count-badge">0</span>
                </div>
                <div id="liveTripList" class="live-trip-list-inner"></div>
            </div>
        </div>

        <p class="text-muted mt-2" style="font-size: 11px;">
            <i class="fas fa-info-circle"></i>
            A pin only appears here while a driver has an active GPS trip in progress (see Trip Logs → Start a GPS Trip in the driver app).
            A grayed-out pin means that phone hasn't reported a position in over 90 seconds — likely a weak signal or the app being backgrounded without "Allow all the time" location access.
        </p>
    </div>
</section>
@endsection

@section('script')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
$(document.body).ready(function() {
    // Rough Bicol Region centroid — only ever seen before the first
    // successful poll repositions the map via fitBounds() below.
    const map = L.map('liveMapEl').setView([13.1391, 123.7438], 9);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    const markers = {};     // vehicle_id -> L.marker
    const prevLatLngs = {}; // vehicle_id -> [lat, lng] — last position, for computing heading
    const headings = {};    // vehicle_id -> last known bearing in degrees (kept when stationary)
    let hasFitOnce = false;
    let lastUpdatedAt = null;

    /// Bearing in degrees (0 = north, 90 = east) from one point to another
    /// — used to rotate each vehicle's marker arrow to its direction of
    /// travel instead of always pointing the same way regardless of
    /// movement, the same way Google/Uber-style live-location markers do.
    function bearingDeg(lat1, lng1, lat2, lng2) {
        const toRad = d => d * Math.PI / 180, toDeg = r => r * 180 / Math.PI;
        const dLng = toRad(lng2 - lng1);
        const y = Math.sin(dLng) * Math.cos(toRad(lat2));
        const x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) - Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(dLng);
        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    /// Minimal HTML-escape for anything coming from the API before it's
    /// concatenated into marker/sidebar markup (plate number, driver
    /// name, vehicle label) — these ultimately originate from user-edited
    /// records (Vehicles, Drivers), so don't trust them verbatim.
    function escHtml(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /// Builds the actual car artwork shared by the map marker and the
    /// sidebar avatar — a premium, Waze/Google-Maps-nav-puck style
    /// vehicle rather than a flat silhouette: wheels peeking out at all
    /// four corners, a rich 3-stop gradient body (bright sky highlight →
    /// brand blue → deep navy shadow) with a glossy sheen painted over
    /// the *same* body outline (so the shine is naturally contained
    /// without needing a separate SVG clip-path), a hood seam line,
    /// windshield/rear glass, a shaded roof panel, headlights/
    /// taillights, side mirrors, and a diagonal paint-highlight streak.
    /// idSuffix must be unique per <svg> on the page (gradients are
    /// referenced by id) since several vehicles can be visible at once.
    function carSvgMarkup(idSuffix, lightColor, midColor, darkColor, size) {
        const gradId = 'carGrad' + idSuffix;
        const shineId = 'carShine' + idSuffix;
        const bodyPath = 'M14,3.3 C16.7,3.3 18.2,5.4 18.9,8.1 L19.8,11 C20.3,12.6 20.4,13.9 20.4,15.4 L20.4,21.5 C20.4,24.2 18.7,25.1 16.2,25.3 L11.8,25.3 C9.3,25.1 7.6,24.2 7.6,21.5 L7.6,15.4 C7.6,13.9 7.7,12.6 8.2,11 L9.1,8.1 C9.8,5.4 11.3,3.3 14,3.3 Z';
        return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg">' +
            '<defs>' +
                '<linearGradient id="' + gradId + '" x1="0.15" y1="0" x2="0.85" y2="1">' +
                    '<stop offset="0%" stop-color="' + lightColor + '"></stop>' +
                    '<stop offset="48%" stop-color="' + midColor + '"></stop>' +
                    '<stop offset="100%" stop-color="' + darkColor + '"></stop>' +
                '</linearGradient>' +
                '<radialGradient id="' + shineId + '" cx="38%" cy="26%" r="55%">' +
                    '<stop offset="0%" stop-color="#ffffff" stop-opacity="0.85"></stop>' +
                    '<stop offset="100%" stop-color="#ffffff" stop-opacity="0"></stop>' +
                '</radialGradient>' +
            '</defs>' +
            '<rect class="car-wheel" x="4.6" y="9.3" width="2.6" height="5.6" rx="1.3"></rect>' +
            '<rect class="car-wheel" x="20.8" y="9.3" width="2.6" height="5.6" rx="1.3"></rect>' +
            '<rect class="car-wheel" x="4.6" y="18.4" width="2.6" height="6" rx="1.3"></rect>' +
            '<rect class="car-wheel" x="20.8" y="18.4" width="2.6" height="6" rx="1.3"></rect>' +
            '<path class="car-body" fill="url(#' + gradId + ')" d="' + bodyPath + '"></path>' +
            '<path class="car-shine" fill="url(#' + shineId + ')" d="' + bodyPath + '"></path>' +
            '<path class="car-hood-seam" d="M14,4.6 L14,8.2"></path>' +
            '<ellipse class="car-headlight" cx="10.3" cy="4.9" rx="1" ry="0.7"></ellipse>' +
            '<ellipse class="car-headlight" cx="17.7" cy="4.9" rx="1" ry="0.7"></ellipse>' +
            '<path class="car-glass" d="M9.4,8.4 C9.4,7 11.3,6.1 14,6.1 C16.7,6.1 18.6,7 18.6,8.4 L18.1,11.8 C17.5,12.6 10.5,12.6 9.9,11.8 Z"></path>' +
            '<rect class="car-roof" x="10.3" y="13" width="7.4" height="5" rx="1.6"></rect>' +
            '<path class="car-glass" d="M9.9,19.2 C10.5,18.5 17.5,18.5 18.1,19.2 L18.5,21.9 C18.5,22.9 17,23.4 14,23.4 C11,23.4 9.5,22.9 9.5,21.9 Z"></path>' +
            '<rect class="car-taillight" x="8.3" y="22.6" width="1.6" height="1" rx="0.4"></rect>' +
            '<rect class="car-taillight" x="18.1" y="22.6" width="1.6" height="1" rx="0.4"></rect>' +
            '<circle class="car-mirror" cx="6.5" cy="11.2" r="1.15"></circle>' +
            '<circle class="car-mirror" cx="21.5" cy="11.2" r="1.15"></circle>' +
            '<path class="car-highlight" d="M10,5 C9.3,9 9.1,14 9.6,20"></path>' +
            '</svg>';
    }

    /// Map marker icon — nose points toward 0deg (north) at rest, same
    /// convention the old arrow used, so the existing bearingDeg/rotate
    /// logic below needs no changes, only a better-looking icon to rotate.
    function markerIcon(stale, headingDeg, vehicleId) {
        const idSuffix = 'Map' + (vehicleId === undefined || vehicleId === null ? 'x' : vehicleId) + (stale ? 'S' : 'A');
        const carSvg = stale
            ? carSvgMarkup(idSuffix, '#e2e8f0', '#94a3b8', '#475569', 38)
            : carSvgMarkup(idSuffix, '#93c5fd', '#3b82f6', '#1e3a8a', 38);
        const html = '<div class="vehicle-marker-wrap' + (stale ? ' is-stale' : '') + '">' +
            '<div class="vehicle-marker-ring"></div>' +
            '<div class="vehicle-marker-shadow"></div>' +
            '<div class="vehicle-marker-car" style="transform: rotate(' + headingDeg + 'deg);">' + carSvg + '</div>' +
            '</div>';
        return L.divIcon({ className: '', html: html, iconSize: [44, 44], iconAnchor: [22, 22] });
    }

    /// Glides a marker from its current position to [lat,lng] over
    /// [duration]ms instead of jumping there instantly — this alone is
    /// most of what makes the map feel "live" rather than a page that
    /// silently teleports a dot every refresh. Purely cosmetic: the
    /// marker's *reported* position (used for heading/bounds) is the new
    /// one immediately: only the pixel it's drawn at catches up gradually.
    function animateMarkerTo(marker, toLatLng, duration) {
        const from = marker.getLatLng();
        const to = L.latLng(toLatLng);
        if (from.equals(to)) return;
        const start = performance.now();
        function step(now) {
            const t = Math.min(1, (now - start) / duration);
            // ease-out — fast start, gentle settle, rather than a linear
            // (slightly robotic-looking) glide.
            const eased = 1 - Math.pow(1 - t, 3);
            marker.setLatLng([
                from.lat + (to.lat - from.lat) * eased,
                from.lng + (to.lng - from.lng) * eased,
            ]);
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function fmtAgo(ms) {
        const secs = Math.max(0, Math.round(ms / 1000));
        return secs < 2 ? 'just now' : secs + 's ago';
    }

    function fmtElapsed(startedAtIso) {
        if (!startedAtIso) return '—';
        const diffMs = Date.now() - new Date(startedAtIso).getTime();
        const mins = Math.max(0, Math.floor(diffMs / 60000));
        const h = Math.floor(mins / 60), m = mins % 60;
        return h > 0 ? (h + 'h ' + m + 'm') : (m + 'm');
    }

    function popupHtml(t) {
        const stale = !!t.stale;
        const statusHtml = stale
            ? '<span class="trip-popup-status is-stale"><span class="trip-popup-status-dot"></span> Signal lost</span>'
            : '<span class="trip-popup-status is-live"><span class="trip-popup-status-dot"></span> Live</span>';

        let html = '<div class="trip-popup-header">' +
                '<div class="trip-popup-header-top">' +
                    '<span class="plate-badge">' + escHtml(t.plate_number || 'Unknown') + '</span>' +
                    statusHtml +
                '</div>' +
                (t.vehicle_label ? '<div class="trip-popup-vehicle-label">' + escHtml(t.vehicle_label) + '</div>' : '') +
            '</div>' +
            '<div class="trip-popup-body">' +
                '<div class="trip-popup-driver"><span class="trip-popup-driver-avatar"><i class="fas fa-user"></i></span> ' + escHtml(t.driver_name || 'Unknown driver') + '</div>' +
                '<div class="trip-popup-stats">' +
                    '<div class="trip-popup-stat"><span class="trip-popup-stat-icon"><i class="fas fa-clock"></i></span><div><div class="trip-popup-stat-value">' + fmtElapsed(t.started_at) + '</div><div class="trip-popup-stat-label">Elapsed</div></div></div>' +
                    '<div class="trip-popup-stat"><span class="trip-popup-stat-icon"><i class="fas fa-road"></i></span><div><div class="trip-popup-stat-value">' + (t.distance_km || 0).toFixed(1) + ' km</div><div class="trip-popup-stat-label">Distance</div></div></div>' +
                '</div>';

        if (stale) {
            const lastSeen = t.last_ping_at ? new Date(t.last_ping_at).toLocaleTimeString() : 'unknown';
            html += '<div class="trip-popup-alert"><i class="fas fa-triangle-exclamation"></i> Last seen ' + lastSeen + '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderSidebar(trips) {
        const list = $('#liveTripList');
        list.empty();

        if (trips.length === 0) {
            list.html('<div class="live-empty-state"><i class="fas fa-route mb-2" style="font-size:22px; display:block;"></i>No vehicles currently on a tracked trip.</div>');
            return;
        }

        trips.forEach(function(t) {
            const item = $('<div class="live-trip-item' + (t.stale ? ' is-stale' : '') + '"></div>');
            let metaHtml = '<span><i class="fas fa-clock"></i> ' + fmtElapsed(t.started_at) + '</span>' +
                '<span><i class="fas fa-road"></i> ' + (t.distance_km || 0).toFixed(1) + ' km</span>';
            if (t.stale) {
                metaHtml += '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Signal lost</span>';
            }
            const sidebarIdSuffix = 'SB' + (t.vehicle_id === undefined || t.vehicle_id === null ? 'x' : t.vehicle_id) + (t.stale ? 'S' : 'A');
            const sidebarCarSvg = t.stale
                ? carSvgMarkup(sidebarIdSuffix, '#e2e8f0', '#94a3b8', '#64748b', 26)
                : carSvgMarkup(sidebarIdSuffix, '#93c5fd', '#3b82f6', '#1e3a8a', 26);
            item.html(
                '<div class="live-trip-avatar">' + sidebarCarSvg + '</div>' +
                '<div class="live-trip-main">' +
                    '<div class="live-trip-top-row">' +
                        '<span class="live-trip-plate">' + escHtml(t.plate_number || 'Unknown') + '</span>' +
                        '<span class="live-trip-status-dot"></span>' +
                    '</div>' +
                    '<div class="live-trip-driver">' + escHtml(t.driver_name || 'Unknown driver') + (t.vehicle_label ? ' · ' + escHtml(t.vehicle_label) : '') + '</div>' +
                    '<div class="live-trip-meta">' + metaHtml + '</div>' +
                '</div>'
            );
            item.on('click', function() {
                const marker = markers[t.vehicle_id];
                if (marker) {
                    map.setView(marker.getLatLng(), 15);
                    marker.openPopup();
                }
            });
            list.append(item);
        });
    }

    function refresh() {
        $('#liveUpdatedLabel').addClass('is-fetching').html('<i class="fas fa-sync-alt"></i> Updating…');

        $.getJSON("{{ route('live-monitoring.active-trips') }}", function(res) {
            if (!res || !res.success) return;
            const trips = res.trips || [];
            const seenIds = {};

            trips.forEach(function(t) {
                seenIds[t.vehicle_id] = true;
                const latLng = [t.latitude, t.longitude];

                // Heading: only recompute from an actual, meaningfully-sized
                // hop (>3m) — otherwise GPS jitter on a near-stationary
                // vehicle would spin the arrow randomly every refresh.
                // Keeps the last known heading in between real moves.
                const prev = prevLatLngs[t.vehicle_id];
                if (prev) {
                    const movedMeters = map.distance(prev, latLng);
                    if (movedMeters > 3) {
                        headings[t.vehicle_id] = bearingDeg(prev[0], prev[1], latLng[0], latLng[1]);
                    }
                }
                prevLatLngs[t.vehicle_id] = latLng;
                const heading = headings[t.vehicle_id] || 0;

                if (markers[t.vehicle_id]) {
                    animateMarkerTo(markers[t.vehicle_id], latLng, 1800);
                    markers[t.vehicle_id].setIcon(markerIcon(t.stale, heading, t.vehicle_id)).setPopupContent(popupHtml(t));
                } else {
                    markers[t.vehicle_id] = L.marker(latLng, { icon: markerIcon(t.stale, heading, t.vehicle_id) }).addTo(map).bindPopup(popupHtml(t));
                }
                // Deliberately no live trail/polyline here — this page is
                // just "where is it right now" (monitoring/tracking).
                // The driven path is still available afterward in Trip
                // Logs' route viewer, which draws it from the same
                // route_points once the trip is logged.
            });

            // Drop markers for vehicles that are no longer active (trip
            // ended, or the session went stale and was purged).
            Object.keys(markers).forEach(function(vehicleId) {
                if (!seenIds[vehicleId]) {
                    map.removeLayer(markers[vehicleId]);
                    delete markers[vehicleId];
                    delete prevLatLngs[vehicleId];
                    delete headings[vehicleId];
                }
            });

            $('#liveCountLabel').text(trips.length + (trips.length === 1 ? ' active trip' : ' active trips'));
            $('#liveSidebarCount').text(trips.length);
            renderSidebar(trips);

            if (!hasFitOnce && trips.length > 0) {
                const bounds = L.latLngBounds(trips.map(function(t) { return [t.latitude, t.longitude]; }));
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
                hasFitOnce = true;
            }

            lastUpdatedAt = Date.now();
            $('#liveUpdatedLabel').removeClass('is-fetching').html('<i class="fas fa-check"></i> Updated just now');
        }).fail(function() {
            $('#liveUpdatedLabel').removeClass('is-fetching').html('<i class="fas fa-exclamation-triangle"></i> Update failed — retrying…');
        });
    }

    // Keeps the "Updated Xs ago" label ticking between refreshes, so it's
    // obvious at a glance whether the map is actually live right now or
    // just showing you whatever it last managed to fetch.
    setInterval(function() {
        if (lastUpdatedAt === null || $('#liveUpdatedLabel').hasClass('is-fetching')) return;
        $('#liveUpdatedLabel').html('<i class="fas fa-check"></i> Updated ' + fmtAgo(Date.now() - lastUpdatedAt));
    }, 1000);

    refresh();
    setInterval(refresh, 10000);
});
</script>
@endsection
