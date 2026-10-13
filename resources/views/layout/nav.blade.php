<nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0 sticky-top">
  <ul class="navbar-nav align-items-center">
    <li class="nav-item">
      <a class="nav-link toggle-menu-btn" data-widget="pushmenu" href="#" role="button" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-flex align-items-center nav-title-li">
        <h5 class="nav-page-title-text mb-0 text-truncate">
            @yield('nav-title', 'VMIS')
        </h5>
    </li>
  </ul>

  <ul class="navbar-nav ml-auto align-items-center">
    @if(View::hasSection('nav-actions'))
    <li class="nav-item nav-actions-li d-flex align-items-center mr-3">
        <div class="nav-actions-container">
            @yield('nav-actions')
        </div>
    </li>
    @endif

    @auth
    <li class="nav-item dropdown mr-2">
      <a class="nav-link btn-nav-icon position-relative d-flex align-items-center justify-content-center" data-toggle="dropdown" href="#" id="notif-bell-trigger" aria-expanded="false" title="Notifications">
        <i class="fas fa-bell"></i>
        <span id="notif-badge" class="badge badge-danger navbar-badge d-none" style="top: 2px; right: 2px; font-size: 10px;">0</span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right premium-dropdown border-0 shadow-lg animate fade-in p-0" style="width: 360px; max-height: 75vh; overflow-y: auto;">
        <div class="dropdown-header-premium d-flex align-items-center justify-content-between p-3">
          <span class="font-weight-bold text-white">Notifications</span>
          <a href="#" id="notif-mark-all" class="text-white small">Mark all read</a>
        </div>
        <div id="notif-list">
          <div class="text-center text-muted p-4 small" id="notif-empty">No notifications.</div>
        </div>
      </div>
    </li>

    <li class="nav-item dropdown">
      <a class="nav-link profile-dropdown-trigger d-flex align-items-center" data-toggle="dropdown" href="#" aria-expanded="false">
        <div class="user-avatar-sm mr-2">
            <img src="{{ Auth::user()->profile_image ? asset(Auth::user()->profile_image) : asset('dist/img/user2-160x160.jpg') }}" 
                 class="img-circle header-profile-img" alt="User" width="32" height="32">
        </div>
        <i class="fas fa-chevron-down profile-chevron"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right premium-dropdown border-0 shadow-lg animate fade-in">
        <div class="dropdown-header-premium text-center p-3">
          <span class="d-block font-weight-bold text-truncate text-white">
            {{ Auth::user()->rank }} {{ Auth::user()->firstname }} {{ substr(Auth::user()->middlename, 0, 1) }}. {{ Auth::user()->lastname }} {{ Auth::user()->qlfr ?? "" }}
          </span>
          <small class="text-muted text-uppercase tracking-wider font-size-10">{{ Auth::user()->account_type }}</small>
        </div>
        <div class="dropdown-divider m-0"></div>
        
        @if(Route::has('profile.index'))
        <a href="{{ route('profile.index') }}" class="dropdown-item py-2.5">
          <i class="fas fa-user-circle mr-2 text-primary-blue-token"></i> Profile Settings
        </a>
        <div class="dropdown-divider m-0"></div>
        @endif
        <a href="#" class="dropdown-item text-danger py-2.5" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
          <i class="fas fa-sign-out-alt mr-2"></i> {{ __('Logout') }}
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
          @csrf
        </form>
      </div>
    </li>
    @endauth
  </ul>
</nav>

<aside id="main-pdm-sidebar" class="main-sidebar sidebar-dark-primary border-right-0 elevation-0">
  <a href="#" class="brand-link border-bottom-0 d-flex align-items-center">
    <div class="brand-image-container">
        <img src="{{ asset('dist/img/kasurog.png') }}" alt="Logo" class="brand-logo-img">
    </div>
    <span class="brand-text font-weight-bold ml-2">VMIS</span>
  </a>

  <div class="sidebar px-3">
    @auth
    <div class="user-panel-card mt-3 mb-4 p-3 rounded text-center">
        <div class="image-wrapper mb-2">
            <img src="{{ Auth::user()->profile_image ? asset(Auth::user()->profile_image) : asset('dist/img/user2-160x160.jpg') }}" 
                 class="img-circle profile-img-main" alt="User Image">
        </div>
        <div class="info-wrapper">
            <p class="mb-0 user-display-name text-truncate">{{ Auth::user()->rank }} {{ Auth::user()->firstname }} {{ substr(Auth::user()->middlename, 0, 1) }}. {{ Auth::user()->lastname }}</p>
            <small class="text-uppercase user-role-badge">{{ Auth::user()->account_type }}</small>
        </div>
    </div>
    @endauth

    @php
        // A DRIVER's nav is deliberately minimal — Dashboard, Scan QR, and Trip
        // Logs only. Every section/link hidden here is backed by a matching
        // abort(403) inside its own controller (see VehicleController,
        // MaintenanceController, RepairController, DriverController,
        // VehicleTypeController, UserController), so this is UX polish, not
        // the actual access control.
        $__navRole = auth()->check() ? strtoupper(trim(Auth::user()->account_type)) : null;
        $__isDriver = $__navRole === 'DRIVER';
    @endphp
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-flat" data-widget="treeview" role="menu">

        <!-- GENERAL -->
        <li class="nav-header small text-muted text-uppercase mb-1 tracking-wider">General</li>
        <li class="nav-item">
          <a href="{{ route('dashboard') }}" class="nav-link {{ (request()->routeIs('dashboard')) ? 'active' : '' }}">
            <i class="nav-icon fas fa-th-large"></i>
            <p>Dashboard</p>
          </a>  
        </li>

        <!-- VEHICLE MANAGEMENT -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">Vehicle Management</li>
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('vehicles.index') }}" class="nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-car-side"></i>
                <p>Vehicle Inventory</p>
            </a>
        </li>
        @endunless
        <li class="nav-item">
            <a href="{{ route('scan.index') }}" class="nav-link {{ request()->routeIs('scan.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-qrcode"></i>
                <p>Scan QR Code</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('trip-logs.index') }}" class="nav-link {{ request()->routeIs('trip-logs.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-route"></i>
                <p>Trip Logs</p>
            </a>
        </li>
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('live-monitoring.index') }}" class="nav-link {{ request()->routeIs('live-monitoring.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-satellite-dish"></i>
                <p>Live Monitoring</p>
            </a>
        </li>
        @endunless
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('trip-registrations.index') }}" class="nav-link {{ request()->routeIs('trip-registrations.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-calendar-check"></i>
                <p>Trip Registrations</p>
            </a>
        </li>
        @endunless
        <li class="nav-item">
            <a href="{{ route('fuel-logs.index') }}" class="nav-link {{ request()->routeIs('fuel-logs.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-gas-pump"></i>
                <p>Fuel Monitoring</p>
            </a>
        </li>
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('maintenance.index') }}" class="nav-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-tools"></i>
                <p>Maintenance &amp; PMS</p>
            </a>
        </li>
        @endunless
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('repairs.index') }}" class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-wrench"></i>
                <p>Repairs</p>
            </a>
        </li>
        @endunless
        @unless($__isDriver)
        <li class="nav-item">
            <a href="{{ route('accidents.index') }}" class="nav-link {{ request()->routeIs('accidents.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-car-crash"></i>
                <p>Accident Records</p>
            </a>
        </li>
        @endunless

        @unless($__isDriver)
        <!-- PERSONNEL -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">Personnel</li>
        <li class="nav-item">
            <a href="{{ route('drivers.index') }}" class="nav-link {{ request()->routeIs('drivers.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-id-card"></i>
                <p>Driver Management</p>
            </a>
        </li>
        {{-- Driver Assignments used to be its own nav link here — it's now
             folded into Driver Management itself (vehicle assignment is a
             column + actions on that page), so there's nothing separate
             to link to anymore. --}}

        <!-- SYSTEM SETTINGS -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">System Settings</li>
        <li class="nav-item">
            <a href="{{ route('vehicle-types.index') }}" class="nav-link {{ request()->routeIs('vehicle-types.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-tags"></i>
                <p>Vehicle Types</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-users-cog"></i>
                <p>System Users</p>
            </a>
        </li>
        @if(in_array($__navRole, ['SUPER ADMINISTRATOR', 'ADMINISTRATOR']))
        <li class="nav-item">
            <a href="{{ route('units.index') }}" class="nav-link {{ (request()->routeIs('units.*') || request()->routeIs('stations.*')) ? 'active' : '' }}">
                <i class="nav-icon fas fa-map-marker-alt"></i>
                <p>Units &amp; Stations</p>
            </a>
        </li>
        @endif
        @if($__navRole === 'SUPER ADMINISTRATOR')
        <li class="nav-item">
            <a href="{{ route('account-types.index') }}" class="nav-link {{ request()->routeIs('account-types.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-user-tag"></i>
                <p>Account Types</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('activity-logs.index') }}" class="nav-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-shield-alt"></i>
                <p>Activity Logs</p>
            </a>
        </li>
        @endif
        @endunless

      </ul>
    </nav>
  </div>
</aside>

@auth
<script>
// Notification Center (header bell) — vanilla JS, no jQuery dependency, so it
// doesn't care whether this fires before or after AdminLTE's own scripts;
// only Bootstrap4's dropdown toggle (data-toggle="dropdown") is needed, and
// that's wired via a plain delegated click already handled by Bootstrap's
// own JS once it loads. See NotificationController (web) for the endpoints.
document.addEventListener('DOMContentLoaded', function () {
    var badge = document.getElementById('notif-badge');
    var list = document.getElementById('notif-list');
    var trigger = document.getElementById('notif-bell-trigger');
    var markAllBtn = document.getElementById('notif-mark-all');
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var loaded = false;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function renderAlerts(alerts) {
        if (!alerts.length) {
            list.innerHTML = '<div class="text-center text-muted p-4 small">No notifications.</div>';
            return;
        }

        list.innerHTML = alerts.map(function (a) {
            var severityColor = a.severity === 'overdue' ? '#dc3545' : '#f0ad4e';
            var unreadDot = a.is_read ? '' : '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:' + severityColor + ';margin-right:6px;flex:none;"></span>';
            var bg = a.is_read ? '' : 'background:rgba(59,130,246,0.06);';

            return '<a href="#" class="notif-item d-flex p-3 border-bottom" data-id="' + a.id + '" data-link="' + (a.link || '') + '" style="' + bg + 'text-decoration:none;color:inherit;">'
                + '<i class="fas ' + escapeHtml(a.icon) + ' mt-1 mr-2" style="color:' + severityColor + ';width:16px;"></i>'
                + '<div style="min-width:0;">'
                + '<div class="font-weight-bold small text-truncate">' + unreadDot + escapeHtml(a.title) + '</div>'
                + '<div class="text-muted small" style="white-space:normal;">' + escapeHtml(a.body) + '</div>'
                + '<div class="text-muted" style="font-size:11px;">' + escapeHtml(a.created_at) + '</div>'
                + '</div>'
                + '</a>';
        }).join('');
    }

    function loadAlerts() {
        fetch('{{ route('notifications.index') }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) { return; }
                loaded = true;
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
                renderAlerts(data.alerts);
            })
            .catch(function () { /* silent — bell just stays empty/stale on a transient failure */ });
    }

    function markRead(id) {
        fetch('/notifications/' + id + '/read', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        }).catch(function () {});
    }

    trigger?.addEventListener('click', function () {
        if (!loaded) { loadAlerts(); }
    });

    list.addEventListener('click', function (e) {
        var item = e.target.closest('.notif-item');
        if (!item) { return; }
        e.preventDefault();
        markRead(item.getAttribute('data-id'));
        var link = item.getAttribute('data-link');
        if (link) { window.location.href = link; }
    });

    markAllBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        fetch('{{ route('notifications.read-all') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        }).then(loadAlerts).catch(function () {});
    });

    // Refresh the unread count in the background every 5 minutes so the
    // badge doesn't go stale on a page someone leaves open all day.
    loadAlerts();
    setInterval(loadAlerts, 5 * 60 * 1000);
});
</script>
@endauth
