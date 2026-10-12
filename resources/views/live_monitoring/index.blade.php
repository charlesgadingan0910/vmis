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

  .live-sidebar { position: absolute; top: 14px; left: 14px; width: 300px; max-width: calc(100% - 28px); max-height: calc(100% - 28px); overflow-y: auto; background: rgba(255, 255, 255, 0.97); border-radius: 12px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.14); z-index: 1000; }
  .live-sidebar-header { padding: 13px 16px; border-bottom: 1px solid #eef1f6; font-weight: 800; font-size: 12.5px; color: #0f172a; display: flex; align-items: center; justify-content: space-between; text-transform: uppercase; letter-spacing: 0.3px; }
  .live-trip-item { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s; }
  .live-trip-item:hover { background: #f8fafc; }
  .live-trip-item.is-stale { opacity: 0.55; }
  .live-trip-plate { font-weight: 800; font-size: 12.5px; color: #0f172a; }
  .live-trip-driver { font-size: 11.5px; color: #64748b; margin-top: 2px; }
  .live-trip-meta { font-size: 10.5px; color: #94a3b8; margin-top: 4px; display: flex; gap: 12px; flex-wrap: wrap; }
  .live-trip-meta .text-warning { color: #d97706 !important; }
  .live-empty-state { padding: 34px 18px; text-align: center; color: #94a3b8; font-size: 12.5px; }

  .live-pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; display: inline-block; margin-right: 7px; animation: live-pulse 1.8s infinite; }
  @keyframes live-pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.45); } 70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }

  .live-updated-label { font-size: 10.5px; color: #94a3b8; margin-left: 10px; font-weight: 600; }
  .live-updated-label i { margin-right: 3px; }
  .live-updated-label.is-fetching i { animation: live-spin 0.8s linear infinite; }
  @keyframes live-spin { to { transform: rotate(360deg); } }

  /* Vehicle marker — a heading-aware arrow (rotated in JS to the bearing
     of travel) inside a soft ring that pulses only while the trip is
     actually live, so "is this vehicle's data current" reads at a glance
     the same way the sidebar's pulse dot does. Position changes are
     animated in JS (see animateMarkerTo) rather than jumping on every
     10s refresh, which is what actually makes this feel "live" instead
     of a page that quietly teleports a dot every few seconds. */
  .vehicle-marker-wrap { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; position: relative; transition: transform 0.2s ease; }
  .vehicle-marker-ring { position: absolute; inset: 0; border-radius: 50%; background: rgba(37, 99, 235, 0.18); animation: marker-pulse 2s ease-out infinite; }
  .vehicle-marker-wrap.is-stale .vehicle-marker-ring { display: none; }
  @keyframes marker-pulse { 0% { transform: scale(0.45); opacity: 0.9; } 100% { transform: scale(1); opacity: 0; } }
  .vehicle-marker-arrow { width: 0; height: 0; border-left: 7px solid transparent; border-right: 7px solid transparent; border-bottom: 16px solid #2563eb; filter: drop-shadow(0 1px 2px rgba(15, 23, 42, 0.5)); position: relative; z-index: 1; }
  .vehicle-marker-wrap.is-stale .vehicle-marker-arrow { border-bottom-color: #94a3b8; filter: none; opacity: 0.85; }
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
                    <span>Active Trips</span>
                    <span id="liveSidebarCount" class="text-muted" style="font-weight: 700;">0</span>
                </div>
                <div id="liveTripList"></div>
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
    const polylines = {};   // vehicle_id -> { glow: L.polyline, line: L.polyline }
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

    function markerIcon(stale, headingDeg) {
        const html = '<div class="vehicle-marker-wrap' + (stale ? ' is-stale' : '') + '">' +
            '<div class="vehicle-marker-ring"></div>' +
            '<div class="vehicle-marker-arrow" style="transform: rotate(' + headingDeg + 'deg);"></div>' +
            '</div>';
        return L.divIcon({ className: '', html: html, iconSize: [34, 34], iconAnchor: [17, 17] });
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
        let html = '<div style="min-width:170px; font-size:12.5px;">' +
            '<strong>' + (t.plate_number || 'Unknown vehicle') + '</strong><br>' +
            (t.vehicle_label ? t.vehicle_label + '<br>' : '') +
            '<span class="text-muted">Driver:</span> ' + (t.driver_name || 'Unknown') + '<br>' +
            '<span class="text-muted">Elapsed:</span> ' + fmtElapsed(t.started_at) + '<br>' +
            '<span class="text-muted">Distance:</span> ' + (t.distance_km || 0).toFixed(1) + ' km';
        if (t.stale) {
            const lastSeen = t.last_ping_at ? new Date(t.last_ping_at).toLocaleTimeString() : 'unknown';
            html += '<br><span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> Signal lost — last seen ' + lastSeen + '</span>';
        }
        return html + '</div>';
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
            item.html(
                '<div class="live-trip-plate">' + (t.plate_number || 'Unknown') + '</div>' +
                '<div class="live-trip-driver">' + (t.driver_name || 'Unknown driver') + (t.vehicle_label ? ' · ' + t.vehicle_label : '') + '</div>' +
                '<div class="live-trip-meta">' + metaHtml + '</div>'
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
                    markers[t.vehicle_id].setIcon(markerIcon(t.stale, heading)).setPopupContent(popupHtml(t));
                } else {
                    markers[t.vehicle_id] = L.marker(latLng, { icon: markerIcon(t.stale, heading) }).addTo(map).bindPopup(popupHtml(t));
                }

                const trail = (t.route_points || [])
                    .filter(function(p) { return typeof p.lat === 'number' && typeof p.lng === 'number'; })
                    .map(function(p) { return [p.lat, p.lng]; });

                // Soft outer glow + crisp inner line, same treatment as the
                // Trip Logs route viewer — reads as a deliberately-drawn
                // path rather than a thin, easy-to-miss stroke.
                if (polylines[t.vehicle_id]) {
                    polylines[t.vehicle_id].glow.setLatLngs(trail);
                    polylines[t.vehicle_id].line.setLatLngs(trail);
                } else if (trail.length > 1) {
                    polylines[t.vehicle_id] = {
                        glow: L.polyline(trail, { color: '#3b82f6', weight: 8, opacity: 0.15, lineCap: 'round', lineJoin: 'round' }).addTo(map),
                        line: L.polyline(trail, { color: '#3b82f6', weight: 3, opacity: 0.75, lineCap: 'round', lineJoin: 'round' }).addTo(map),
                    };
                }
            });

            // Drop markers/trails for vehicles that are no longer active
            // (trip ended, or the session went stale and was purged).
            Object.keys(markers).forEach(function(vehicleId) {
                if (!seenIds[vehicleId]) {
                    map.removeLayer(markers[vehicleId]);
                    delete markers[vehicleId];
                    delete prevLatLngs[vehicleId];
                    delete headings[vehicleId];
                    if (polylines[vehicleId]) {
                        map.removeLayer(polylines[vehicleId].glow);
                        map.removeLayer(polylines[vehicleId].line);
                        delete polylines[vehicleId];
                    }
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
