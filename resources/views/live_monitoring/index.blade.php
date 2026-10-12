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

  .vehicle-marker-icon { background: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.45); }
  .vehicle-marker-icon.is-stale { background: #94a3b8; }
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

    const markers = {};   // vehicle_id -> L.marker
    const polylines = {}; // vehicle_id -> L.polyline
    let hasFitOnce = false;

    function markerIcon(stale) {
        return L.divIcon({ className: '', html: '<div class="vehicle-marker-icon' + (stale ? ' is-stale' : '') + '"></div>', iconSize: [16, 16] });
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
        $.getJSON("{{ route('live-monitoring.active-trips') }}", function(res) {
            if (!res || !res.success) return;
            const trips = res.trips || [];
            const seenIds = {};

            trips.forEach(function(t) {
                seenIds[t.vehicle_id] = true;
                const latLng = [t.latitude, t.longitude];

                if (markers[t.vehicle_id]) {
                    markers[t.vehicle_id].setLatLng(latLng).setIcon(markerIcon(t.stale)).setPopupContent(popupHtml(t));
                } else {
                    markers[t.vehicle_id] = L.marker(latLng, { icon: markerIcon(t.stale) }).addTo(map).bindPopup(popupHtml(t));
                }

                const trail = (t.route_points || [])
                    .filter(function(p) { return typeof p.lat === 'number' && typeof p.lng === 'number'; })
                    .map(function(p) { return [p.lat, p.lng]; });

                if (polylines[t.vehicle_id]) {
                    polylines[t.vehicle_id].setLatLngs(trail);
                } else if (trail.length > 1) {
                    polylines[t.vehicle_id] = L.polyline(trail, { color: '#3b82f6', weight: 3, opacity: 0.55 }).addTo(map);
                }
            });

            // Drop markers/trails for vehicles that are no longer active
            // (trip ended, or the session went stale and was purged).
            Object.keys(markers).forEach(function(vehicleId) {
                if (!seenIds[vehicleId]) {
                    map.removeLayer(markers[vehicleId]);
                    delete markers[vehicleId];
                    if (polylines[vehicleId]) {
                        map.removeLayer(polylines[vehicleId]);
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
        });
    }

    refresh();
    setInterval(refresh, 10000);
});
</script>
@endsection
