<!DOCTYPE html>

<html lang="en"><head><meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/><meta content="web_dashboard" name="shell-type"/><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/><link href="https://fonts.googleapis.com" rel="preconnect"/><link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/><style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style><script src="https://cdn.tailwindcss.com"></script><script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{"colors":{"on-primary-fixed-variant":"#384b3f","forest-soft":"#E4EDE7","canvas-bg":"#FDFCF7","on-tertiary-container":"#fffbff","surface-container":"#edecff","on-error":"#ffffff","secondary-fixed-dim":"#9dd3aa","error":"#E76F51","surface-dim":"#d7d8f4","on-secondary-container":"#3b6d4b","background":"#fbf8ff","on-primary-container":"#f6fff6","warning":"#F4A261","secondary-fixed":"#b9efc5","surface-tint":"#506356","primary-fixed":"#d2e8d8","primary-fixed-dim":"#b7ccbc","surface":"#FFFFFF","secondary-container":"#b6edc2","tertiary-fixed-dim":"#ffb4a1","outline-variant":"#c3c8c2","on-primary-fixed":"#0d1f15","inverse-on-surface":"#f1efff","surface-container-high":"#e6e6ff","on-secondary-fixed-variant":"#1e5031","tertiary":"#97422b","on-error-container":"#93000a","success":"#81B29A","surface-container-highest":"#e0e0fc","surface-bright":"#fbf8ff","error-container":"#ffdad6","on-secondary-fixed":"#00210e","inverse-surface":"#2d2f44","primary-container":"#66796c","on-secondary":"#ffffff","on-background":"#181a2e","surface-container-lowest":"#ffffff","sage-soft":"#EBF0ED","surface-variant":"#e0e0fc","on-tertiary-fixed":"#3c0800","coral-soft":"#FCECE7","outline":"#737873","on-tertiary-fixed-variant":"#7c2e19","on-tertiary":"#ffffff","on-primary":"#ffffff","surface-container-low":"#f4f2ff","on-surface-variant":"#434844","inverse-primary":"#b7ccbc","primary":"#4d6054","tertiary-fixed":"#ffdbd2","on-surface":"#181a2e","secondary":"#376847","text-muted":"#6C757D","tertiary-container":"#b65a41"},"borderRadius":{"DEFAULT":"0.25rem","lg":"0.5rem","xl":"0.75rem","full":"9999px"},"spacing":{"space-md":"1rem","margin-mobile":"1rem","space-sm":"0.5rem","margin":"1.25rem","space-lg":"1.5rem","gutter":"1rem","gutter-sm":"0.75rem","margin-desktop":"2rem","space-xs":"0.25rem","space-xl":"2rem"},"fontFamily":{"label-lg":["Plus Jakarta Sans"],"headline-sm":["Plus Jakarta Sans"],"headline-md":["Plus Jakarta Sans"],"display-lg":["Plus Jakarta Sans"],"body-md":["Plus Jakarta Sans"],"headline-lg":["Plus Jakarta Sans"],"body-lg":["Plus Jakarta Sans"],"body-sm":["Plus Jakarta Sans"],"display-lg-mobile":["Plus Jakarta Sans"],"label-md":["Plus Jakarta Sans"],"label-sm":["Plus Jakarta Sans"]},"fontSize":{"label-lg":["14px",{"lineHeight":"20px","fontWeight":"600"}],"headline-sm":["18px",{"lineHeight":"24px","fontWeight":"600"}],"headline-md":["20px",{"lineHeight":"28px","fontWeight":"600"}],"display-lg":["32px",{"lineHeight":"40px","letterSpacing":"-0.02em","fontWeight":"700"}],"body-md":["14px",{"lineHeight":"20px","fontWeight":"400"}],"headline-lg":["24px",{"lineHeight":"32px","letterSpacing":"-0.01em","fontWeight":"700"}],"body-lg":["16px",{"lineHeight":"24px","fontWeight":"400"}],"body-sm":["12px",{"lineHeight":"18px","fontWeight":"400"}],"display-lg-mobile":["26px",{"lineHeight":"34px","letterSpacing":"-0.01em","fontWeight":"700"}],"label-md":["12px",{"lineHeight":"16px","letterSpacing":"0.01em","fontWeight":"600"}],"label-sm":["11px",{"lineHeight":"14px","letterSpacing":"0.02em","fontWeight":"700"}]}}}};</script></head><body class="bg-canvas-bg font-body-md text-on-surface antialiased min-h-screen"><aside class="fixed left-0 top-0 h-full w-72 bg-surface z-50 flex flex-col justify-between shadow-[0_1px_8px_rgba(0,0,0,0.04)]"><div class="flex flex-col"><a href="{{ url('/') }}" class="h-16 px-space-md flex items-center gap-space-sm bg-surface hover:opacity-90 transition-opacity"><div class="w-10 h-10 rounded-xl bg-forest-soft flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[24px]">shield</span></div><div class="flex flex-col"><span class="font-headline-sm text-headline-sm leading-tight text-on-surface font-bold">Family Essentials</span><span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">Landlord Platform</span></div></a><div class="px-space-md pt-space-md pb-space-xs"><span class="font-label-sm text-label-sm uppercase tracking-wider text-text-muted px-space-sm">Operations &amp; Control</span></div><nav class="flex flex-col gap-1 px-space-md py-space-xs" data-active-classes="bg-forest-soft text-primary font-bold shadow-[0_1px_3px_rgba(43,45,66,0.02)]"><a class="flex items-center px-space-sm py-2.5 rounded-xl text-on-surface-variant font-label-lg text-label-lg hover:bg-sage-soft hover:text-primary transition-all" href="{{ url('/admin/dashboard') }}" data-path="overview-analytics">Overview / Analytics</a><a aria-current="page" class="flex items-center px-space-sm py-2.5 rounded-xl transition-all bg-forest-soft text-primary font-bold shadow-[0_1px_3px_rgba(43,45,66,0.02)]" href="{{ url('/admin/tenants') }}" data-path="tenants-workspaces">Tenants &amp; Workspaces</a><a class="flex items-center px-space-sm py-2.5 rounded-xl text-on-surface-variant font-label-lg text-label-lg hover:bg-sage-soft hover:text-primary transition-all" href="{{ url('/admin/dashboard') }}#subscriptions" data-path="subscriptions-mrr">Subscriptions &amp; MRR</a><a class="flex items-center px-space-sm py-2.5 rounded-xl text-on-surface-variant font-label-lg text-label-lg hover:bg-sage-soft hover:text-primary transition-all" href="{{ url('/admin/dashboard') }}#queue" data-path="queue-workers-jobs">Queue Workers &amp; Jobs</a><a class="flex items-center px-space-sm py-2.5 rounded-xl text-on-surface-variant font-label-lg text-label-lg hover:bg-sage-soft hover:text-primary transition-all" href="{{ url('/admin/audit-logs') }}" data-path="audit-security-logs">Audit &amp; Security Logs</a><a class="flex items-center px-space-sm py-2.5 rounded-xl text-on-surface-variant font-label-lg text-label-lg hover:bg-sage-soft hover:text-primary transition-all" href="{{ url('/admin/dashboard') }}#settings" data-path="system-settings">System Settings</a></nav></div><div class="p-space-md flex flex-col gap-space-sm bg-surface"><div class="bg-forest-soft/60 rounded-xl p-space-sm flex flex-col gap-1"><div class="flex items-center justify-between"><span class="font-label-sm text-label-sm text-secondary font-bold flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>All Systems Operational</span><span class="font-label-sm text-label-sm text-primary font-semibold">99.98%</span></div><span class="font-body-sm text-body-sm text-text-muted">Redis Cluster OK • Zero Degraded</span></div><div class="flex items-center justify-between p-space-sm rounded-xl bg-canvas-bg shadow-[0_1px_3px_0_rgba(43,45,66,0.02)]"><div class="flex items-center gap-space-sm min-w-0"><div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-on-primary text-[18px]">person</span></div><div class="flex flex-col min-w-0"><span class="font-label-md text-label-md text-on-surface truncate font-bold">Alex Thorne</span><span class="font-body-sm text-body-sm text-text-muted truncate">Platform Admin</span></div></div><a href="{{ url('/admin/login') }}" title="Sign Out" class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-sage-soft rounded-lg transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span></a></div></div></aside><div class="pl-72"><header class="fixed top-0 left-72 right-0 h-16 bg-surface/80 backdrop-blur-xl z-40 px-space-lg flex items-center justify-between shadow-[0_1px_8px_rgba(0,0,0,0.04)]"><div class="flex items-center gap-space-lg flex-1 max-w-2xl"><div class="hidden xl:flex items-center gap-2 text-on-surface-variant font-label-md text-label-md whitespace-nowrap"><span class="text-text-muted">Platform Admin</span><span class="material-symbols-outlined text-[16px] text-text-muted">chevron_right</span><span class="text-on-surface font-semibold">Tenant Management Console</span></div><div class="relative w-full max-w-md"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span><input class="w-full pl-10 pr-space-md py-2 text-body-sm font-body-sm bg-canvas-bg rounded-xl text-on-surface placeholder:text-text-muted outline-none focus:bg-surface focus:shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] transition-all" placeholder="Search tenants, workspaces, transactions, or log trace IDs..." type="text"/></div></div><div class="flex items-center gap-space-sm shrink-0"><div class="hidden sm:flex items-center gap-2 bg-sage-soft px-3 py-1.5 rounded-full"><span class="material-symbols-outlined text-secondary text-[16px]">bolt</span><span class="font-label-sm text-label-sm text-secondary font-semibold">Live Redis Queue: 24 active</span></div><div class="hidden md:flex items-center gap-1.5 bg-forest-soft px-3 py-1.5 rounded-full"><span class="w-2 h-2 rounded-full bg-success"></span><span class="font-label-sm text-label-sm text-primary font-semibold">System Healthy</span></div><button class="relative w-10 h-10 flex items-center justify-center rounded-xl text-on-surface-variant hover:bg-canvas-bg hover:text-on-surface transition-colors" type="button"><span class="material-symbols-outlined text-[22px]">notifications</span><span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-error ring-2 ring-surface"></span></button><div class="flex items-center pl-2"><div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center"><span class="material-symbols-outlined text-on-primary text-[18px]">person</span></div></div></div></header><main class="w-full pt-16 bg-canvas-bg px-space-lg min-h-screen"><div class="flex flex-col w-full pb-16 space-y-6">
<!-- Top Hero & Context Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pt-2">
<div>
<div class="flex items-center gap-2 mb-1.5">
<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm">
<span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
          PostgreSQL Multi-Tenant v16.2
        </span>
<span class="text-text-muted text-xs">•</span>
<span class="text-text-muted font-label-sm text-label-sm">Schema Namespace Isolation</span>
</div>
<h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">Tenant Workspaces &amp; Client Families</h1>
<p class="font-body-md text-body-md text-text-muted max-w-3xl mt-1">
        Manage multi-tenant isolation, provision household schemas, inspect RLS boundaries, and monitor client family subscriptions.
      </p>
</div>
<!-- Quick Action / Global Buttons -->
<div class="flex items-center gap-3 shrink-0">
<button class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-secondary text-white font-label-lg text-label-lg shadow-sm hover:bg-primary transition-all" onclick="document.getElementById('provisionDrawer').classList.toggle('hidden')">
<span class="material-symbols-outlined text-[20px]">add_circle</span>
<span>Provision New Tenant</span>
</button>
</div>
</div>
<!-- Quick Metrics KPI Row -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
<!-- Card 1 -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] flex flex-col justify-between">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">Total Tenants</span>
<span class="w-7 h-7 rounded-lg bg-sage-soft flex items-center justify-center text-secondary">
<span class="material-symbols-outlined text-[18px]">domain</span>
</span>
</div>
<div class="mt-3 flex items-baseline gap-2">
<span class="font-headline-md text-headline-md text-on-surface font-bold">1,248</span>
<span class="inline-flex items-center font-label-sm text-label-sm text-secondary font-semibold">
<span class="material-symbols-outlined text-[14px]">trending_up</span> +14 MoM
        </span>
</div>
<span class="font-body-sm text-body-sm text-text-muted mt-1">Active household instances</span>
</div>
<!-- Card 2 -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] flex flex-col justify-between">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">Active Families</span>
<span class="w-2.5 h-2.5 rounded-full bg-success ring-4 ring-forest-soft"></span>
</div>
<div class="mt-3 flex items-baseline gap-2">
<span class="font-headline-md text-headline-md text-on-surface font-bold">1,180</span>
<span class="font-label-sm text-label-sm text-text-muted">94.5% health</span>
</div>
<span class="font-body-sm text-body-sm text-text-muted mt-1">Paying subscriber clusters</span>
</div>
<!-- Card 3 -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] flex flex-col justify-between">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">In Free Trial</span>
<span class="w-2.5 h-2.5 rounded-full bg-warning ring-4 ring-warning/10"></span>
</div>
<div class="mt-3 flex items-baseline gap-2">
<span class="font-headline-md text-headline-md text-on-surface font-bold">56</span>
<span class="font-label-sm text-label-sm text-secondary font-semibold">4.4% flow</span>
</div>
<span class="font-body-sm text-body-sm text-text-muted mt-1">14-day evaluation window</span>
</div>
<!-- Card 4 -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] flex flex-col justify-between">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">Suspended / Past Due</span>
<span class="w-2.5 h-2.5 rounded-full bg-error ring-4 ring-coral-soft"></span>
</div>
<div class="mt-3 flex items-baseline gap-2">
<span class="font-headline-md text-headline-md text-error font-bold">12</span>
<span class="font-label-sm text-label-sm text-error">Critical alert</span>
</div>
<span class="font-body-sm text-body-sm text-text-muted mt-1">Dunning cycle &amp; TOS locks</span>
</div>
<!-- Card 5 -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] flex flex-col justify-between col-span-2 md:col-span-1">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-text-muted uppercase tracking-wider">RLS Schema Health</span>
<span class="w-7 h-7 rounded-lg bg-forest-soft flex items-center justify-center text-secondary">
<span class="material-symbols-outlined text-[18px]">verified_user</span>
</span>
</div>
<div class="mt-3 flex items-baseline gap-1.5">
<span class="font-headline-md text-headline-md text-secondary font-bold">100%</span>
<span class="font-label-sm text-label-sm text-secondary font-semibold">Strict Guard</span>
</div>
<span class="font-body-sm text-body-sm text-text-muted mt-1">Zero cross-tenant bleed</span>
</div>
</div>
<!-- Datagrid Action Toolbar & Filter Hub -->
<div class="bg-surface rounded-2xl p-4 shadow-[0_1px_3px_0_rgba(43,45,66,0.02)] border border-black/[0.04] space-y-3.5">
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
<!-- Search input -->
<div class="relative flex-1 max-w-xl">
<span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
<input class="w-full pl-11 pr-4 py-2 text-body-sm font-body-sm bg-canvas-bg rounded-xl text-on-surface placeholder:text-text-muted focus:bg-surface focus:shadow-sm focus:outline-none focus:ring-1 focus:ring-secondary/40 transition-all border border-black/[0.03]" id="tenantSearchInput" oninput="filterTableRows()" placeholder="Filter by Household name, Tenant ID, primary admin email, or domain..." type="text"/>
</div>
<!-- Bulk Actions & Utility Buttons -->
<div class="flex items-center gap-2 self-end lg:self-auto shrink-0">
<span class="hidden text-label-sm font-label-sm text-secondary px-2 py-1 rounded bg-forest-soft font-semibold" id="selectedCount">0 Selected</span>
<button class="hidden items-center gap-1.5 px-3 py-2 rounded-xl text-error bg-coral-soft hover:bg-error-container font-label-md text-label-md transition-all" id="bulkSuspendBtn">
<span class="material-symbols-outlined text-[16px]">pause_circle</span>
          Bulk Suspend
        </button>
<button class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-on-surface-variant bg-canvas-bg hover:bg-sage-soft font-label-md text-label-md transition-all border border-black/[0.04]" onclick="exportCSVMock()">
<span class="material-symbols-outlined text-[16px]">file_download</span>
          Export CSV
        </button>
<button class="inline-flex items-center gap-1 px-3 py-2 rounded-xl text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-all bg-canvas-bg border border-black/[0.04]">
<span class="material-symbols-outlined text-[16px]">tune</span>
          Column Views
        </button>
</div>
</div>
<!-- Filter Pills Row -->
<div class="flex flex-wrap items-center gap-2 pt-1 border-t border-black/[0.04]">
<span class="text-xs font-label-sm text-text-muted uppercase tracking-wider pr-1">Filter by:</span>
<!-- Status Segments -->
<div class="inline-flex p-1 bg-canvas-bg rounded-xl gap-1 border border-black/[0.03]">
<button class="filter-status-btn px-2.5 py-1 rounded-lg text-label-sm font-label-sm bg-surface shadow-xs text-secondary font-bold transition-all" onclick="setFilter('status', 'all', this)">All Statuses (1,248)</button>
<button class="filter-status-btn px-2.5 py-1 rounded-lg text-label-sm font-label-sm text-text-muted hover:text-on-surface transition-all" onclick="setFilter('status', 'active', this)">Active (1,180)</button>
<button class="filter-status-btn px-2.5 py-1 rounded-lg text-label-sm font-label-sm text-text-muted hover:text-on-surface transition-all" onclick="setFilter('status', 'trial', this)">Trial (56)</button>
<button class="filter-status-btn px-2.5 py-1 rounded-lg text-label-sm font-label-sm text-text-muted hover:text-on-surface transition-all" onclick="setFilter('status', 'past-due', this)">Delinquent / Suspended (12)</button>
</div>
<!-- Tier Dropdown simulation -->
<div class="relative">
<select class="appearance-none pl-3 pr-8 py-1.5 rounded-xl bg-canvas-bg text-on-surface-variant font-label-sm text-label-sm border border-black/[0.04] focus:outline-none cursor-pointer" id="tierSelect" onchange="filterTableRows()">
<option value="all">All Tiers</option>
<option value="household pro">Household Pro ($24.99)</option>
<option value="pilot family">Pilot Family ($14.99)</option>
<option value="starter free">Starter ($0)</option>
</select>
<span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted text-[16px] pointer-events-none">expand_more</span>
</div>
<!-- Region Dropdown simulation -->
<div class="relative">
<select class="appearance-none pl-3 pr-8 py-1.5 rounded-xl bg-canvas-bg text-on-surface-variant font-label-sm text-label-sm border border-black/[0.04] focus:outline-none cursor-pointer" id="regionSelect">
<option value="all">All Clusters &amp; Regions</option>
<option value="us-east-1">us-east-1 (N. Virginia Aurora)</option>
<option value="eu-central-1">eu-central-1 (Frankfurt Aurora)</option>
<option value="us-west-2">us-west-2 (Oregon)</option>
</select>
<span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted text-[16px] pointer-events-none">expand_more</span>
</div>
<div class="ml-auto flex items-center gap-1.5 text-text-muted font-body-sm text-body-sm">
<span class="w-2 h-2 rounded-full bg-success"></span>
<span>Auto-sync enabled</span>
</div>
</div>
</div>
<!-- Primary Tenant Datagrid / Table -->
<div class="bg-surface rounded-2xl shadow-[0_2px_8px_-1px_rgba(43,45,66,0.04)] border border-black/[0.04] overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse min-w-[1100px]" id="tenantsTable">
<thead>
<tr class="bg-canvas-bg/80 border-b border-black/[0.04] text-text-muted font-label-sm text-label-sm uppercase tracking-wider select-none">
<th class="py-3.5 px-4 w-12 text-center">
<input class="rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" id="selectAllCheckbox" onchange="toggleSelectAll(this)" type="checkbox"/>
</th>
<th class="py-3.5 px-4 font-semibold">Tenant ID &amp; Schema</th>
<th class="py-3.5 px-4 font-semibold">Household / Family Name</th>
<th class="py-3.5 px-4 font-semibold">Primary Admin Contact</th>
<th class="py-3.5 px-4 font-semibold">Subscription Tier</th>
<th class="py-3.5 px-4 font-semibold">Allocated Quota &amp; Usage</th>
<th class="py-3.5 px-4 font-semibold">Provisioned</th>
<th class="py-3.5 px-4 font-semibold">Status</th>
<th class="py-3.5 px-4 text-right font-semibold">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-black/[0.03] font-body-md text-body-md text-on-surface" id="tableBody">
<!-- Row 1: The Tanvir Household (Flagship) -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="active" data-tier="pilot family">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-1042</span>
<div class="flex items-center gap-1.5 mt-0.5">
<span class="inline-block px-1.5 py-0.5 rounded font-mono text-[10px] bg-canvas-bg text-secondary border border-secondary/20">tenant_1042_schema</span>
<span class="inline-flex items-center text-[10px] text-secondary font-semibold" title="Row Level Security enforced">
<span class="material-symbols-outlined text-[12px]">verified</span> RLS
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-forest-soft text-secondary font-bold flex items-center justify-center shrink-0">
                  TH
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Tanvir Household</span>
<div class="flex items-center gap-1.5 text-text-muted font-body-sm text-body-sm">
<span class="material-symbols-outlined text-[14px]">location_on</span> Brooklyn, NY
                    <span class="px-1.5 py-0.2 rounded-full bg-forest-soft text-secondary text-[10px] font-bold">Flagship Pilot</span>
</div>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Tanvir Rahman</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">tanvir@familyessentials.app</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5 flex items-center gap-1">
<span class="material-symbols-outlined text-[13px]">phone</span> +1 (555) 019-2849
                </span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="inline-flex items-center gap-1 font-label-md text-label-md text-secondary font-bold">
<span class="material-symbols-outlined text-[15px]">stars</span> Pilot Family Tier
                </span>
<span class="font-body-sm text-body-sm text-text-muted">$14.99/mo • Annual Billed</span>
<span class="text-[11px] text-text-muted/80">Renews Oct 18, 2025</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>4/5 Members</span>
<span class="text-on-surface font-medium">80%</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-secondary h-full rounded-full w-4/5"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">4 Geofences • 12 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Oct 18, 2023</span>
<span class="font-label-sm text-label-sm text-text-muted">1 yr ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Active
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<div class="relative inline-block text-left">
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors" onclick="toggleActionMenu('menu-1042')">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
<div class="hidden absolute right-0 mt-1 w-48 bg-surface rounded-xl shadow-lg border border-black/[0.06] py-1 z-30" id="menu-1042">
<a class="flex items-center gap-2 px-3.5 py-2 text-label-sm text-on-surface hover:bg-sage-soft" href="#">
<span class="material-symbols-outlined text-[16px]">database</span> Inspect Schema
                  </a>
<a class="flex items-center gap-2 px-3.5 py-2 text-label-sm text-on-surface hover:bg-sage-soft" href="#">
<span class="material-symbols-outlined text-[16px]">switch_account</span> Impersonate Admin
                  </a>
<a class="flex items-center gap-2 px-3.5 py-2 text-label-sm text-on-surface hover:bg-sage-soft" href="#">
<span class="material-symbols-outlined text-[16px]">credit_card</span> Manage Billing Tier
                  </a>
<div class="border-t border-black/[0.04] my-1"></div>
<a class="flex items-center gap-2 px-3.5 py-2 text-label-sm text-error hover:bg-coral-soft" href="#">
<span class="material-symbols-outlined text-[16px]">block</span> Suspend Tenant
                  </a>
</div>
</div>
</td>
</tr>
<!-- Row 2: The Miller Household -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="active" data-tier="household pro">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-8492</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_8492_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-sage-soft text-primary font-bold flex items-center justify-center shrink-0">
                  MH
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Miller Household</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Austin, TX
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">David Miller</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">david.m@millerfamily.net</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (512) 803-1192</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-bold">Household Pro</span>
<span class="font-body-sm text-body-sm text-text-muted">$24.99/mo • Monthly Recurring</span>
<span class="text-[11px] text-text-muted/80">Renews Nov 12, 2024</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>6/Unlimited</span>
<span class="text-secondary font-medium">Uncapped</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-primary h-full rounded-full w-2/3"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">8 Geofences • 45 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Aug 12, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">3 mos ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Active
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<div class="flex items-center justify-end gap-1">
<button class="p-1.5 rounded-lg text-text-muted hover:text-secondary hover:bg-forest-soft transition-colors" title="Inspect RLS">
<span class="material-symbols-outlined text-[18px]">policy</span>
</button>
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors" title="Actions">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</div>
</td>
</tr>
<!-- Row 3: The Rodriguez Family (Trial) -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="trial" data-tier="pilot family">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-7210</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_7210_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-warning/10 text-warning font-bold flex items-center justify-center shrink-0">
                  RF
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Rodriguez Family</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Miami, FL
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Elena Rodriguez</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">elena@rodriguez-casa.com</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (305) 774-9021</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-bold">Pilot Family Tier</span>
<span class="font-body-sm text-body-sm text-warning font-medium">14-Day Free Trial</span>
<span class="text-[11px] text-warning/90 font-semibold">4 days left in trial</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>3/5 Members</span>
<span class="text-on-surface font-medium">60%</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-warning h-full rounded-full w-3/5"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">2 Geofences • 4 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Oct 10, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">10 days ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-warning/15 text-warning font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                Trial
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<div class="flex items-center justify-end gap-1.5">
<button class="px-2 py-1 rounded-lg bg-sage-soft text-secondary font-label-sm text-label-sm font-semibold hover:bg-forest-soft">Convert</button>
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</div>
</td>
</tr>
<!-- Row 4: The Chen Family -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="active" data-tier="household pro">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-9301</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_9301_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-forest-soft text-secondary font-bold flex items-center justify-center shrink-0">
                  CF
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Chen Family</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Seattle, WA
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Marcus Chen</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">marcus.chen@techcorp.io</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (206) 441-3829</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-bold">Household Pro</span>
<span class="font-body-sm text-body-sm text-text-muted">$24.99/mo • Annual Pass</span>
<span class="text-[11px] text-text-muted/80">Renews Feb 04, 2025</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>5/Unlimited</span>
<span class="text-secondary font-medium">Uncapped</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-secondary h-full rounded-full w-1/2"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">6 Geofences • 28 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Feb 04, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">8 mos ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Active
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</td>
</tr>
<!-- Row 5: The Patel Household -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="active" data-tier="pilot family">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-4890</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_4890_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-sage-soft text-primary font-bold flex items-center justify-center shrink-0">
                  PH
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Patel Household</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Chicago, IL
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Priya Patel</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">priya@patelfamily.org</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (312) 555-8902</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-bold">Pilot Family Tier</span>
<span class="font-body-sm text-body-sm text-text-muted">$14.99/mo • Annual Billed</span>
<span class="text-[11px] text-text-muted/80">Renews Nov 22, 2024</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>4/5 Members</span>
<span class="text-on-surface font-medium">80%</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-secondary h-full rounded-full w-4/5"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">3 Geofences • 16 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Nov 22, 2023</span>
<span class="font-label-sm text-label-sm text-text-muted">11 mos ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Active
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</td>
</tr>
<!-- Row 6: The Henderson Family -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="active" data-tier="starter free">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-3319</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_3319_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-canvas-bg border border-black/[0.06] text-text-muted font-bold flex items-center justify-center shrink-0">
                  HF
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Henderson Family</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Denver, CO
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Claire Henderson</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">c.henderson@gmail.com</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (720) 612-4401</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-bold">Starter Free</span>
<span class="font-body-sm text-body-sm text-text-muted">$0/mo (Free Baseline)</span>
<span class="text-[11px] text-text-muted/80">No card on file</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>2/2 Max</span>
<span class="text-error font-semibold">100% (Cap)</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-outline h-full rounded-full w-full"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">1 Geofence • 0 AI Calls</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Sep 01, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">1 mo ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Active
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</td>
</tr>
<!-- Row 7: The Kowalski Estate (Past Due) -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group" data-status="past-due" data-tier="pilot family">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-on-surface">#TEN-2184</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_2184_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-coral-soft text-error font-bold flex items-center justify-center shrink-0">
                  KE
                </div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold group-hover:text-secondary transition-colors">The Kowalski Estate</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> Boston, MA
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Stefan Kowalski</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">stefan.k@verizon.net</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (617) 892-3321</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-error font-bold">Pilot Family Tier</span>
<span class="font-body-sm text-body-sm text-error">Payment Past Due</span>
<span class="text-[11px] text-error font-medium">Grace Period: Day 5 of 7</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<div class="flex justify-between font-label-sm text-label-sm text-text-muted">
<span>4/5 Members</span>
<span class="text-on-surface font-medium">80%</span>
</div>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-warning h-full rounded-full w-4/5"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">3 Geofences • Sync Stalled</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">May 19, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">5 mos ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-coral-soft text-error font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                Past Due
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<div class="flex items-center justify-end gap-1.5">
<button class="px-2 py-1 rounded-lg bg-coral-soft text-error font-label-sm text-label-sm font-semibold hover:bg-error-container">Retry Pay</button>
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</div>
</td>
</tr>
<!-- Row 8: The Bradley Household (Suspended) -->
<tr class="tenant-row hover:bg-forest-soft/30 transition-colors group bg-canvas-bg/30" data-status="past-due" data-tier="household pro">
<td class="py-4 px-4 text-center">
<input class="row-checkbox rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" onchange="updateSelectedCount()" type="checkbox"/>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-mono text-label-md text-label-md font-bold text-text-muted">#TEN-1105</span>
<span class="font-mono text-[10px] text-text-muted mt-0.5">tenant_1105_schema</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex items-center gap-3 opacity-75">
<div class="w-9 h-9 rounded-xl bg-error-container text-error font-bold flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[18px]">lock</span>
</div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-on-surface font-semibold line-through decoration-text-muted">The Bradley Household</span>
<span class="font-body-sm text-body-sm text-text-muted flex items-center gap-1">
<span class="material-symbols-outlined text-[14px]">location_on</span> San Jose, CA
                  </span>
</div>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md font-semibold text-on-surface">Arthur Bradley</span>
<span class="font-body-sm text-body-sm text-text-muted font-mono">abradley@stanford.edu</span>
<span class="font-body-sm text-body-sm text-text-muted mt-0.5">+1 (408) 229-7104</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col">
<span class="font-label-md text-label-md text-text-muted font-bold">Household Pro</span>
<span class="font-body-sm text-body-sm text-error">Suspended by Platform</span>
<span class="text-[11px] text-text-muted">TOS / Rate Limit Flag</span>
</div>
</td>
<td class="py-4 px-4">
<div class="flex flex-col gap-1 max-w-[180px]">
<span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-error font-semibold">
<span class="material-symbols-outlined text-[14px]">lock</span> Locked • Read Only
                </span>
<div class="w-full bg-canvas-bg h-1.5 rounded-full overflow-hidden">
<div class="bg-error h-full rounded-full w-full"></div>
</div>
<span class="font-body-sm text-body-sm text-text-muted">Database access frozen</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex flex-col">
<span class="font-body-sm text-body-sm text-on-surface font-medium">Jan 15, 2024</span>
<span class="font-label-sm text-label-sm text-text-muted">9 mos ago</span>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error-container text-error font-label-sm text-label-sm font-semibold">
<span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                Suspended
              </span>
</td>
<td class="py-4 px-4 text-right whitespace-nowrap">
<div class="flex items-center justify-end gap-1.5">
<button class="px-2 py-1 rounded-lg bg-forest-soft text-secondary font-label-sm text-label-sm font-semibold hover:bg-secondary hover:text-white transition-all">Reactivate</button>
<button class="p-1.5 rounded-lg text-text-muted hover:text-on-surface hover:bg-canvas-bg transition-colors">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination Footer -->
<div class="p-4 bg-surface border-t border-black/[0.04] flex flex-col sm:flex-row items-center justify-between gap-4">
<div class="flex items-center gap-4 text-body-sm font-body-sm text-text-muted">
<span>Showing <strong class="text-on-surface">1 to 8</strong> of <strong>1,248</strong> tenant workspaces</span>
<div class="flex items-center gap-1.5">
<span>Rows:</span>
<select class="bg-canvas-bg rounded-lg border border-black/[0.04] px-2 py-1 text-on-surface font-label-sm text-label-sm focus:outline-none">
<option>10 per page</option>
<option>25 per page</option>
<option>50 per page</option>
<option>100 per page</option>
</select>
</div>
</div>
<!-- Page navigation -->
<div class="flex items-center gap-1">
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-canvas-bg disabled:opacity-40" disabled="">
<span class="material-symbols-outlined text-[18px]">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-secondary font-bold bg-forest-soft font-label-sm text-label-sm">1</button>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-canvas-bg font-label-sm text-label-sm">2</button>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-canvas-bg font-label-sm text-label-sm">3</button>
<span class="px-1 text-text-muted font-body-sm text-body-sm">...</span>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-canvas-bg font-label-sm text-label-sm">156</button>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-canvas-bg">
<span class="material-symbols-outlined text-[18px]">chevron_right</span>
</button>
</div>
</div>
</div>
<!-- Multi-Tenant Architecture & Health Callout Banner -->
<div class="bg-gradient-to-r from-forest-soft via-sage-soft to-surface rounded-2xl p-5 border border-secondary/15 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
<div class="flex items-start gap-3.5">
<div class="w-10 h-10 rounded-xl bg-secondary text-white flex items-center justify-center shrink-0 shadow-sm">
<span class="material-symbols-outlined text-[22px]">database</span>
</div>
<div>
<h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Row-Level Security &amp; Isolation Protocol</h4>
<p class="font-body-sm text-body-sm text-text-muted max-w-2xl mt-0.5">
          Each family household operates under isolated schema identifiers with encrypted Supabase auth bindings. Cross-tenant leakage checks are continuously verified every 120 seconds.
        </p>
</div>
</div>
<div class="flex items-center gap-3 shrink-0 self-end md:self-auto">
<button class="px-3.5 py-2 rounded-xl bg-surface text-secondary hover:text-primary font-label-md text-label-md shadow-xs border border-black/[0.04] transition-colors">
        Run Isolation Test Suite
      </button>
<button class="px-3.5 py-2 rounded-xl bg-secondary text-white hover:bg-primary font-label-md text-label-md shadow-sm transition-colors">
        Cluster Topologies
      </button>
</div>
</div>
<!-- FEATURE DRAWER / SLIDE-OUT MODAL: Provision New Tenant Workspace -->
<div class="hidden fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end transition-opacity" id="provisionDrawer">
<div class="w-full max-w-xl bg-surface h-full shadow-2xl flex flex-col justify-between overflow-y-auto border-l border-black/[0.06]">
<!-- Drawer Header -->
<div class="p-6 border-b border-black/[0.05] bg-canvas-bg flex items-start justify-between">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-forest-soft text-secondary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[24px]">domain_add</span>
</div>
<div>
<h3 class="font-headline-md text-headline-md text-on-surface font-bold">Provision New Tenant</h3>
<p class="font-body-sm text-body-sm text-text-muted mt-0.5">Instantiates isolated PostgreSQL schema namespace with automated RLS security policies.</p>
</div>
</div>
<button class="w-8 h-8 rounded-lg flex items-center justify-center text-text-muted hover:text-on-surface hover:bg-surface transition-colors" onclick="document.getElementById('provisionDrawer').classList.add('hidden')">
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
</div>
<!-- Drawer Form Body -->
<div class="p-6 space-y-5 flex-1">
<!-- Step Indicator -->
<div class="flex items-center justify-between pb-3 border-b border-black/[0.04]">
<span class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Step 1 of 2: Namespace &amp; Credentials</span>
<span class="text-xs text-text-muted">Schema: auto-generated</span>
</div>
<!-- Field 1: Workspace Name -->
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">
            Family / Household Workspace Name <span class="text-error">*</span>
</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">home</span>
<input class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md" placeholder="e.g. The O'Connor Household" type="text" value="The O'Connor Household"/>
</div>
<span class="font-body-sm text-body-sm text-text-muted">Target schema namespace: <code class="font-mono text-secondary font-semibold">tenant_oconnor_prod</code></span>
</div>
<!-- Field 2: Admin Full Name & Email -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">Primary Admin Name <span class="text-error">*</span></label>
<input class="w-full px-3.5 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md" placeholder="e.g. Fiona O'Connor" type="text" value="Fiona O'Connor"/>
</div>
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">Admin Email Address <span class="text-error">*</span></label>
<input class="w-full px-3.5 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md" placeholder="fiona@oconnor.family" type="email" value="fiona@oconnor.family"/>
</div>
</div>
<!-- Field 3: Admin Phone -->
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">Admin Contact Phone (SMS Dispatch)</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">smartphone</span>
<input class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md font-mono" placeholder="+1 (555) 000-0000" type="tel" value="+1 (617) 555-0199"/>
</div>
</div>
<!-- Field 4: Initial Subscription Tier -->
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">Initial Subscription Tier</label>
<select class="w-full px-3.5 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md">
<option selected="">Pilot Family Tier ($14.99/mo) - 14 Day Trial Enabled</option>
<option>Household Pro ($24.99/mo) - Full AI &amp; Uncapped Members</option>
<option>Starter Free ($0/mo) - 2 Members Max</option>
</select>
</div>
<!-- Field 5: Database Cluster & Region -->
<div class="space-y-1.5">
<label class="font-label-md text-label-md text-on-surface font-semibold block">Database Cluster &amp; Storage Region</label>
<select class="w-full px-3.5 py-2.5 rounded-xl bg-canvas-bg text-on-surface border border-black/[0.08] focus:bg-surface focus:outline-none focus:ring-2 focus:ring-secondary/30 font-body-md text-body-md">
<option selected="">us-east-multi-rls (Primary Aurora Multi-AZ - N. Virginia)</option>
<option>eu-central-multi-rls (GDPR Compliant Frankfurt Cluster)</option>
<option>us-west-multi-rls (Secondary Failover Node)</option>
</select>
<span class="font-body-sm text-body-sm text-text-muted">Automatic point-in-time recovery and snapshot replication enabled.</span>
</div>
<!-- Field 6: Seed Default Household Data -->
<div class="pt-2">
<label class="flex items-start gap-3 p-3.5 rounded-xl bg-forest-soft/50 border border-secondary/15 cursor-pointer hover:bg-forest-soft/70 transition-colors">
<input checked="" class="mt-1 rounded w-4 h-4 text-secondary accent-secondary focus:ring-0 cursor-pointer" type="checkbox"/>
<div class="flex flex-col">
<span class="font-label-md text-label-md text-on-surface font-semibold">Seed Default Household Taxonomy</span>
<span class="font-body-sm text-body-sm text-text-muted">Generates preset domestic zones: Supermarket, Pharmacy, School, Home Base, along with starter grocery categories.</span>
</div>
</label>
</div>
</div>
<!-- Drawer Footer Actions -->
<div class="p-6 border-t border-black/[0.06] bg-canvas-bg flex items-center justify-between gap-3">
<button class="px-4 py-2.5 rounded-xl text-on-surface-variant hover:text-on-surface font-label-lg text-label-lg transition-colors" onclick="document.getElementById('provisionDrawer').classList.add('hidden')">
          Cancel
        </button>
<button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-secondary text-white font-label-lg text-label-lg shadow-sm hover:bg-primary transition-all" onclick="mockProvisionSuccess()">
<span class="material-symbols-outlined text-[18px]">verified</span>
<span>Provision &amp; Dispatch Magic Link</span>
</button>
</div>
</div>
</div>
</div>
<!-- Client-side Interactive Logic -->
<script>
  // Simple table row search and status filter
  let currentStatusFilter = 'all';

  function setFilter(type, value, btnElement) {
    if (type === 'status') {
      currentStatusFilter = value;
      // Update UI active buttons
      document.querySelectorAll('.filter-status-btn').forEach(btn => {
        btn.classList.remove('bg-surface', 'shadow-xs', 'text-secondary', 'font-bold');
        btn.classList.add('text-text-muted');
      });
      btnElement.classList.add('bg-surface', 'shadow-xs', 'text-secondary', 'font-bold');
      btnElement.classList.remove('text-text-muted');
      filterTableRows();
    }
  }

  function filterTableRows() {
    const searchVal = document.getElementById('tenantSearchInput').value.toLowerCase();
    const tierVal = document.getElementById('tierSelect').value.toLowerCase();
    const rows = document.querySelectorAll('.tenant-row');

    rows.forEach(row => {
      const text = row.innerText.toLowerCase();
      const rowStatus = row.getAttribute('data-status');
      const rowTier = row.getAttribute('data-tier') || '';

      const matchesSearch = text.includes(searchVal);
      const matchesStatus = (currentStatusFilter === 'all') || (rowStatus === currentStatusFilter);
      const matchesTier = (tierVal === 'all') || rowTier.includes(tierVal);

      if (matchesSearch && matchesStatus && matchesTier) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = masterCheckbox.checked;
    });
    updateSelectedCount();
  }

  function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const badge = document.getElementById('selectedCount');
    const bulkSuspend = document.getElementById('bulkSuspendBtn');

    if (checked > 0) {
      badge.textContent = `${checked} Selected`;
      badge.classList.remove('hidden');
      bulkSuspend.classList.remove('hidden');
      bulkSuspend.classList.add('inline-flex');
    } else {
      badge.classList.add('hidden');
      bulkSuspend.classList.add('hidden');
      bulkSuspend.classList.remove('inline-flex');
    }
  }

  function toggleActionMenu(menuId) {
    const menu = document.getElementById(menuId);
    if (menu) {
      menu.classList.toggle('hidden');
    }
  }

  function exportCSVMock() {
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-6 right-6 bg-surface p-4 rounded-xl shadow-lg border border-black/[0.08] flex items-center gap-3 z-50 text-body-sm';
    notification.innerHTML = `
      <span class="material-symbols-outlined text-secondary text-[20px]">check_circle</span>
      <span>Exporting 1,248 tenant workspaces to CSV... Complete!</span>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3200);
  }

  function mockProvisionSuccess() {
    document.getElementById('provisionDrawer').classList.add('hidden');
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-6 right-6 bg-secondary text-white p-4 rounded-xl shadow-xl flex items-center gap-3 z-50 text-body-sm';
    toast.innerHTML = `
      <span class="material-symbols-outlined text-[20px]">task_alt</span>
      <span>Workspace <strong>The O'Connor Household</strong> provisioned. Schema created & magic link dispatched.</span>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }
</script></main></div></body></html>