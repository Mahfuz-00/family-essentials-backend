<!DOCTYPE html>
<html class="h-full" lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <meta content="web_dashboard" name="shell-type"/>
  <title>Billing &amp; Subscription Portal | Family Essentials</title>
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
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/places') }}">
          <span class="material-symbols-outlined text-[20px]">pin_drop</span>
          <span>Saved Places &amp; Geofences</span>
        </a>
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl font-body-md text-body-md text-on-surface-variant hover:bg-sage-soft hover:text-on-surface transition-all" href="{{ url('/app/members') }}">
          <span class="material-symbols-outlined text-[20px]">group</span>
          <span>Family Members</span>
        </a>
        <!-- Active Link -->
        <a class="flex items-center gap-space-md px-space-md py-space-sm rounded-xl bg-primary-container text-on-primary-container font-headline-sm text-headline-sm transition-all" href="{{ url('/app/billing') }}">
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
      <div class="flex flex-col w-full gap-space-lg pb-16">
        
        <!-- Breadcrumbs & Hero Header -->
        <section class="flex flex-col gap-1">
          <div class="flex items-center gap-2 text-text-muted font-label-sm text-label-sm">
            <span>Family Workspace</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-secondary font-semibold">Billing &amp; Subscription</span>
          </div>
          
          <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mt-1">
            <div class="flex flex-col max-w-2xl">
              <h1 class="font-display-lg text-display-lg text-on-surface font-bold tracking-tight">Billing &amp; Subscription Portal</h1>
              <p class="font-body-md text-body-md text-text-muted mt-1 leading-relaxed">
                Manage your household SaaS membership, connected family device seats, payment methods, and invoice receipts.
              </p>
            </div>
            
            <div class="flex items-center gap-2.5 shrink-0">
              <button class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-surface hover:bg-sage-soft text-on-surface font-label-md text-label-md shadow-sm border border-[rgba(43,45,66,0.06)] transition-all">
                <span class="material-symbols-outlined text-[18px] text-primary">download</span>
                <span>Download Tax Statement</span>
              </button>
              <button class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-surface hover:bg-sage-soft text-on-surface font-label-md text-label-md shadow-sm border border-[rgba(43,45,66,0.06)] transition-all">
                <span class="material-symbols-outlined text-[18px] text-primary">credit_card</span>
                <span>+ Add Payment Method</span>
              </button>
              <button class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-secondary hover:bg-primary text-on-secondary font-label-md text-label-md shadow-sm transition-all active:scale-95">
                <span class="material-symbols-outlined text-[18px]">upgrade</span>
                <span>Upgrade Plan</span>
              </button>
            </div>
          </div>
        </section>

        <!-- TOP CARD: Active Subscription Overview (Pilot Family Tier) -->
        <div class="p-6 md:p-8 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-[0_4px_20px_rgba(43,45,66,0.04)] flex flex-col gap-6">
          
          <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-canvas-bg">
            <div class="flex items-start gap-4">
              <div class="w-14 h-14 rounded-2xl bg-forest-soft text-secondary flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-[32px]">verified_user</span>
              </div>
              <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2.5">
                  <h2 class="font-display-lg text-2xl md:text-3xl font-bold text-on-surface">Pilot Family Tier</h2>
                  <span class="px-2.5 py-0.5 rounded-full bg-forest-soft text-secondary text-xs font-bold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                    <span>Active Membership</span>
                  </span>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-secondary">
                  <span class="px-2 py-0.5 rounded-md bg-sage-soft">Annual Billing (20% OFF)</span>
                </div>
                <p class="font-body-sm text-body-sm text-text-muted max-w-2xl mt-1 leading-relaxed">
                  Comprehensive shared logistics, geofencing, and unlimited breakthrough voice calls for immediate family units.
                </p>
              </div>
            </div>

            <div class="flex flex-col md:items-end shrink-0">
              <div class="flex items-baseline gap-1">
                <span class="font-display-lg text-3xl md:text-4xl font-extrabold text-on-surface">$14.99</span>
                <span class="text-sm font-semibold text-text-muted">/ month</span>
              </div>
              <span class="text-xs text-text-muted mt-0.5">Billed annually at $179.88/yr</span>
            </div>
          </div>

          <!-- Renewal Details & Auto-Renew Toggle -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-canvas-bg/80 border border-[rgba(43,45,66,0.04)]">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] text-secondary">event_repeat</span>
              <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 text-xs text-on-surface">
                <span class="font-semibold">Next auto-renewal on October 18, 2026</span>
                <span class="hidden sm:inline text-text-muted">•</span>
                <span class="text-text-muted">As a primary payment, method will be automatically charged $179.88 USD.</span>
              </div>
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-xs font-semibold text-on-surface">Auto-Renew:</span>
              <div class="w-11 h-6 rounded-full bg-secondary flex items-center p-1 cursor-pointer">
                <div class="w-4 h-4 rounded-full bg-white ml-auto shadow-sm"></div>
              </div>
            </div>
          </div>

          <!-- 4 Usage Progress Meter Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Meter 1 -->
            <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-semibold text-on-surface flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">group</span>
                    <span>Family Member Seats</span>
                  </span>
                  <span class="font-bold text-secondary">80%</span>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-xl font-bold text-on-surface">4 of 5</span>
                  <span class="text-xs text-text-muted">Used</span>
                </div>
                <div class="w-full h-2 rounded-full bg-stone-200 overflow-hidden mb-2">
                  <div class="h-full bg-secondary rounded-full w-4/5"></div>
                </div>
                <div class="flex -space-x-1.5 py-1">
                  <div class="w-5 h-5 rounded-full bg-secondary text-white font-bold text-[9px] flex items-center justify-center ring-1 ring-white">C</div>
                  <div class="w-5 h-5 rounded-full bg-primary text-white font-bold text-[9px] flex items-center justify-center ring-1 ring-white">S</div>
                  <div class="w-5 h-5 rounded-full bg-tertiary text-white font-bold text-[9px] flex items-center justify-center ring-1 ring-white">T</div>
                  <div class="w-5 h-5 rounded-full bg-warning text-white font-bold text-[9px] flex items-center justify-center ring-1 ring-white">M</div>
                </div>
              </div>
              <p class="text-[11px] text-text-muted mt-2">Need more? Upgrade to Household Pro to add extra adults &amp; nannies.</p>
            </div>

            <!-- Meter 2 -->
            <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-semibold text-on-surface flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">pin_drop</span>
                    <span>Active Geofenced Places</span>
                  </span>
                  <span class="font-bold text-secondary">40%</span>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-xl font-bold text-on-surface">4 of 10</span>
                  <span class="text-xs text-text-muted">Used</span>
                </div>
                <div class="w-full h-2 rounded-full bg-stone-200 overflow-hidden mb-2">
                  <div class="h-full bg-secondary rounded-full w-2/5"></div>
                </div>
              </div>
              <p class="text-[11px] text-text-muted mt-2">Whole Foods, CVS Pharmacy, Trader Joe's, Home Base</p>
            </div>

            <!-- Meter 3 -->
            <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-semibold text-on-surface flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">phone_in_talk</span>
                    <span>AI Breakthrough Calls</span>
                  </span>
                  <span class="font-bold text-secondary">40%</span>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-xl font-bold text-on-surface">12 of 30</span>
                  <span class="text-xs text-text-muted">Calls</span>
                </div>
                <div class="w-full h-2 rounded-full bg-stone-200 overflow-hidden mb-2">
                  <div class="h-full bg-secondary rounded-full w-2/5"></div>
                </div>
              </div>
              <p class="text-[11px] text-text-muted mt-2">8 critical wake/breakthrough alert overrides used this billing cycle.</p>
            </div>

            <!-- Meter 4 -->
            <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-semibold text-on-surface flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">devices</span>
                    <span>Multi-Device Sync</span>
                  </span>
                  <span class="font-bold text-success">Active</span>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-xl font-bold text-on-surface">Unlimited</span>
                </div>
                <div class="w-full h-2 rounded-full bg-success/20 overflow-hidden mb-2">
                  <div class="h-full bg-success rounded-full w-full"></div>
                </div>
              </div>
              <p class="text-[11px] text-text-muted mt-2">4 mobile devices &amp; 1 shared kitchen wall display in sync.</p>
            </div>

          </div>

          <!-- Bottom Actions inside Subscription Card -->
          <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-canvas-bg text-xs">
            <div class="flex items-center gap-4">
              <a href="#plans" class="font-semibold text-secondary hover:underline">Change Plan</a>
              <span class="text-stone-300">•</span>
              <a href="#pause" class="font-semibold text-on-surface hover:text-secondary transition-colors">Pause Membership</a>
            </div>
            <a href="#cancel" class="font-semibold text-error hover:underline flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">cancel</span>
              <span>Cancel Subscription</span>
            </a>
          </div>

        </div>

        <!-- MIDDLE SECTION: Compare Plans & Capabilities -->
        <section id="plans" class="flex flex-col gap-6 pt-2">
          
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 class="font-headline-lg text-headline-lg font-bold text-on-surface">Compare Plans &amp; Capabilities</h2>
              <p class="font-body-md text-body-md text-text-muted mt-0.5">Scale your family workspace as your kids grow or family routines demand more automation.</p>
            </div>

            <!-- Annual / Monthly Toggle -->
            <div class="flex items-center p-1 bg-surface rounded-xl border border-[rgba(43,45,66,0.06)] shadow-xs self-start">
              <button class="px-3.5 py-1.5 rounded-lg bg-secondary text-white font-label-sm text-label-sm font-semibold shadow-xs">Annual (20% OFF)</button>
              <button class="px-3.5 py-1.5 rounded-lg text-text-muted font-label-sm text-label-sm font-medium hover:text-on-surface transition-colors">Monthly</button>
            </div>
          </div>

          <!-- 3 Plans Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
            
            <!-- Tier 1: Starter Family -->
            <div class="p-6 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-sm flex flex-col justify-between gap-6 hover:shadow-md transition-shadow">
              <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted px-2.5 py-1 rounded-md bg-stone-100">ENTRY LEVEL</span>
                </div>
                <div>
                  <h3 class="font-headline-md text-xl font-bold text-on-surface">Starter Family</h3>
                  <p class="text-xs text-text-muted mt-1">Lightweight grocery lists for couples or roommates.</p>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-3xl font-extrabold text-on-surface">$0</span>
                  <span class="text-xs text-text-muted font-medium">/ forever</span>
                </div>
                <ul class="space-y-3 text-xs text-on-surface pt-2">
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Up to 2 family members</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Basic categorized grocery lists</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>1 Saved geofenced location</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Non-priority breakthrough voice calls</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Standard sync speed (non-priority)</span>
                  </li>
                </ul>
              </div>

              <button class="w-full py-3 rounded-xl border border-stone-300 hover:bg-stone-50 font-label-md text-label-md text-on-surface transition-all">
                Downgrade to Starter
              </button>
            </div>

            <!-- Tier 2: Pilot Family Tier (Featured Current Plan) -->
            <div class="p-6 rounded-3xl bg-surface border-2 border-secondary shadow-[0_8px_30px_rgba(55,104,71,0.12)] relative flex flex-col justify-between gap-6">
              <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3.5 py-0.5 rounded-full bg-secondary text-white font-label-sm text-[11px] font-bold uppercase tracking-wider shadow-md">
                Current Household Plan
              </div>
              
              <div class="flex flex-col gap-4 pt-1">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-secondary px-2.5 py-1 rounded-md bg-sage-soft">MOST POPULAR</span>
                </div>
                <div>
                  <h3 class="font-headline-md text-xl font-bold text-on-surface">Pilot Family Tier</h3>
                  <p class="text-xs text-text-muted mt-1">Ideal for active households managing multi-stop errands.</p>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-3xl font-extrabold text-on-surface">$14.99</span>
                  <span class="text-xs text-text-muted font-medium">/ month</span>
                </div>
                <ul class="space-y-3 text-xs text-on-surface pt-2">
                  <li class="flex items-start gap-2 font-medium">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
                    <span>Up to 5 family members</span>
                  </li>
                  <li class="flex items-start gap-2 font-medium">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
                    <span>10 Saved geofenced places</span>
                  </li>
                  <li class="flex items-start gap-2 font-medium">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
                    <span>30 AI critical voice dispatch calls</span>
                  </li>
                  <li class="flex items-start gap-2 font-medium">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
                    <span>Real-time instant synchronization</span>
                  </li>
                  <li class="flex items-start gap-2 font-medium">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
                    <span>Priority proxy shopping &amp; item grouping</span>
                  </li>
                </ul>
              </div>

              <button class="w-full py-3 rounded-xl bg-forest-soft text-secondary font-label-md text-label-md font-bold flex items-center justify-center gap-1.5 cursor-default">
                <span class="material-symbols-outlined text-[18px]">check</span>
                <span>Currently Active</span>
              </button>
            </div>

            <!-- Tier 3: Household Pro -->
            <div class="p-6 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-sm relative flex flex-col justify-between gap-6 hover:shadow-md transition-shadow">
              <div class="absolute -top-3 right-6 px-3 py-0.5 rounded-full bg-error text-white font-label-sm text-[10px] font-bold uppercase tracking-wider shadow-md">
                Recommended
              </div>

              <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-tertiary px-2.5 py-1 rounded-md bg-coral-soft">UNRESTRICTED CLOUD</span>
                </div>
                <div>
                  <h3 class="font-headline-md text-xl font-bold text-on-surface">Household Pro</h3>
                  <p class="text-xs text-text-muted mt-1">Multi-generational families, nannies, caregivers &amp; shared homes.</p>
                </div>
                <div class="flex items-baseline gap-1 my-2">
                  <span class="text-3xl font-extrabold text-on-surface">$24.99</span>
                  <span class="text-xs text-text-muted font-medium">/ month</span>
                </div>
                <ul class="space-y-3 text-xs text-on-surface pt-2">
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Unlimited family members &amp; caregivers</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Unlimited geofenced locations</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Unlimited breakthrough voice escalation</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Smart receipt OCR scanning &amp; split billing</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    <span>Dedicated emergency household dispatcher</span>
                  </li>
                </ul>
              </div>

              <button class="w-full py-3 rounded-xl bg-secondary hover:bg-primary text-white font-label-md text-label-md font-semibold transition-all shadow-sm active:scale-98 flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">rocket_launch</span>
                <span>Upgrade to Household Pro</span>
              </button>
            </div>

          </div>

        </section>

        <!-- BOTTOM SECTION 1: Payment Methods & Billing Info -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          
          <!-- Payment Methods Card -->
          <div class="p-6 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-sm flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="font-headline-sm text-lg font-bold text-on-surface">Payment Methods</h3>
                  <p class="text-xs text-text-muted">Cards and digital wallets used for household billing</p>
                </div>
                <button class="text-xs font-bold text-secondary hover:underline flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px]">add</span> Add New
                </button>
              </div>

              <!-- List of Cards -->
              <div class="space-y-3">
                <!-- Method 1: Visa -->
                <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex items-center justify-between">
                  <div class="flex items-center gap-3.5">
                    <div class="w-12 h-8 rounded-lg bg-surface border border-stone-200 flex items-center justify-center font-bold text-blue-800 text-xs tracking-wider">
                      VISA
                    </div>
                    <div class="flex flex-col">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-xs text-on-surface">Visa ending in 4242</span>
                        <span class="px-2 py-0.2 rounded-full bg-forest-soft text-secondary text-[10px] font-bold">Default</span>
                      </div>
                      <span class="text-[11px] text-text-muted">Expires 08/2028 • Tanvir Rahman</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 text-xs">
                    <button class="text-text-muted hover:text-on-surface font-medium">Edit</button>
                    <span class="text-stone-300">•</span>
                    <button class="text-error font-medium hover:underline">Delete</button>
                  </div>
                </div>

                <!-- Method 2: Apple Pay -->
                <div class="p-4 rounded-2xl bg-canvas-bg border border-[rgba(43,45,66,0.04)] flex items-center justify-between">
                  <div class="flex items-center gap-3.5">
                    <div class="w-12 h-8 rounded-lg bg-black text-white flex items-center justify-center text-[11px] font-bold">
                      Pay
                    </div>
                    <div class="flex flex-col">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-xs text-on-surface">Apple Pay (Sarah Rahman)</span>
                        <span class="px-2 py-0.2 rounded-full bg-stone-200 text-text-muted text-[10px] font-bold">Backup</span>
                      </div>
                      <span class="text-[11px] text-text-muted">Linked to •••• 9121</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 text-xs">
                    <button class="text-secondary hover:underline font-medium">Set Default</button>
                    <span class="text-stone-300">•</span>
                    <button class="text-error font-medium hover:underline">Remove</button>
                  </div>
                </div>
              </div>
            </div>

            <p class="text-[11px] text-text-muted flex items-center gap-1.5 pt-2 border-t border-canvas-bg">
              <span class="material-symbols-outlined text-[14px] text-secondary">lock</span>
              <span>256-bit encrypted via Stripe. We never store raw card numbers.</span>
            </p>
          </div>

          <!-- Billing Information Card -->
          <div class="p-6 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-sm flex flex-col justify-between gap-5">
            <div>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="font-headline-sm text-lg font-bold text-on-surface">Billing Information</h3>
                  <p class="text-xs text-text-muted">Tax tax records and household invoice delivery contact</p>
                </div>
                <button class="text-xs font-bold text-secondary hover:underline">
                  Edit Details
                </button>
              </div>

              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-label-sm text-text-muted uppercase text-[10px] font-bold tracking-wider">Billing Name</span>
                  <p class="font-semibold text-on-surface mt-1">Tanvir Rahman</p>
                </div>
                <div>
                  <span class="font-label-sm text-text-muted uppercase text-[10px] font-bold tracking-wider">Receipt Email</span>
                  <p class="font-semibold text-on-surface mt-1">tanvir@familyessentials.app</p>
                </div>
                <div class="col-span-2">
                  <span class="font-label-sm text-text-muted uppercase text-[10px] font-bold tracking-wider">Billing Address</span>
                  <p class="font-semibold text-on-surface mt-1">742 Evergreen Terrace, Brooklyn, NY 11201, United States</p>
                </div>
                <div>
                  <span class="font-label-sm text-text-muted uppercase text-[10px] font-bold tracking-wider">Tax ID / VAT</span>
                  <p class="text-text-muted mt-1">Not specified (Optional for consumer household accounts)</p>
                </div>
                <div>
                  <span class="font-label-sm text-text-muted uppercase text-[10px] font-bold tracking-wider">Household Currency</span>
                  <p class="font-semibold text-on-surface mt-1">USD ($) - Auto-Exchange enabled</p>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-text-muted pt-2 border-t border-canvas-bg">
              <span>Receipts are sent automatically after each renewal.</span>
              <a href="#test" class="text-secondary font-semibold hover:underline">Send Test Receipt</a>
            </div>
          </div>

        </div>

        <!-- BOTTOM SECTION 2: Invoice & Receipt History Table -->
        <div class="p-6 md:p-8 rounded-3xl bg-surface border border-[rgba(43,45,66,0.06)] shadow-sm flex flex-col gap-5">
          
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 class="font-headline-sm text-xl font-bold text-on-surface">Invoice &amp; Receipt History</h3>
              <p class="text-xs text-text-muted">Archived billing statements, tax receipts, and upgrade charges.</p>
            </div>

            <div class="flex items-center gap-2">
              <div class="flex items-center bg-canvas-bg p-1 rounded-xl text-xs">
                <button class="px-2.5 py-1 rounded-lg bg-surface font-semibold text-on-surface shadow-xs">All Time</button>
                <button class="px-2.5 py-1 rounded-lg text-text-muted hover:text-on-surface">2026</button>
                <button class="px-2.5 py-1 rounded-lg text-text-muted hover:text-on-surface">2025</button>
                <button class="px-2.5 py-1 rounded-lg text-text-muted hover:text-on-surface">2024</button>
              </div>
              <button class="px-3 py-1.5 rounded-xl border border-stone-200 text-xs font-semibold text-on-surface hover:bg-stone-50 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[15px]">file_download</span> Export All (.ZIP)
              </button>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-canvas-bg text-[10px] uppercase font-bold tracking-wider text-text-muted">
                  <th class="py-3 px-2">Invoice ID</th>
                  <th class="py-3 px-4">Date</th>
                  <th class="py-3 px-4">Description / Plan</th>
                  <th class="py-3 px-4">Amount</th>
                  <th class="py-3 px-4">Status</th>
                  <th class="py-3 px-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-canvas-bg font-medium text-on-surface">
                <tr class="hover:bg-canvas-bg/60 transition-colors">
                  <td class="py-4 px-2 font-mono text-secondary font-bold">INV-2024-003</td>
                  <td class="py-4 px-4 text-text-muted">Oct 18, 2024</td>
                  <td class="py-4 px-4">Family Essentials Pilot Tier (Annual Subscription - 5 Family Seats)</td>
                  <td class="py-4 px-4 font-bold text-on-surface">$179.88</td>
                  <td class="py-4 px-4">
                    <span class="px-2.5 py-0.5 rounded-full bg-forest-soft text-secondary text-[10px] font-bold">PAID</span>
                  </td>
                  <td class="py-4 px-4 text-right">
                    <button class="px-2.5 py-1 rounded-lg bg-surface-container-low hover:bg-sage-soft text-secondary font-semibold transition-colors flex items-center gap-1 ml-auto">
                      <span class="material-symbols-outlined text-[14px]">download</span> PDF
                    </button>
                  </td>
                </tr>
                <tr class="hover:bg-canvas-bg/60 transition-colors">
                  <td class="py-4 px-2 font-mono text-secondary font-bold">INV-2023-001</td>
                  <td class="py-4 px-4 text-text-muted">Oct 18, 2023</td>
                  <td class="py-4 px-4">Family Essentials Starter Tier (Annual Trial Booster)</td>
                  <td class="py-4 px-4 font-bold text-on-surface">$49.00</td>
                  <td class="py-4 px-4">
                    <span class="px-2.5 py-0.5 rounded-full bg-forest-soft text-secondary text-[10px] font-bold">PAID</span>
                  </td>
                  <td class="py-4 px-4 text-right">
                    <button class="px-2.5 py-1 rounded-lg bg-surface-container-low hover:bg-sage-soft text-secondary font-semibold transition-colors flex items-center gap-1 ml-auto">
                      <span class="material-symbols-outlined text-[14px]">download</span> PDF
                    </button>
                  </td>
                </tr>
                <tr class="hover:bg-canvas-bg/60 transition-colors">
                  <td class="py-4 px-2 font-mono text-secondary font-bold">INV-2023-002</td>
                  <td class="py-4 px-4 text-text-muted">Apr 12, 2023</td>
                  <td class="py-4 px-4">Add-on Voice Call Pack (20 Critical Dispatch credits)</td>
                  <td class="py-4 px-4 font-bold text-on-surface">$15.00</td>
                  <td class="py-4 px-4">
                    <span class="px-2.5 py-0.5 rounded-full bg-forest-soft text-secondary text-[10px] font-bold">PAID</span>
                  </td>
                  <td class="py-4 px-4 text-right">
                    <button class="px-2.5 py-1 rounded-lg bg-surface-container-low hover:bg-sage-soft text-secondary font-semibold transition-colors flex items-center gap-1 ml-auto">
                      <span class="material-symbols-outlined text-[14px]">download</span> PDF
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- FOOTER CARD: No-Surprise Family Billing Promise -->
        <div class="p-6 rounded-3xl bg-forest-soft/70 border border-secondary/20 flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-secondary text-white flex items-center justify-center shrink-0 shadow-sm">
              <span class="material-symbols-outlined text-[26px]">health_and_safety</span>
            </div>
            <div class="flex flex-col">
              <h4 class="font-headline-sm text-base font-bold text-secondary">No-Surprise Family Billing Promise</h4>
              <p class="text-xs text-on-surface leading-relaxed max-w-2xl mt-0.5">
                Cancel anytime with 1-click. If you downgrade, your family data, grocery lists, favorite geofence perimeter history, and chore records stay safely preserved for <strong>90 days</strong> in case household members return.
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <button class="px-4 py-2.5 rounded-xl bg-white text-on-surface font-label-md text-xs font-semibold shadow-xs hover:bg-stone-50 transition-colors">
              Retention Policy
            </button>
            <button class="px-4 py-2.5 rounded-xl bg-secondary hover:bg-primary text-white font-label-md text-xs font-semibold shadow-xs transition-colors">
              Contact Billing Concierge
            </button>
          </div>
        </div>

      </div>
    </main>
  </div>

</body>
</html>
