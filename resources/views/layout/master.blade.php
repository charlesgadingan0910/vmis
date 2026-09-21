<!DOCTYPE html>
<html lang="en">

<head>
  @yield('title')
  <meta name="csrf-token" content="{{ csrf_token() }}">

  @include('layout.header')

  <!-- ============================================================ -->
  <!-- GLOBAL THEME SYSTEM: Prevent Flash-of-Unthemed-Content (FOUC) -->
  <!-- This script runs synchronously before any paint occurs       -->
  <!-- ============================================================ -->
  <script>
    (function () {
      // Force light mode
      var isDark = false;
      if (isDark) {
        document.documentElement.classList.add('dark-mode-pending');
      }
    })();
  </script>

  <style>
    :root {
    color-scheme: light;
    --cc-bg: #f8fafc;
    --cc-surface: rgba(255, 255, 255, 0.85);
    --cc-border: #e2e8f0;
    --cc-text-primary: #0f172a;
    --cc-text-secondary: #64748b;
    --cc-accent: #dc3545;
    --cc-accent-hover: #b91c1c;
    
    /* Upgraded UI Premium Tokens */
    --sidebar-bg: #1e293b;
    --sidebar-hover: #334155;
    --primary-blue-token: #3b82f6;
    --accent-card-bg: rgba(255, 255, 255, 0.04);
    --accent-card-border: rgba(255, 255, 255, 0.08);
    --transition-smooth: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    --header-height: 65px;
  }

  body.dark-mode {
    --cc-bg: #090d16;
    --cc-surface: rgba(26, 29, 35, 0.85);
    --cc-border: #1e293b;
    --cc-text-primary: #f8fafc;
    --cc-text-secondary: #94a3b8;
    --cc-accent: #ef4444;
  }

  /* Base Optimization */
  *, *::before, *::after {
    transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
  }

  /* Header Navbar Upgrades */
  .main-header.navbar {
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
    background: var(--cc-surface) !important;
    border-bottom: 1px solid var(--cc-border) !important;
    height: calc(var(--header-height) + env(safe-area-inset-top, 0px));
    padding: env(safe-area-inset-top, 0px) 1.5rem 0;
    flex-wrap: nowrap;
  }

  /* The title sits between a fixed-size toggle button and a fixed-size
     actions/profile area — it needs to be the one thing that shrinks and
     truncates with an ellipsis on a narrow phone, rather than pushing the
     profile menu or action buttons off-screen. */
  .main-header .navbar-nav:first-child {
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
  }
  .nav-title-li {
    min-width: 0;
    overflow: hidden;
  }
  .nav-page-title-text {
    font-weight: 700;
    letter-spacing: -0.5px;
    color: var(--cc-text-primary);
    margin-left: 0.75rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Toggle button: previously used an icon class (fa-bars-staggered) that
     doesn't exist in every Font Awesome build, so it rendered with nothing
     visible at all — fa-bars is universally supported. This also gives it a
     real visual affordance (background + border on interaction) and a
     touch target at least 40x40px, which is the practical minimum for a
     reliably tappable icon-only control on a phone. */
  .toggle-menu-btn {
    color: var(--cc-text-primary) !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    min-height: 40px;
    padding: 0.5rem 0.65rem;
    border-radius: 9px;
    background: rgba(15, 23, 42, 0.05);
    border: 1px solid transparent;
  }
  .toggle-menu-btn i {
    font-size: 17px !important;
    line-height: 1 !important;
  }
  .toggle-menu-btn:hover,
  .toggle-menu-btn:focus,
  .toggle-menu-btn:active {
    background-color: rgba(15, 23, 42, 0.09);
    border-color: var(--cc-border);
    color: var(--cc-text-primary) !important;
  }

  /* Premium Sidebar Execution */
  .main-sidebar {
    background: var(--sidebar-bg) !important;
    border-right: 1px solid rgba(255, 255, 255, 0.05) !important;
    will-change: width;
    transition: var(--transition-smooth) !important;
  }

  .brand-link {
    background-color: var(--sidebar-bg) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 1.25rem 1rem !important;
    height: 65px;
  }
  .brand-image-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
  }
  .brand-logo-img {
    max-height: 32px;
    width: auto;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
  }

  /* Sidebar Profile Card Configuration */
  .user-panel-card {
    background: var(--accent-card-bg);
    border: 1px solid var(--accent-card-border);
    border-radius: 12px !important;
    backdrop-filter: blur(4px);
    transition: var(--transition-smooth);
  }
  .profile-img-main {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border: 2px solid var(--primary-blue-token);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    transition: var(--transition-smooth);
  }
  .user-display-name {
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: -0.2px;
  }
  .user-role-badge {
    color: var(--primary-blue-token);
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 0.75px;
    opacity: 0.9;
  }

  /* Sidebar Navigation Items */
  .nav-sidebar .nav-item .nav-link {
    border-radius: 8px;
    margin-bottom: 4px;
    padding: 0.75rem 1rem;
    color: #94a3b8 !important;
    transition: var(--transition-smooth);
  }
  .nav-sidebar .nav-item .nav-link:hover {
    background-color: var(--sidebar-hover) !important;
    color: #ffffff !important;
  }
  .nav-sidebar .nav-item .nav-link.active {
    background-color: var(--primary-blue-token) !important;
    color: #ffffff !important;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35) !important;
  }
  .nav-sidebar .nav-item .nav-link .nav-icon {
    margin-right: 0.75rem;
    font-size: 1.1rem;
    color: inherit !important;
  }

  /* Premium User Dropdown Structuring */
  .premium-dropdown {
    border-radius: 12px !important;
    overflow: hidden;
    padding: 0;
    min-width: 240px;
    background: #ffffff !important;
  }
  body.dark-mode .premium-dropdown {
    background: #1e293b !important;
    border: 1px solid var(--cc-border) !important;
  }
  .dropdown-header-premium {
    background: linear-gradient(135deg, #1e293b, #0f172a);
  }
  .profile-chevron {
    font-size: 11px;
    color: var(--cc-text-secondary);
    transition: transform 0.2s ease;
  }
  .dropdown.show .profile-chevron {
    transform: rotate(180deg);
  }
  .header-profile-img {
    border: 1.5px solid var(--cc-border);
    padding: 1px;
    background: #fff;
  }
  .py-2.5 { padding-top: 0.65rem !important; padding-bottom: 0.65rem !important; }
  .tracking-wider { letter-spacing: 0.05em; }
  .font-size-10 { font-size: 10px; }

  /* Micro-animations */
  .animate { animation-duration: 0.2s; animation-fill-mode: both; }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .fade-in { animation-name: fadeIn; }

  /* --- CRITICAL COLLAPSED SIDEBAR (MINI STATE) OVERRIDES --- */
  .sidebar-collapse .user-panel-card {
    background: transparent !important;
    border-color: transparent !important;
    padding: 0.5rem 0 !important;
  }
  .sidebar-collapse .info-wrapper,
  .sidebar-collapse .brand-text,
  .sidebar-collapse .sidebar .nav-header {
    display: none !important;
  }
  .sidebar-collapse .profile-img-main {
    width: 36px !important;
    height: 36px !important;
    box-shadow: none;
  }
  .sidebar-collapse .image-wrapper {
    margin-bottom: 0 !important;
  }

  /* Smooth expansion interactions for sidebar-mini */
  .sidebar-mini.sidebar-collapse .main-sidebar:hover {
    width: 250px !important;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar:hover .info-wrapper,
  .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-text {
    display: block !important;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar .nav-header {
    display: block !important;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar:hover .user-panel-card {
    background: var(--accent-card-bg) !important;
    border-color: var(--accent-card-border) !important;
    padding: 1rem !important;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar:hover .profile-img-main {
    width: 56px !important;
    height: 56px !important;
  }

  /* ============================================================ */
  /* PREMIUM NAV ACTIONS STYLING                                 */
  /* ============================================================ */

  .nav-actions-container {
      display: flex;
      align-items: center;
      gap: 8px; /* Standard premium spacing between multiple actions */
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
  }
  .nav-actions-container::-webkit-scrollbar { display: none; }

  /* Base style for premium navbar action buttons */
  .btn-nav-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      border: 1px solid var(--cc-border);
      background: var(--cc-surface);
      color: var(--cc-text-primary);
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      height: 38px;
      white-space: nowrap;
      flex: none;
  }

  /* Subtle Hover states mimicking modern SaaS architectures */
  .btn-nav-action:hover {
      background: var(--cc-surface-hover);
      color: var(--cc-text-primary);
      border-color: #cbd5e1;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
  }

  body.dark-mode .btn-nav-action:hover {
      border-color: #475569;
  }

  /* Primary/Accent Variant for call-to-actions (e.g., "New Complaint") */
  .btn-nav-action-primary {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
      border: none !important;
      color: #ffffff !important;
      box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
  }

  .btn-nav-action-primary:hover {
      background: linear-gradient(135deg, #2563eb, #1e40af) !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35) !important;
      transform: translateY(-1px);
  }

  /* Icon-only circular button option for secondary tools (e.g., Notifications, Help) */
  .btn-nav-icon {
      width: 38px;
      height: 38px;
      padding: 0;
      border-radius: 50%;
      color: var(--cc-text-secondary);
  }
  .btn-nav-icon:hover {
      color: var(--cc-text-primary);
  }

  /* Quick Badge Alignment inside action elements */
  .nav-action-badge {
      font-size: 11px;
      padding: 0.25em 0.6em;
      border-radius: 6px;
      font-weight: 700;
  }

  .modern-badge {
      background-color: #ff4d4d; /* Softer, modern red */
      color: white;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 12px; /* Perfect pill shape */
      display: inline-block;
      line-height: 1;
      text-align: center;
      margin-left: auto; /* Pushes it to the right */
  }

  /* ============================================================ */
  /* MOBILE POLISH                                                */
  /* ============================================================ */
  @media (max-width: 576px) {
    :root { --header-height: 56px; }
    .main-header.navbar { padding-left: 0.85rem; padding-right: 0.85rem; }
    .nav-page-title-text { font-size: 0.92rem; margin-left: 0.5rem; }
    .btn-nav-action { padding: 0.5rem 0.8rem; font-size: 0.8rem; }
    .content-wrapper { padding-bottom: env(safe-area-inset-bottom, 0px); }
  }

  /* When the sidebar opens as a mobile overlay (AdminLTE's own pushmenu
     behavior below its lg breakpoint), it should read as a proper drawer —
     comfortable width, real shadow separating it from the page behind it —
     rather than the desktop hover-to-expand mini-sidebar treatment, which
     doesn't apply on a touchscreen since there's no hover. */
  @media (max-width: 991.98px) {
    /* Scoped to .sidebar-open specifically — NOT a blanket .main-sidebar rule.
       AdminLTE hides the sidebar by default via `margin-left:-250px`, exactly
       matched to its native 250px width. Widening .main-sidebar unconditionally
       (as an earlier version of this rule did) breaks that pairing: a 270px
       sidebar offset by only -250px leaves 20px permanently visible even while
       "closed" — that's the overlap/sliver bug. Only touching the OPEN state
       keeps the closed state's native hide mechanism completely untouched.
       See CLASS_NAME_OPEN$3 in adminlte.js's PushMenu widget for how/when
       sidebar-open actually gets added — confirmed directly against source. */
    .sidebar-open .main-sidebar {
      width: 270px !important;
      box-shadow: 0 0 40px rgba(0, 0, 0, 0.35);
    }
    /* AdminLTE's own #sidebar-overlay already provides a working tap-to-close
       backdrop (shown via the sidebar-open class its PushMenu widget adds) —
       just deepening the default tint (10% black) so it reads as a deliberate
       modal-style backdrop instead of a faint, almost-accidental haze. */
    #sidebar-overlay {
      background-color: rgba(15, 23, 42, 0.5) !important;
      backdrop-filter: blur(1px);
    }
  }
  </style>

  @yield('css')
  {{-- <link rel="manifest" href="{{asset('manifest.json')}}"> --}}
  <meta name="theme-color" content="#0d6efd">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">

  @vite(['resources/js/app.js'])
</head>

<!-- FIX: Added id="e-reklamo-body" so the Global Theme Manager successfully selects this tag -->
<body id="e-reklamo-body">

  <div class="wrapper">
    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center">
      <img class="animation__shake" src="{{ asset('dist/img/kasurogLogo.png')}}" alt="AdminLTELogo" height="60"
        width="60">
    </div>

    <script>
      // 🚀 AGGRESSIVE PRELOADER REMOVAL
      (function() {
        var hideLoader = function() {
          var loader = document.querySelector('.preloader');
          if (loader && loader.style.display !== 'none') {
            loader.style.opacity = '0';
            setTimeout(function() { loader.style.display = 'none'; }, 300);
            console.log('Preloader removed early for speed.');
          }
        };
        document.addEventListener('DOMContentLoaded', hideLoader);
        setTimeout(hideLoader, 1500);
      })();
    </script>

    @include('layout.nav')

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      
      <!-- FIX: Re-introduced breadcrumb rendering logic -->
      @yield('breadcrumb')

      <!-- Main content -->
      <section class="content">
        @yield('content')
      </section>
      <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    
    <footer class="main-footer">
      <strong>Copyright &copy; 2026 <a href="">Kali Ada | RITO5</a>.</strong>
      All rights reserved.
      <div class="float-right d-none d-sm-inline-block"></div>
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
      <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
  </div>
  <!-- ./wrapper -->

  @include('layout.script')

  @yield('script')
  
  <script>
    // ============================================================
    // GLOBAL THEME MANAGER
    // ============================================================
    (function () {
      var body = document.getElementById('e-reklamo-body');
      var isDark = false;

      if (isDark) {
        body.classList.add('dark-mode');
      } else {
        body.classList.remove('dark-mode');
      }

      function syncSidebar(dark) {
        var aside = document.getElementById('main-pdm-sidebar');
        if (aside) {
          aside.classList.remove('sidebar-light-primary');
          aside.classList.add('sidebar-dark-primary');
        }
      }

      window.toggleGlobalTheme = function () {
        console.log("Theme toggling is disabled.");
      };

      document.addEventListener('DOMContentLoaded', function () {
        syncSidebar(isDark);
      });
    })();

    document.addEventListener('DOMContentLoaded', function() {
      if ("serviceWorker" in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (registrations) {
          for (var i = 0; i < registrations.length; i++) {
            var registration = registrations[i];
            registration.unregister();
            console.log("Service Worker Unregistered forcibly.");
          }
        });
        
        if ('caches' in window) {
            caches.keys().then(function(names) {
                for (var j = 0; j < names.length; j++) {
                    caches.delete(names[j]);
                }
            });
        }
      }

      setTimeout(function() {
        var preloader = document.querySelector('.preloader');
        if (preloader && preloader.style.display !== 'none') {
          preloader.style.display = 'none';
        }
      }, 10000);
    });
  </script>

</body>
</html>
