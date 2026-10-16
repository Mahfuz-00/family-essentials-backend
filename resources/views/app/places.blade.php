<!DOCTYPE html>
<html class="h-full" lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <meta content="web_dashboard" name="shell-type"/>
  <title>Saved Places & Geofence Map Console | Family Essentials</title>
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
  <style>
    @layer base {
      html, body { margin: 0; padding: 0; }
      body { overscroll-behavior: none; }
      main > :first-child { margin-top: 0 !important; }
      main > :last-child { margin-bottom: 0 !important; }
    }
    ::-webkit-scrollbar { display: none; }
  </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary-fixed-dim": "#b7ccbc",
            "inverse-surface": "#2d2f44",
            "on-tertiary-fixed-variant": "#7c2e19",
            "text-muted": "#6C757D",
            "on-surface-variant": "#434844",
            "tertiary-fixed": "#ffdbd2",
            "sage-soft": "#EBF0ED",
            "canvas-bg": "#FDFCF7",
            "primary-fixed": "#d2e8d8",
            "surface-bright": "#fbf8ff",
            "secondary": "#376847",
            "on-tertiary": "#ffffff",
            "tertiary-fixed-dim": "#ffb4a1",
            "warning": "#F4A261",
            "on-secondary": "#ffffff",
            "surface": "#FFFFFF",
            "on-tertiary-container": "#fffbff",
            "on-surface": "#181a2e",
            "surface-container-lowest": "#ffffff",
            "on-secondary-fixed-variant": "#1e5031",
            "background": "#fbf8ff",
            "tertiary": "#97422b",
            "surface-dim": "#d7d8f4",
            "secondary-container": "#b6edc2",
            "outline-variant": "#c3c8c2",
            "on-error": "#ffffff",
            "inverse-primary": "#b7ccbc",
            "on-error-container": "#93000a",
            "coral-soft": "#FCECE7",
            "surface-container-highest": "#e0e0fc",
            "on-secondary-fixed": "#00210e",
            "inverse-on-surface": "#f1efff",
            "surface-tint": "#506356",
            "surface-container": "#edecff",
            "on-secondary-container": "#3b6d4b",
            "tertiary-container": "#b65a41",
            "forest-soft": "#E4EDE7",
            "on-background": "#181a2e",
            "secondary-fixed-dim": "#9dd3aa",
            "surface-variant": "#e0e0fc",
            "success": "#81B29A",
            "error-container": "#ffdad6",
            "primary": "#4d6054",
            "on-primary-fixed-variant": "#384b3f",
            "surface-container-high": "#e6e6ff",
            "on-primary": "#ffffff",
            "surface-container-low": "#f4f2ff",
            "on-tertiary-fixed": "#3c0800",
            "on-primary-fixed": "#0d1f15",
            "primary-container": "#66796c",
            "outline": "#737873",
            "error": "#E76F51",
            "on-primary-container": "#f6fff6",
            "secondary-fixed": "#b9efc5"
          },
          borderRadius: {
            DEFAULT: "0.25rem",
            lg: "0.5rem",
            xl: "0.75rem",
            full: "9999px"
          },
          spacing: {
            "margin-desktop": "2rem",
            "space-sm": "0.5rem",
            "margin-mobile": "1rem",
            "space-md": "1rem",
            "margin": "1.25rem",
            "space-lg": "1.5rem",
            "gutter": "1rem",
            "space-xl": "2rem",
            "gutter-sm": "0.75rem",
            "space-xs": "0.25rem"
          },
          fontFamily: {
            "display-lg": ["Plus Jakarta Sans"],
            "headline-sm": ["Plus Jakarta Sans"],
            "body-md": ["Plus Jakarta Sans"],
            "label-md": ["Plus Jakarta Sans"],
            "headline-md": ["Plus Jakarta Sans"],
            "label-sm": ["Plus Jakarta Sans"],
            "body-sm": ["Plus Jakarta Sans"],
            "label-lg": ["Plus Jakarta Sans"],
            "body-lg": ["Plus Jakarta Sans"],
            "headline-lg": ["Plus Jakarta Sans"]
          }
        }
      }
    };
  </script>
</head>
<body class="bg-canvas-bg font-body-md text-body-md text-on-surface antialiased">

  <!-- Sidebar Component -->
  <aside class="fixed left-0 top-0 h-full w-72 bg-surface shadow-[0_1px_8px_rgba(43,45,66,0.04)] z-50 flex flex-col justify-between">
    <div class="flex flex-col">
      <a href="{{ url('/') }}" class="h-16 px-space-lg flex items-center gap-space-sm hover:opacity-90 transition-opacity">
        <div class="w-9 h-9 rounded-xl bg-sage-soft flex items-center justify-center text-primary">
          <span class="material-symbols-outlined text-[20px]">cottage</span>
        </div>
        <div class="flex flex-col">
          <span class="font-headline-sm text-headline-sm text-on-surface leading-tight font-bold">Family Essentials</span>
          <span class="font-label-sm text-label-sm text-text-muted">Household Logistics</span>
        </div>
      </a>
      
      <div class="px-space-md py-space-xs">
        <div class="flex items-center justify-between px-space-md py-space-sm rounded-xl bg-surface-container-low text-on-surface">
          <div class="flex items-center gap-space-sm">
            <div class="w-2 h-2 rounded-full bg-secondary"></div>
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm text-text-muted leading-none">Active Household</span>
              <span class="font-label-lg text-label-lg text-on-surface leading-snug">The Tanvir Household</span>
            </div>
          </div>
          <span class="material-symbols-outlined text-on-surface-variant text-[18px]">unfold_more</span>
        </div>
      </div>

      <nav class="flex flex-col gap-space-xs px-space-md mt-space-md">
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/dashboard') }}">
          <span class="material-symbols-outlined text-[20px]">view_kanban</span>
          <span>Dashboard / Kanban</span>
        </a>
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/dashboard') }}#requests">
          <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
          <span>Requests</span>
        </a>
        <!-- Active Link -->
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl bg-primary-container text-on-primary-container font-headline-sm text-headline-sm transition-all" href="{{ url('/app/places') }}">
          <span class="material-symbols-outlined text-[20px]">pin_drop</span>
          <span>Saved Places &amp; Geofences</span>
        </a>
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/members') }}">
          <span class="material-symbols-outlined text-[20px]">group</span>
          <span>Family Members</span>
        </a>
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/billing') }}">
          <span class="material-symbols-outlined text-[20px]">credit_card</span>
          <span>Billing &amp; Subscription</span>
        </a>
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/dashboard') }}#settings">
          <span class="material-symbols-outlined text-[20px]">tune</span>
          <span>Settings</span>
        </a>
      </nav>
    </div>

    <!-- Bottom Sidebar Section -->
    <div class="flex flex-col px-space-md pb-space-lg gap-space-md">
      <div class="p-space-md rounded-xl bg-forest-soft">
        <div class="flex items-center justify-between mb-space-xs">
          <span class="font-label-sm text-label-sm text-secondary font-bold">Pilot Family Plan</span>
          <span class="font-label-sm text-label-sm text-on-surface-variant">4/5 Members</span>
        </div>
        <div class="w-full h-1.5 rounded-full bg-surface-container">
          <div class="h-1.5 rounded-full bg-secondary w-4/5"></div>
        </div>
      </div>

      <div class="flex items-center justify-between p-space-sm rounded-xl bg-surface-container-low">
        <div class="flex items-center gap-space-sm">
          <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-[18px]">person</span>
          </div>
          <div class="flex flex-col">
            <span class="font-label-lg text-label-lg text-on-surface leading-snug font-semibold">Tanvir Rahman</span>
            <span class="font-label-sm text-label-sm text-text-muted leading-tight">Tenant Admin</span>
          </div>
        </div>
        <a href="{{ url('/login') }}" title="Switch / Sign Out" class="text-on-surface-variant hover:text-error transition-colors p-1 rounded-lg hover:bg-surface">
          <span class="material-symbols-outlined text-[18px]">logout</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- Top Header Component -->
  <div class="pl-72">
    <header class="fixed top-0 left-72 right-0 h-16 bg-surface/80 backdrop-blur-xl shadow-[0_1px_8px_rgba(43,45,66,0.04)] z-40 flex items-center justify-between px-space-lg">
      <div class="flex items-center gap-space-lg">
        <div class="flex flex-col">
          <span class="font-headline-sm text-headline-sm text-on-surface leading-tight font-semibold">Good morning, Tanvir</span>
          <span class="font-label-sm text-label-sm text-text-muted">Family Workspace · Logistics Live</span>
        </div>
        <div class="relative flex items-center w-80">
          <span class="material-symbols-outlined absolute left-3 text-[18px] text-on-surface-variant pointer-events-none">search</span>
          <input class="w-full h-9 pl-9 pr-space-md bg-canvas-bg rounded-xl font-body-sm text-body-sm text-on-surface placeholder:text-text-muted outline-none focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Quick search tasks, stores, items..." type="text"/>
        </div>
      </div>
      <div class="flex items-center gap-space-md">
        <div class="flex items-center gap-space-xs px-space-sm py-1 rounded-full bg-sage-soft text-secondary">
          <span class="material-symbols-outlined text-[16px]">palette</span>
          <span class="font-label-sm text-label-sm font-semibold">Warm Sage</span>
        </div>
        <div class="relative flex items-center justify-center">
          <button aria-label="Notifications" class="w-9 h-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface transition-all">
            <span class="material-symbols-outlined text-[20px]">notifications</span>
          </button>
          <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-error ring-2 ring-surface"></span>
        </div>
        <button class="flex items-center gap-space-xs h-9 px-space-md rounded-xl bg-secondary text-on-secondary font-label-lg text-label-lg hover:bg-primary transition-all active:scale-95 shadow-sm">
          <span class="material-symbols-outlined text-[18px]">add</span>
          <span>Add Request</span>
        </button>
        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center">
          <span class="material-symbols-outlined text-on-primary text-[18px]">person</span>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="relative pt-16 w-full min-h-screen px-space-lg py-space-lg">
      <div class="flex flex-col w-full gap-space-lg">
        
        <!-- Breadcrumbs & Hero Header -->
        <section class="flex flex-col gap-1">
          <div class="flex items-center gap-2 text-text-muted font-label-sm text-label-sm">
            <span>Family Workspace</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-secondary font-semibold">Saved Places &amp; Geofences</span>
          </div>
          
          <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mt-1">
            <div class="flex flex-col max-w-2xl">
              <h1 class="font-display-lg text-display-lg text-on-surface font-bold tracking-tight">Saved Places &amp; Geofence Map Console</h1>
              <p class="font-body-md text-body-md text-text-muted mt-1 leading-relaxed">
                Configure domestic proximity radiuses, arrival automation triggers, and grocery store geofence alerts for all paired household devices.
              </p>
            </div>
            
            <div class="flex items-center gap-2.5 shrink-0">
              <button class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-surface hover:bg-sage-soft text-on-surface font-label-md text-label-md shadow-sm border border-[rgba(43,45,66,0.06)] transition-all">
                <span class="material-symbols-outlined text-[18px] text-secondary">gps_fixed</span>
                <span>Calibrate GPS Precision</span>
              </button>
              <button class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary hover:bg-[#325a3d] text-on-secondary font-label-md text-label-md shadow-sm transition-all active:scale-95">
                <span class="material-symbols-outlined text-[20px]">add</span>
                <span>Add New Place</span>
              </button>
            </div>
          </div>
        </section>

        <!-- Metrics KPI Row (4 Cards matching W-TA-04 design) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
          <!-- KPI 1 -->
          <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex items-center justify-between">
            <div class="flex flex-col">
              <span class="font-display-lg text-display-lg font-bold text-on-surface">4</span>
              <span class="font-body-sm text-body-sm text-text-muted">Active Geofences</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-sage-soft flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-[24px]">explore</span>
            </div>
          </div>

          <!-- KPI 2 -->
          <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex items-center justify-between">
            <div class="flex flex-col">
              <span class="font-display-lg text-display-lg font-bold text-secondary flex items-center gap-1">
                <span class="material-symbols-outlined text-[22px]">arrow_downward</span> 1.2% / day
              </span>
              <span class="font-body-sm text-body-sm text-text-muted">Low-Power Telemetry</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-forest-soft flex items-center justify-center text-secondary">
              <span class="material-symbols-outlined text-[24px]">battery_charging_full</span>
            </div>
          </div>

          <!-- KPI 3 -->
          <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex items-center justify-between">
            <div class="flex flex-col">
              <span class="font-headline-md text-headline-md font-bold text-on-surface">Armed &amp; Synced</span>
              <span class="font-body-sm text-body-sm text-text-muted">Proximity Escalation</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-coral-soft flex items-center justify-center text-tertiary">
              <span class="material-symbols-outlined text-[24px]">notifications_active</span>
            </div>
          </div>

          <!-- KPI 4 -->
          <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex items-center justify-between">
            <div class="flex flex-col">
              <span class="font-headline-md text-headline-md font-bold text-on-surface flex items-center gap-1.5">
                <span>1 Zone Active</span>
                <span class="w-2 h-2 rounded-full bg-warning animate-pulse"></span>
              </span>
              <span class="font-body-sm text-body-sm text-text-muted">4 Pending Errands</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-sage-soft flex items-center justify-center text-secondary">
              <span class="material-symbols-outlined text-[24px]">shopping_basket</span>
            </div>
          </div>
        </div>

        <!-- Main Split Console Layout -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-space-lg items-start">
          
          <!-- LEFT COLUMN: Saved Places List & Settings (5 Cols) -->
          <div class="xl:col-span-5 flex flex-col gap-space-md">
            
            <!-- Filter Bar & Search -->
            <div class="bg-surface p-space-sm rounded-2xl shadow-sm border border-[rgba(43,45,66,0.05)] flex flex-col gap-2.5">
              <div class="relative w-full">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-text-muted pointer-events-none">search</span>
                <input class="w-full h-10 pl-9 pr-space-md bg-canvas-bg rounded-xl font-body-sm text-body-sm text-on-surface placeholder:text-text-muted outline-none focus:bg-surface focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Search places, addresses, or items..." type="text"/>
              </div>
              <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                <button class="px-3 py-1.5 rounded-full bg-secondary text-white font-label-sm text-label-sm font-semibold shrink-0">All Places (4)</button>
                <button class="px-3 py-1.5 rounded-full bg-canvas-bg hover:bg-sage-soft text-on-surface-variant font-label-sm text-label-sm shrink-0 transition-colors">Supermarkets (2)</button>
                <button class="px-3 py-1.5 rounded-full bg-canvas-bg hover:bg-sage-soft text-on-surface-variant font-label-sm text-label-sm shrink-0 transition-colors">Pharmacies (1)</button>
                <button class="px-3 py-1.5 rounded-full bg-canvas-bg hover:bg-sage-soft text-on-surface-variant font-label-sm text-label-sm shrink-0 transition-colors">Home Base (1)</button>
              </div>
            </div>

            <!-- Card 1: Whole Foods Market (Active & Expanded) -->
            <div class="p-space-md rounded-2xl bg-surface border-2 border-secondary shadow-[0_4px_16px_rgba(55,104,71,0.08)] flex flex-col gap-space-sm">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-forest-soft text-secondary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">shopping_cart</span>
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-1.5">
                      <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Whole Foods Market</h3>
                      <span class="material-symbols-outlined text-secondary text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-text-muted">10 Columbus Circle, New York, NY</span>
                  </div>
                </div>
                <div class="flex items-center gap-1">
                  <button class="p-1 text-on-surface-variant hover:text-on-surface transition-colors" title="Locate on Map">
                    <span class="material-symbols-outlined text-[18px]">near_me</span>
                  </button>
                  <button class="p-1 text-on-surface-variant hover:text-on-surface transition-colors" title="Edit Place">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                  </button>
                </div>
              </div>

              <!-- Tags -->
              <div class="flex flex-wrap items-center gap-2 pt-1">
                <span class="px-2.5 py-0.5 rounded-md bg-sage-soft text-secondary font-label-sm text-label-sm font-semibold">Supermarket &amp; Grocery</span>
                <span class="px-2.5 py-0.5 rounded-md bg-coral-soft text-error font-label-sm text-label-sm font-bold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                  <span>3 items pending pickup</span>
                </span>
              </div>

              <!-- Pending Items Box -->
              <div class="p-2.5 rounded-xl bg-canvas-bg/80 border border-[rgba(43,45,66,0.04)] flex flex-wrap items-center gap-2 text-xs">
                <span class="font-label-sm text-text-muted font-bold uppercase tracking-wider">Needs:</span>
                <span class="px-2 py-0.5 bg-surface rounded-md shadow-xs text-on-surface font-medium">Organic Oat Milk</span>
                <span class="px-2 py-0.5 bg-surface rounded-md shadow-xs text-on-surface font-medium">Artisan Sourdough</span>
                <span class="px-2 py-0.5 bg-surface rounded-md shadow-xs text-on-surface font-medium">Hass Avocados (x4)</span>
              </div>

              <!-- Geofence Radius Slider Mock -->
              <div class="pt-2 flex flex-col gap-1.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-label-md text-on-surface font-semibold flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-secondary">radar</span>
                    <span>Geofence Radius: 250m</span>
                  </span>
                  <span class="text-text-muted font-body-sm">(~3 min walking cushion)</span>
                </div>
                <!-- Range Slider Bar -->
                <div class="w-full bg-surface-container h-2 rounded-full relative my-1">
                  <div class="bg-secondary h-2 rounded-full w-2/5"></div>
                  <div class="absolute left-[38%] -top-1.5 w-5 h-5 rounded-full bg-white border-2 border-secondary shadow-md cursor-pointer"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-text-muted">
                  <span>100m (Tight)</span>
                  <span class="text-secondary font-bold">250m (Selected)</span>
                  <span>500m</span>
                  <span>1km (Driving)</span>
                </div>
              </div>

              <!-- Card Footer with Assignee Avatars & Toggle -->
              <div class="pt-3 border-t border-[rgba(43,45,66,0.06)] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-label-sm text-text-muted">Assigned:</span>
                  <div class="flex -space-x-1.5">
                    <div class="w-6 h-6 rounded-full bg-secondary text-white font-bold text-[10px] flex items-center justify-center ring-2 ring-surface">S</div>
                    <div class="w-6 h-6 rounded-full bg-primary text-white font-bold text-[10px] flex items-center justify-center ring-2 ring-surface">T</div>
                  </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <span class="font-label-sm text-on-surface font-medium">Auto-ping runner:</span>
                  <input type="checkbox" checked class="w-4 h-4 rounded text-secondary focus:ring-secondary/20"/>
                </label>
              </div>
            </div>

            <!-- Card 2: CVS Pharmacy -->
            <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex flex-col gap-space-sm">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-coral-soft text-error flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">medication</span>
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-1.5">
                      <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">CVS Pharmacy</h3>
                      <span class="w-2 h-2 rounded-full bg-error"></span>
                    </div>
                    <span class="font-body-sm text-body-sm text-text-muted">450 Lexington Ave, New York, NY</span>
                  </div>
                </div>
                <div class="flex items-center gap-1 text-on-surface-variant">
                  <span class="material-symbols-outlined text-[18px]">more_vert</span>
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-md bg-sage-soft text-secondary font-label-sm text-label-sm">Pharmacy &amp; Health</span>
                <span class="px-2.5 py-0.5 rounded-md bg-coral-soft text-error font-label-sm text-label-sm font-bold flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">emergency</span>
                  <span>Urgent: Amoxicillin 500mg Refill</span>
                </span>
              </div>

              <div class="grid grid-cols-2 gap-2 text-xs py-1">
                <div class="p-2 rounded-xl bg-canvas-bg/80 flex flex-col">
                  <span class="text-text-muted font-label-sm">Perimeter Radius</span>
                  <span class="font-bold text-on-surface">150m <span class="text-text-muted font-normal">(Block trigger)</span></span>
                </div>
                <div class="p-2 rounded-xl bg-canvas-bg/80 flex flex-col">
                  <span class="text-text-muted font-label-sm">Audio Alert Override</span>
                  <span class="font-bold text-error flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">volume_up</span> Quiet hours bypass
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t border-[rgba(43,45,66,0.06)] flex items-center justify-between text-xs text-text-muted">
                <span>Assigned: <strong class="text-on-surface">Tanvir Rahman</strong></span>
                <span class="text-error font-semibold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-error animate-ping"></span> Proximity push armed
                </span>
              </div>
            </div>

            <!-- Card 3: Trader Joe's -->
            <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex flex-col gap-space-sm">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-sage-soft text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">storefront</span>
                  </div>
                  <div class="flex flex-col">
                    <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Trader Joe's</h3>
                    <span class="font-body-sm text-body-sm text-text-muted">675 6th Ave, Chelsea, New York, NY</span>
                  </div>
                </div>
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">more_vert</span>
              </div>

              <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-md bg-sage-soft text-secondary font-label-sm text-label-sm">Groceries &amp; Snacks</span>
                <span class="px-2.5 py-0.5 rounded-md bg-surface-container-low text-text-muted font-label-sm text-label-sm">0 Queued Items</span>
              </div>

              <div class="p-2.5 rounded-xl bg-forest-soft/50 text-xs text-secondary flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">lightbulb</span>
                <span>Nudge active: <em>"Nearby! Any household snack requests?"</em></span>
              </div>

              <div class="pt-2 border-t border-[rgba(43,45,66,0.06)] flex items-center justify-between text-xs text-text-muted">
                <span>Radius: 300m (Neighborhood)</span>
                <span class="text-secondary font-medium">Idle / Ready</span>
              </div>
            </div>

            <!-- Card 4: Home Base -->
            <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex flex-col gap-space-sm">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-forest-soft text-secondary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">home</span>
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                      <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Home Base</h3>
                      <span class="px-2 py-0.5 rounded bg-surface-container text-[10px] font-bold text-text-muted uppercase tracking-wider">Main HQ</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-text-muted">742 Evergreen Terrace, Brooklyn, NY</span>
                  </div>
                </div>
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">more_vert</span>
              </div>

              <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-md bg-sage-soft text-secondary font-label-sm text-label-sm">Drop-off &amp; Distribution</span>
                <span class="px-2.5 py-0.5 rounded-md bg-surface-container-low text-text-muted font-label-sm text-label-sm">Radius: 100m (Driveway gate)</span>
              </div>

              <div class="p-2.5 rounded-xl bg-sage-soft/70 text-xs text-primary leading-relaxed flex items-start gap-2">
                <span class="material-symbols-outlined text-[16px] mt-0.5">campaign</span>
                <span><strong>Automation Armed:</strong> Auto-broadcast "Arrived with Groceries" to household tablets &amp; notify kids to assist unloading.</span>
              </div>
            </div>

            <!-- Add Destination Button -->
            <button class="w-full py-3.5 border-2 border-dashed border-primary/30 hover:border-primary rounded-2xl text-primary font-label-md text-label-md flex items-center justify-center gap-2 hover:bg-sage-soft/40 transition-all">
              <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
              <span>+ Add Another Destination or School Zone</span>
            </button>

          </div>

          <!-- RIGHT COLUMN: Interactive Geofence Map Console (7 Cols) -->
          <div class="xl:col-span-7 flex flex-col gap-space-md">
            
            <!-- Map Canvas Container -->
            <div class="relative w-full h-[620px] rounded-3xl overflow-hidden bg-[#EAE8DE] shadow-[0_4px_24px_rgba(43,45,66,0.08)] border border-[rgba(43,45,66,0.08)]">
              
              <!-- Realistic Stylized SVG Map Layer -->
              <svg class="absolute inset-0 w-full h-full object-cover" viewBox="0 0 800 620" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Background Landmass -->
                <rect width="800" height="620" fill="#F4F3EE"/>
                
                <!-- Central Park Green Polygon -->
                <path d="M420 40 L680 70 L640 380 L380 340 Z" fill="#DCECDD" stroke="#C4DECA" stroke-width="2"/>
                <text x="500" y="210" fill="#4A7C59" font-family="Plus Jakarta Sans" font-size="14" font-weight="700" letter-spacing="2">CENTRAL PARK</text>
                <text x="510" y="230" fill="#7C9082" font-family="Plus Jakarta Sans" font-size="11">Sheeps Meadow</text>
                
                <!-- Hudson River / Water Body at left -->
                <path d="M0 0 L140 0 L180 620 L0 620 Z" fill="#D6E5EA"/>
                
                <!-- Secondary Park Area (Madison Sq) -->
                <rect x="420" y="440" width="160" height="90" rx="12" fill="#E2EFE3"/>
                <text x="445" y="490" fill="#6A8D74" font-family="Plus Jakarta Sans" font-size="11" font-weight="600" letter-spacing="1">MADISON SQ</text>
                
                <!-- Street Grid Lines -->
                <g stroke="#E0DDD2" stroke-width="6" stroke-linecap="round">
                  <line x1="140" y1="120" x2="800" y2="120"/>
                  <line x1="140" y1="200" x2="800" y2="200"/>
                  <line x1="140" y1="280" x2="800" y2="280"/>
                  <line x1="140" y1="360" x2="800" y2="360"/>
                  <line x1="140" y1="440" x2="800" y2="440"/>
                  <line x1="140" y1="520" x2="800" y2="520"/>
                  <line x1="260" y1="0" x2="260" y2="620"/>
                  <line x1="380" y1="0" x2="380" y2="620"/>
                  <line x1="500" y1="0" x2="500" y2="620"/>
                  <line x1="620" y1="0" x2="620" y2="620"/>
                  <line x1="740" y1="0" x2="740" y2="620"/>
                </g>

                <!-- Major Diagonal Avenues (Broadway) -->
                <line x1="720" y1="20" x2="280" y2="600" stroke="#FAF9F5" stroke-width="12" stroke-linecap="round"/>
                <line x1="720" y1="20" x2="280" y2="600" stroke="#EAE6DA" stroke-width="4" stroke-linecap="round"/>

                <!-- Active Walking Path Route (Dotted) -->
                <path d="M 580 490 L 520 370 L 410 240 L 360 210" stroke="#4A7C59" stroke-width="3" stroke-dasharray="6 6" fill="none"/>

                <!-- Whole Foods Circular Geofence Zone (Green Animated Radar) -->
                <circle cx="360" cy="210" r="85" fill="#4A7C59" fill-opacity="0.18" stroke="#4A7C59" stroke-width="2"/>
                <circle cx="360" cy="210" r="50" fill="#4A7C59" fill-opacity="0.25" stroke="#4A7C59" stroke-width="1.5"/>
                <circle cx="360" cy="210" r="10" fill="#4A7C59"/>

                <!-- Trader Joe's Radius (Gray) -->
                <circle cx="560" cy="510" r="60" fill="#7C9082" fill-opacity="0.12" stroke="#7C9082" stroke-width="1.5" stroke-dasharray="4 4"/>
                
                <!-- CVS Radius (Red alert tint) -->
                <circle cx="700" cy="260" r="45" fill="#E76F51" fill-opacity="0.15" stroke="#E76F51" stroke-width="2"/>
              </svg>

              <!-- Map UI Overlays / Markers -->
              
              <!-- Whole Foods Pin Badge -->
              <div class="absolute top-[165px] left-[270px] flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-secondary text-white font-label-sm text-label-sm font-bold shadow-lg">
                <span class="material-symbols-outlined text-[16px]">shopping_cart</span>
                <span>Whole Foods · 3 Items</span>
              </div>

              <!-- Trader Joe's Pin Badge -->
              <div class="absolute top-[500px] left-[520px] flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/95 text-on-surface font-label-sm text-label-sm shadow-md border border-stone-200">
                <span class="material-symbols-outlined text-[14px] text-primary">storefront</span>
                <span>Trader Joe's</span>
              </div>

              <!-- CVS Pin Badge -->
              <div class="absolute top-[245px] left-[660px] flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/95 text-error font-label-sm text-label-sm font-bold shadow-md border border-error/30">
                <span class="material-symbols-outlined text-[14px]">medication</span>
                <span>CVS</span>
              </div>

              <!-- Runner Marker: Sarah Approaching -->
              <div class="absolute top-[320px] left-[450px] flex flex-col items-start gap-1">
                <div class="flex items-center gap-2 bg-white/95 px-3 py-1.5 rounded-xl shadow-lg border border-secondary/20">
                  <div class="w-6 h-6 rounded-full bg-secondary text-white font-bold text-[10px] flex items-center justify-center">S</div>
                  <div class="flex flex-col">
                    <span class="font-label-sm text-on-surface font-bold text-[11px] leading-tight">Sarah · 2.4 mi away</span>
                    <span class="text-[10px] text-secondary font-semibold">Approaching WF in ~8 min</span>
                  </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-secondary ring-4 ring-white shadow-md mx-auto"></div>
              </div>

              <!-- Top Right Map Tool Controls -->
              <div class="absolute top-4 right-4 flex flex-col items-end gap-2.5">
                <!-- Map Layer Switcher -->
                <div class="flex items-center bg-white/90 backdrop-blur-md p-1 rounded-xl shadow-md border border-black/5 text-xs font-semibold">
                  <button class="px-3 py-1.5 rounded-lg bg-surface text-on-surface shadow-xs">Vector Street</button>
                  <button class="px-3 py-1.5 rounded-lg text-text-muted hover:text-on-surface transition-colors">Satellite</button>
                </div>
                
                <!-- Live Traffic Toggle -->
                <div class="flex items-center gap-2 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-md border border-black/5 text-xs">
                  <span class="w-2 h-2 rounded-full bg-success"></span>
                  <span class="font-medium text-on-surface">Live Traffic</span>
                  <span class="font-bold text-secondary">ON</span>
                </div>

                <!-- Zoom Steppers -->
                <div class="flex flex-col bg-white/90 backdrop-blur-md rounded-xl shadow-md border border-black/5 overflow-hidden">
                  <button class="w-9 h-9 flex items-center justify-center text-on-surface hover:bg-canvas-bg transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                  </button>
                  <div class="h-px bg-stone-200"></div>
                  <button class="w-9 h-9 flex items-center justify-center text-on-surface hover:bg-canvas-bg transition-colors">
                    <span class="material-symbols-outlined text-[18px]">remove</span>
                  </button>
                  <div class="h-px bg-stone-200"></div>
                  <button class="w-9 h-9 flex items-center justify-center text-on-surface hover:bg-canvas-bg transition-colors" title="My Location">
                    <span class="material-symbols-outlined text-[18px]">my_location</span>
                  </button>
                </div>
              </div>

              <!-- Bottom Floating Zone Inspector Overlay on Map -->
              <div class="absolute bottom-4 left-4 right-4 bg-white/95 backdrop-blur-md p-4 rounded-2xl shadow-xl border border-black/5 flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-forest-soft text-secondary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[24px]">adjust</span>
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                      <span class="text-[10px] uppercase font-bold tracking-wider text-text-muted">Selected Zone Inspector</span>
                      <span class="px-2 py-0.2 rounded bg-sage-soft text-secondary text-[10px] font-bold">Columbus</span>
                    </div>
                    <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Whole Foods Market</span>
                    <span class="text-xs text-text-muted">Radius: 250m • High-Frequency GPS Tracking Armed</span>
                  </div>
                </div>

                <div class="flex items-center gap-2.5 w-full md:w-auto justify-end">
                  <button class="px-3.5 py-2.5 rounded-xl border border-stone-300 text-on-surface hover:bg-stone-50 font-label-md text-label-md transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px] text-primary">sensors</span>
                    <span>Test Simulated Ping</span>
                  </button>
                  <button class="px-4 py-2.5 rounded-xl bg-secondary hover:bg-primary text-white font-label-md text-label-md shadow-sm transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Save Zone Boundaries</span>
                  </button>
                </div>
              </div>

            </div>

            <!-- Hardware & Triangulation Diagnostic Card -->
            <div class="p-space-md rounded-2xl bg-surface shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-[rgba(43,45,66,0.05)] flex items-center justify-between">
              <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-sage-soft text-secondary flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-[22px]">cell_tower</span>
                </div>
                <div class="flex flex-col">
                  <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface text-sm">Cell-Tower + Wi-Fi Triangulation Fallback</h4>
                  <p class="font-body-sm text-body-sm text-text-muted">High-accuracy GPS only wakes up when devices travel within 600m of an active destination.</p>
                </div>
              </div>
              <a href="#profiles" class="font-label-md text-label-md text-secondary hover:underline flex items-center gap-1 shrink-0">
                <span>Configure Hardware Profiles</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
              </a>
            </div>

          </div>

        </div>

      </div>
    </main>
  </div>

</body>
</html>
