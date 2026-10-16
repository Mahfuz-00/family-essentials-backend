<!DOCTYPE html>
<html class="h-full" lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Family Essentials — Web Dashboard Portal Directory</title>
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace'],
          },
          colors: {
            brand: {
              primary: '#7C9082',
              secondary: '#4A7C59',
              accent: '#E07A5F',
              slate: '#2B2D42',
              muted: '#6C757D',
              surface: '#FFFFFF',
              bg: '#FDFCF7',
              success: '#81B29A',
              warning: '#F4A261',
              danger: '#E76F51',
              sageSoft: '#EBF0ED',
              forestSoft: '#E4EDE7',
              coralSoft: '#FCECE7',
            }
          }
        }
      }
    };
  </script>
</head>
<body class="min-h-screen bg-brand-bg text-brand-slate font-sans antialiased flex flex-col justify-between selection:bg-brand-primary/20 selection:text-brand-secondary">

  <!-- Subtle Ambient Glow -->
  <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-brand-primary/10 blur-3xl"></div>
    <div class="absolute top-1/3 -left-48 w-[500px] h-[500px] rounded-full bg-brand-secondary/10 blur-3xl"></div>
    <div class="absolute bottom-10 right-1/4 w-80 h-80 rounded-full bg-brand-accent/5 blur-2xl"></div>
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#7C908208_1px,transparent_1px),linear-gradient(to_bottom,#7C908208_1px,transparent_1px)] bg-[size:32px_32px]"></div>
  </div>

  <!-- Top Nav Minimal Bar -->
  <header class="relative z-10 w-full px-6 lg:px-12 py-5 border-b border-[#E8E6DE] bg-white/70 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-brand-secondary to-brand-primary flex items-center justify-center text-white shadow-sm ring-1 ring-black/5">
          <span class="material-symbols-outlined text-[22px]">cottage</span>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-extrabold text-base tracking-tight text-brand-slate">Family Essentials</span>
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-brand-forestSoft text-brand-secondary border border-brand-secondary/20">Stitch UI Spec</span>
          </div>
          <p class="text-[11px] font-medium text-brand-muted">Multi-Tenant Domestic Logistics &amp; Landlord Platform</p>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <a href="{{ url('/app/dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-sageSoft text-brand-secondary font-semibold text-xs hover:bg-brand-forestSoft transition-colors">
          <span class="material-symbols-outlined text-[16px]">view_kanban</span>
          <span>Household App</span>
        </a>
        <a href="{{ url('/admin/dashboard') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-secondary text-white font-semibold text-xs shadow-sm hover:bg-[#3D6649] transition-all">
          <span class="material-symbols-outlined text-[16px]">shield</span>
          <span>Landlord Admin</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Hero & Directory -->
  <main class="relative z-10 flex-1 w-full max-w-7xl mx-auto px-6 lg:px-12 py-10 lg:py-14">
    
    <!-- Hero Title -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
      <div class="max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-forestSoft text-brand-secondary text-xs font-bold mb-3 border border-brand-secondary/20">
          <span class="w-2 h-2 rounded-full bg-brand-success animate-pulse"></span>
          <span>All 9 Web Dashboard Pages Recreated From Google Stitch</span>
        </div>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-brand-slate tracking-tight leading-tight">
          Web Dashboard Frontend Portal
        </h1>
        <p class="mt-3 text-base text-brand-muted leading-relaxed max-w-2xl">
          Complete, responsive frontend implementation based on the Google Stitch UI specifications and Warm Sage design tokens. Pure frontend views ready for backend wiring.
        </p>
      </div>

      <div class="flex items-center gap-2 p-1.5 bg-white rounded-2xl shadow-sm border border-[#E8E6DE] self-start md:self-auto text-xs">
        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-sageSoft text-brand-secondary font-semibold">
          <span class="w-2 h-2 rounded-full bg-brand-secondary"></span>
          <span>Warm Sage Theme</span>
        </div>
        <div class="px-2.5 py-1.5 text-brand-muted font-mono text-[11px]">
          Laravel 12 · Tailwind v4
        </div>
      </div>
    </div>

    <!-- SECTIONS CONTAINER -->
    <div class="space-y-14">

      <!-- SECTION 1: Tenant Admin (Family Workspace) -->
      <div>
        <div class="flex items-center justify-between pb-4 border-b border-[#E8E6DE] mb-6">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-brand-forestSoft text-brand-secondary flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">cottage</span>
            </div>
            <div>
              <h2 class="text-xl font-bold text-brand-slate">Tenant Admin — Household Workspace</h2>
              <p class="text-xs text-brand-muted">Client family coordination, domestic kanban errands, geofencing, members, and billing</p>
            </div>
          </div>
          <span class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-brand-sageSoft text-brand-secondary">5 Pages</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          
          <!-- W-TA-01: Login & Register -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-TA-01</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Tenant Admin Login &amp; Registration
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Split screen authentication gateway, client login, registration forms, and phone/SMS OTP verification state.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/login</span>
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/register</span>
              </div>
            </div>

            <a href="{{ url('/login') }}" class="w-full py-2.5 rounded-xl bg-brand-sageSoft hover:bg-brand-forestSoft text-brand-secondary font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Login Portal</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-TA-02: Kanban Overview & Dashboard -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-brand-forestSoft text-brand-secondary">W-TA-02</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-brand-secondary px-2 py-0.5 rounded-full bg-brand-sageSoft">Featured Hub</span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Household Kanban &amp; Errand Hub
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Live 4-column household kanban board (Overdue, Due Today, Assigned to Tanvir, Completed) with quick filters and urgency badges.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/app/dashboard</span>
              </div>
            </div>

            <a href="{{ url('/app/dashboard') }}" class="w-full py-2.5 rounded-xl bg-brand-secondary hover:bg-brand-primary text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-sm">
              <span>Open Kanban Dashboard</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-TA-03: Members & Role Management -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-TA-03</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Family Member &amp; Role Management
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Household member grid, role permission badges (Tenant Admin, Adult, Kid), device pairing statuses, and invite modals.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/app/members</span>
              </div>
            </div>

            <a href="{{ url('/app/members') }}" class="w-full py-2.5 rounded-xl bg-brand-sageSoft hover:bg-brand-forestSoft text-brand-secondary font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Member Manager</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-TA-04: Saved Places & Geofences -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-TA-04</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Saved Places &amp; Geofence Map Console
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Split console with grocery/pharmacy place cards, radius sliders (150m-1km), auto-ping toggles, and live interactive SVG map radar.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/app/places</span>
              </div>
            </div>

            <a href="{{ url('/app/places') }}" class="w-full py-2.5 rounded-xl bg-brand-sageSoft hover:bg-brand-forestSoft text-brand-secondary font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Geofence Console</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-TA-05: Billing & Subscription -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-TA-05</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Billing &amp; Subscription Portal
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Pilot Family Tier management, 3-tier capability comparison table, payment methods (Visa, Apple Pay), and invoice history ledger.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/app/billing</span>
              </div>
            </div>

            <a href="{{ url('/app/billing') }}" class="w-full py-2.5 rounded-xl bg-brand-sageSoft hover:bg-brand-forestSoft text-brand-secondary font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Billing Portal</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

        </div>
      </div>

      <!-- SECTION 2: Platform Admin (Landlord Console) -->
      <div>
        <div class="flex items-center justify-between pb-4 border-b border-[#E8E6DE] mb-6">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-brand-forestSoft text-brand-secondary flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">shield</span>
            </div>
            <div>
              <h2 class="text-xl font-bold text-brand-slate">Platform Admin — Landlord &amp; SRE Infrastructure</h2>
              <p class="text-xs text-brand-muted">Cross-tenant analytics, multi-tenant provisioning, Redis queue workers, and ClickHouse audit log explorer</p>
            </div>
          </div>
          <span class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-brand-forestSoft text-brand-secondary">4 Pages</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          
          <!-- W-PA-01: Admin Login -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-PA-01</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Platform Admin Login
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Zero-trust authentication card, master password, FIDO2 / Yubikey security toggles, and Google Cloud / Okta IAM SSO buttons.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/admin/login</span>
              </div>
            </div>

            <a href="{{ url('/admin/login') }}" class="w-full py-2.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-brand-slate font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Admin Login</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-PA-02: Analytics Dashboard -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-brand-forestSoft text-brand-secondary">W-PA-02</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-brand-secondary px-2 py-0.5 rounded-full bg-brand-sageSoft">Global</span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Global Operations &amp; Analytics
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Active tenant counts, MRR run-rate, Redis queue engine health, tenant growth charts, and tier breakdown visualizations.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/admin/dashboard</span>
              </div>
            </div>

            <a href="{{ url('/admin/dashboard') }}" class="w-full py-2.5 rounded-xl bg-brand-secondary hover:bg-brand-primary text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-sm">
              <span>Open Global Dashboard</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-PA-03: Tenant Management -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-PA-03</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Tenant Management Console
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Multi-tenant CRUD datagrid, PostgreSQL schema namespace indicators, provision tenant drawer, and bulk status controls.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/admin/tenants</span>
              </div>
            </div>

            <a href="{{ url('/admin/tenants') }}" class="w-full py-2.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-brand-slate font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Tenant Console</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

          <!-- W-PA-04: Audit Logs Explorer -->
          <div class="group p-6 rounded-3xl bg-white border border-[#E8E6DE] shadow-sm hover:shadow-md hover:border-brand-primary/40 transition-all flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-stone-100 text-stone-700">W-PA-04</span>
                <span class="w-2 h-2 rounded-full bg-brand-success"></span>
              </div>
              <h3 class="text-base font-bold text-brand-slate group-hover:text-brand-secondary transition-colors">
                Global Audit &amp; Security Logs
              </h3>
              <p class="text-xs text-brand-muted mt-1.5 leading-relaxed">
                Real-time security log stream, zero-trust RLS violation alerts, SHA-256 HMAC integrity ledger, and trace query filters.
              </p>
              <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                <span class="px-2 py-0.5 rounded-md bg-stone-100 font-mono text-stone-600">/admin/audit-logs</span>
              </div>
            </div>

            <a href="{{ url('/admin/audit-logs') }}" class="w-full py-2.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-brand-slate font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
              <span>Open Audit Explorer</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>

        </div>
      </div>

    </div>

  </main>

  <!-- Footer -->
  <footer class="relative z-10 w-full px-6 lg:px-12 py-6 border-t border-[#E8E6DE] bg-white/60 backdrop-blur-sm mt-16">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-brand-muted">
      <div class="flex items-center gap-4">
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-brand-success"></span>
          <span>Family Essentials Multi-Tenant SaaS</span>
        </span>
        <span class="text-stone-300">•</span>
        <span>Google Stitch Recreated UI</span>
      </div>
      <div class="flex items-center gap-4 text-[11px]">
        <a href="{{ url('/app/dashboard') }}" class="hover:text-brand-slate transition-colors">Tenant Dashboard</a>
        <span class="text-stone-300">•</span>
        <a href="{{ url('/admin/dashboard') }}" class="hover:text-brand-slate transition-colors">Landlord Console</a>
        <span class="text-stone-300">•</span>
        <a href="https://stitch.withgoogle.com/projects/11757246856049619346?pli=1" target="_blank" class="hover:text-brand-slate transition-colors font-semibold">Stitch Project #11757246856049619346 &nearr;</a>
      </div>
    </div>
  </footer>

</body>
</html>
