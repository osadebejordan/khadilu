<!DOCTYPE html>
<html lang="en">
<head>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('khadilu-theme') || 'theme-business-light';
      document.documentElement.className = savedTheme;
    })();
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Dashboard' }} — Khadilu Phone Hub</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
  
  <!-- Third Party Stacks -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  @stack('styles')
</head>
<body>

  <div id="page-loader" class="global-loader">
    <div class="spinner"></div>
  </div>

  <script>
    const hideLoader = () => {
      const loader = document.getElementById('page-loader');
      if (loader) {
        loader.style.opacity = '0';
        loader.style.pointerEvents = 'none';
        setTimeout(() => { loader.style.display = 'none'; }, 400);
      }
    };
    if (document.readyState === 'complete') hideLoader();
    else document.addEventListener('DOMContentLoaded', hideLoader);
    setTimeout(hideLoader, 3000);
  </script>

  <nav class="sidebar">
    <div class="sidebar-top">
      <div class="sidebar-logo">
        <div class="logo-icon">📱</div>
        <div class="logo-text">
          <div class="logo-name">Khadilu</div>
          <div class="logo-sub">Phone Hub</div>
        </div>
      </div>
    </div>

    <ul class="nav-links">
      <li class="nav-section">Main</li>
      <li>
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-section">Inventory</li>
      <li>
        <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          <span>Inventory</span>
        </a>
      </li>
      <li>
        <a href="{{ route('inventory.bulk_stock') }}" class="nav-link {{ request()->routeIs('inventory.bulk_stock') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Stock Entry</span>
        </a>
      </li>
      <li>
        <a href="{{ route('inventory.bulk_prices') }}" class="nav-link {{ request()->routeIs('inventory.bulk_prices') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>
          <span>Bulk Pricing</span>
        </a>
      </li>
      @if(auth()->user()->isAdmin())
      <li>
        <a href="{{ route('inventory.low_stock') }}" class="nav-link {{ request()->routeIs('inventory.low_stock') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <span>Low Stock Report</span>
        </a>
      </li>
      <li>
        <a href="{{ route('inventory.analyze') }}" class="nav-link {{ request()->routeIs('inventory.analyze') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <line x1="18" y1="20" x2="18" y2="10"></line>
            <line x1="12" y1="20" x2="12" y2="4"></line>
            <line x1="6" y1="20" x2="6" y2="14"></line>
          </svg>
          <span>Stock Valuation</span>
        </a>
      </li>
      @endif

      <li class="nav-section">Operations</li>
      <li>
        <a href="{{ route('sales.pos') }}" class="nav-link {{ request()->routeIs('sales.pos') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <span>Record Sale (POS)</span>
        </a>
      </li>
      <li>
        <a href="{{ route('repairs.index') }}" class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
          </svg>
          <span>Repairs Management</span>
        </a>
      </li>
      <li>
        <a href="{{ route('returns.index') }}" class="nav-link {{ request()->routeIs('returns.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          <span>Process Return</span>
        </a>
      </li>
      <li>
        <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Customers & Debts</span>
        </a>
      </li>

      <li class="nav-section">Reports & System</li>
      <li>
        <a href="{{ route('sales.history') }}" class="nav-link {{ request()->routeIs('sales.history') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Sales History</span>
        </a>
      </li>
      @if(auth()->user()->isAdmin())
      <li>
        <a href="{{ route('reports.closing') }}" class="nav-link {{ request()->routeIs('reports.closing') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          <span>Daily Closing</span>
        </a>
      </li>
      <li>
        <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
            <line x1="12" y1="4" x2="12" y2="20"></line>
            <circle cx="12" cy="12" r="4"></circle>
          </svg>
          <span>Expense Tracker</span>
        </a>
      </li>
      <li>
        <a href="{{ route('attributes.index') }}" class="nav-link {{ request()->routeIs('attributes.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
          </svg>
          <span>Product Attributes</span>
        </a>
      </li>
      <li>
        <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
          </svg>
          <span>Audit Log</span>
        </a>
      </li>
      <li>
        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
          <span>Manage Users</span>
        </a>
      </li>
      @endif
    </ul>

    <div class="sidebar-theme-selector" style="padding: 12px 16px; border-top: 1px solid var(--border);">
      <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-dimmer); letter-spacing: 0.1em; margin-bottom: 8px;">Theme</div>
      <select id="sidebarThemeSelector" class="theme-select">
        <option value="theme-business-light">💼 Emerald Prestige</option>
        <option value="theme-corporate-dark">🛠️ Midnight Amber</option>
        <option value="theme-cyber-original">👾 Cyber Command</option>
      </select>
    </div>

    <div class="sidebar-bottom">
      <a href="{{ route('profile') }}" class="sidebar-user" title="My Profile">
        <div class="user-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
        <div class="user-info">
          <div class="user-name">{{ auth()->user()->full_name }}</div>
          <div class="user-role">{{ ucfirst(auth()->user()->role) }}</div>
        </div>
      </a>
      <div style="padding: 0 12px;">
        <form action="{{ route('logout') }}" method="POST" id="logout-form">
          @csrf
          <button type="submit" class="logout-btn" title="Logout" style="width:100%; border:none; background:transparent; cursor:pointer;" onclick="return confirm('Are you sure you want to logout?')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon" style="margin:0; opacity:0.9;">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Sign Out</span>
          </button>
        </form>
      </div>
    </div>
  </nav>

  <main class="main-content">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <div class="page-header">
      <div class="header-left">
        <button id="mobileToggle" class="mobile-toggle">☰</button>
        <h2 class="page-title">{{ $title ?? 'Dashboard' }}</h2>
      </div>
      <div class="page-meta">{{ date('l, d F Y') }}</div>
    </div>

    @if(session('success'))
      <div class="toast alert-success" id="app-toast">
        <div>{{ session('success') }}</div>
        <div class="close-toast" onclick="this.parentElement.remove()">✕</div>
      </div>
    @endif

    @if(session('error'))
      <div class="toast alert-danger" id="app-toast">
        <div>{{ session('error') }}</div>
        <div class="close-toast" onclick="this.parentElement.remove()">✕</div>
      </div>
    @endif

    @yield('content')
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const themeSelector = document.getElementById('sidebarThemeSelector');
      if (themeSelector) {
        themeSelector.value = localStorage.getItem('khadilu-theme') || 'theme-business-light';
        themeSelector.addEventListener('change', (e) => {
          const newTheme = e.target.value;
          document.documentElement.className = newTheme;
          localStorage.setItem('khadilu-theme', newTheme);
        });
      }

      const mobileToggle = document.getElementById('mobileToggle');
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.getElementById('sidebarOverlay');

      if (mobileToggle && sidebar && overlay) {
        mobileToggle.addEventListener('click', () => {
          sidebar.classList.toggle('active');
          overlay.classList.toggle('active');
        });
        overlay.addEventListener('click', () => {
          sidebar.classList.remove('active');
          overlay.classList.remove('active');
        });
      }
    });
  </script>

  @stack('scripts')
</body>
</html>
