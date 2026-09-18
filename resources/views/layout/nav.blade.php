<nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0 sticky-top">
  <ul class="navbar-nav align-items-center">
    <li class="nav-item">
      <a class="nav-link toggle-menu-btn" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars-staggered"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
        <h5 class="nav-page-title-text mb-0">
            @yield('nav-title', 'VMIS')
        </h5>
    </li>
  </ul>

  <ul class="navbar-nav ml-auto align-items-center">
    @if(View::hasSection('nav-actions'))
    <li class="nav-item d-flex align-items-center mr-3">
        <div class="nav-actions-container">
            @yield('nav-actions')
        </div>
    </li>
    @endif

    @auth
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

        <!-- FLEET MANAGEMENT -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">Fleet Management</li>
        <li class="nav-item">
            <a href="{{ route('vehicles.index') }}" class="nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-car-side"></i>
                <p>Vehicle Inventory</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('scan.index') }}" class="nav-link {{ request()->routeIs('scan.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-qrcode"></i>
                <p>Scan QR Code</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('maintenance.index') }}" class="nav-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-tools"></i>
                <p>Maintenance &amp; PMS</p>
            </a>
        </li>

        <!-- PERSONNEL -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">Personnel</li>
        <li class="nav-item">
            <a href="{{ route('drivers.index') }}" class="nav-link {{ request()->routeIs('drivers.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-id-card"></i>
                <p>Driver Management</p>
            </a>
        </li>

        <!-- SYSTEM SETTINGS -->
        <li class="nav-header small text-muted text-uppercase mb-1 mt-3 tracking-wider">System Settings</li>
        <li class="nav-item">
            <a href="{{ route('vehicle-types.index') }}" class="nav-link {{ request()->routeIs('vehicle-types.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-tags"></i>
                <p>Vehicle Types</p>
            </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>
