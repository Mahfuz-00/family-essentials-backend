<!DOCTYPE html>

<html lang="en"><head><meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/><meta content="web_standard" name="shell-type"/><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&amp;display=swap" rel="stylesheet"/><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/><style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style><script src="https://cdn.tailwindcss.com"></script><script id="tailwind-config">tailwind.config = { darkMode: "class", theme: { extend: { "colors": { "tertiary-fixed-dim": "#ffb4a1", "surface-tint": "#506356", "on-surface": "#181a2e", "surface-bright": "#fbf8ff", "inverse-on-surface": "#f1efff", "on-primary": "#ffffff", "inverse-primary": "#b7ccbc", "secondary-fixed": "#b9efc5", "on-primary-container": "#f6fff6", "tertiary-container": "#b65a41", "surface-container": "#edecff", "on-secondary-fixed": "#00210e", "secondary-container": "#b6edc2", "on-primary-fixed-variant": "#384b3f", "background": "#fbf8ff", "on-surface-variant": "#434844", "surface-container-highest": "#e0e0fc", "primary": "#4d6054", "text-muted": "#6C757D", "error": "#E76F51", "inverse-surface": "#2d2f44", "primary-fixed-dim": "#b7ccbc", "outline": "#737873", "outline-variant": "#c3c8c2", "on-secondary": "#ffffff", "primary-container": "#66796c", "tertiary": "#97422b", "surface-variant": "#e0e0fc", "on-tertiary": "#ffffff", "surface": "#FFFFFF", "surface-container-high": "#e6e6ff", "primary-fixed": "#d2e8d8", "surface-container-low": "#f4f2ff", "on-primary-fixed": "#0d1f15", "on-error": "#ffffff", "on-background": "#181a2e", "tertiary-fixed": "#ffdbd2", "secondary-fixed-dim": "#9dd3aa", "coral-soft": "#FCECE7", "on-tertiary-container": "#fffbff", "on-error-container": "#93000a", "error-container": "#ffdad6", "on-secondary-fixed-variant": "#1e5031", "success": "#81B29A", "on-tertiary-fixed-variant": "#7c2e19", "on-tertiary-fixed": "#3c0800", "forest-soft": "#E4EDE7", "secondary": "#376847", "canvas-bg": "#FDFCF7", "on-secondary-container": "#3b6d4b", "warning": "#F4A261", "surface-dim": "#d7d8f4", "surface-container-lowest": "#ffffff", "sage-soft": "EBF0ED" }, "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" }, "spacing": { "space-xl": "2rem", "gutter": "1rem", "space-xs": "0.25rem", "margin-mobile": "1rem", "space-md": "1rem", "margin": "1.25rem", "space-sm": "0.5rem", "space-lg": "1.5rem", "margin-desktop": "2rem", "gutter-sm": "0.75rem" }, "fontFamily": { "label-lg": ["Plus Jakarta Sans"], "label-sm": ["Plus Jakarta Sans"], "body-sm": ["Plus Jakarta Sans"], "body-lg": ["Plus Jakarta Sans"], "display-lg-mobile": ["Plus Jakarta Sans"], "headline-lg": ["Plus Jakarta Sans"], "headline-md": ["Plus Jakarta Sans"], "display-lg": ["Plus Jakarta Sans"], "body-md": ["Plus Jakarta Sans"], "headline-sm": ["Plus Jakarta Sans"], "label-md": ["Plus Jakarta Sans"] }, "fontSize": { "label-lg": ["14px", { "lineHeight": "20px", "fontWeight": "600" }], "label-sm": ["11px", { "lineHeight": "14px", "letterSpacing": "0.02em", "fontWeight": "700" }], "body-sm": ["12px", { "lineHeight": "18px", "fontWeight": "400" }], "body-lg": ["16px", { "lineHeight": "24px", "fontWeight": "400" }], "display-lg-mobile": ["26px", { "lineHeight": "34px", "letterSpacing": "-0.01em", "fontWeight": "700" }], "headline-lg": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "700" }], "headline-md": ["20px", { "lineHeight": "28px", "fontWeight": "600" }], "display-lg": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }], "body-md": ["14px", { "lineHeight": "20px", "fontWeight": "400" }], "headline-sm": ["18px", { "lineHeight": "24px", "fontWeight": "600" }], "label-md": ["12px", { "lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "600" }] } } } }</script></head><body class="bg-canvas-bg font-body-md text-on-surface min-h-screen flex flex-col justify-between"><header class="fixed top-0 left-0 right-0 z-50 bg-canvas-bg/90 backdrop-blur-md shadow-[0_1px_8px_rgba(43,45,66,0.04)]"><div class="h-16 w-full max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop flex items-center justify-between"><div class="flex items-center gap-space-sm"><div class="w-9 h-9 rounded-xl bg-forest-soft flex items-center justify-center text-secondary"><span class="material-symbols-outlined text-[20px]">cottage</span></div><div class="flex flex-col"><span class="font-headline-sm text-headline-sm text-on-surface tracking-tight leading-none">Family Essentials</span><span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mt-0.5">Access Portal</span></div></div><nav class="flex items-center gap-space-md" data-active-classes="bg-sage-soft text-secondary rounded-lg px-space-sm py-1 font-label-md"><a class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors" data-path="client-family-login" href="#">Client Login</a><a aria-current="page" class="transition-colors bg-sage-soft text-secondary rounded-lg px-space-sm py-1 font-label-md" data-path="tenant-admin-login" href="#">Admin Portal</a><a class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors" data-path="register-family" href="#">Register</a><a class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors" data-path="help-center" href="#">Support</a></nav><div class="flex items-center gap-space-sm"><a class="p-2 text-on-surface-variant hover:text-on-surface transition-colors" href="#" title="Get Assistance"><span class="material-symbols-outlined text-[20px]">help</span></a><div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center"><span class="material-symbols-outlined text-on-primary text-[18px]">person</span></div></div></div></header><main class="w-full flex-1 pt-16 bg-canvas-bg flex flex-col"><div class="flex flex-col w-full">
<div class="w-full min-h-[calc(100vh-4rem)] flex flex-col lg:flex-row bg-canvas-bg">
<!-- Left Panel: Warm Branding, Feature Value & Domestic Social Proof -->
<div class="w-full lg:w-[46%] xl:w-[48%] relative flex flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-16 bg-forest-soft/60 overflow-hidden">
<!-- Decorative Organic Gradients & Floating Household Accents -->
<div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-secondary-fixed/30 blur-3xl pointer-events-none"></div>
<div class="absolute bottom-10 right-0 w-80 h-80 rounded-full bg-coral-soft/50 blur-2xl pointer-events-none"></div>
<div class="absolute top-1/2 left-1/3 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-surface/40 blur-xl pointer-events-none"></div>
<!-- Top Branding Pill -->
<div class="relative z-10 flex flex-col gap-6">
<div class="inline-flex items-center gap-2 self-start bg-surface px-4 py-1.5 rounded-full shadow-sm text-secondary font-label-md text-label-md">
<span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
<span>Household Logistics &amp; Family Care</span>
<span class="text-outline-variant font-normal">|</span>
<span class="text-on-surface-variant font-label-sm">v2.4 Tenant Hub</span>
</div>
<!-- Headline & Editorial Narrative -->
<div class="space-y-4 max-w-xl">
<h1 class="font-display-lg text-display-lg text-on-surface tracking-tight leading-tight">
            Never forget a household need or urgent errand again.
          </h1>
<p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
            The synchronized domestic operating system trusted by over 1,200 busy modern families to organize grocery runs, shared lists, and geofenced reminders.
          </p>
</div>
<!-- Rich Visual: Modern Family Routine Illustration / Hero Card -->
<div class="relative w-full rounded-2xl overflow-hidden shadow-md bg-surface group my-2">
<div class="h-44 sm:h-52 w-full bg-cover bg-center transition-transform duration-700 group-hover:scale-105" data-alt="Warm atmospheric documentary photo of a modern multicultural family enjoying breakfast together around an organized kitchen wooden island. Morning golden light streams through expansive windows showing fresh fruit, baby formula, shopping lists on a tablet, and a calm joyful atmosphere with muted sage and cream home tones." style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCiFRUZYy1DS1QNw_-fboh_tz6-beFSLi555v1MGyndmf3619Mgn90v6mW7EE5RxB6fIwGl1jKnIhpw87hM7_GWySFcEUb37LoxOYqqSaAbKzg7Nm_DkgWq2rqe1MKJsycXIxoEAK8UZXJdTE3l0WTKEQTSW9d3R2G3KTCpo-QktlNGB-qmu9vY4r66aFVVJy6qUfYLeuzuesofWAPzRj-E2PShLNW4R5f_iyHgjZxR-jrN_LT4Wul6Vw')"></div>
<div class="absolute inset-0 bg-gradient-to-t from-on-surface/80 via-on-surface/20 to-transparent flex items-end p-5">
<div class="text-surface flex items-center justify-between w-full">
<div>
<p class="font-label-lg text-label-lg text-on-primary">The Brooklyn Tanvir Residence</p>
<p class="font-body-sm text-body-sm text-surface-variant">Active Sync: Kitchen Display, 2 iPhones, 1 Apple Watch</p>
</div>
<span class="material-symbols-outlined text-secondary-fixed text-[28px]" style="font-variation-settings: 'FILL' 1;">home_pin</span>
</div>
</div>
</div>
<!-- Bento Feature Stack (Zero borders, tinted card elevation) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
<!-- Feature 1 -->
<div class="bg-surface/90 backdrop-blur-sm p-4 rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="w-8 h-8 rounded-lg bg-secondary-fixed/50 flex items-center justify-center text-secondary mb-2">
<span class="material-symbols-outlined text-[18px]">near_me</span>
</div>
<div>
<p class="font-label-lg text-label-lg text-on-surface">Geofence Alerts</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-snug">Auto-pings parents when driving past Target or CVS.</p>
</div>
</div>
<!-- Feature 2 -->
<div class="bg-surface/90 backdrop-blur-sm p-4 rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="w-8 h-8 rounded-lg bg-coral-soft flex items-center justify-center text-tertiary mb-2">
<span class="material-symbols-outlined text-[18px]">e911_emergency</span>
</div>
<div>
<p class="font-label-lg text-label-lg text-on-surface">Urgent Dispatch</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-snug">Priority voice alerts for baby formula &amp; urgent RX.</p>
</div>
</div>
<!-- Feature 3 -->
<div class="bg-surface/90 backdrop-blur-sm p-4 rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="w-8 h-8 rounded-lg bg-forest-soft flex items-center justify-center text-primary mb-2">
<span class="material-symbols-outlined text-[18px]">sync</span>
</div>
<div>
<p class="font-label-lg text-label-lg text-on-surface">Omni Sync</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-snug">Instant state update across iOS, Android &amp; kitchen web.</p>
</div>
</div>
</div>
</div>
<!-- Left Panel Bottom: Testimonial & Compliance Badge -->
<div class="relative z-10 pt-8 mt-6 flex flex-col gap-4">
<div class="bg-surface/85 backdrop-blur-sm p-4 rounded-2xl shadow-sm">
<div class="flex items-center gap-1 text-warning mb-1.5">
<span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="font-label-sm text-label-sm text-on-surface ml-1 font-bold">4.9/5 Rating</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface italic leading-normal">
            “Family Essentials cut our evening grocery coordination chaos to zero. We're never stuck without milk or pediatric vitamins.”
          </p>
<div class="flex items-center justify-between mt-2 pt-2 text-on-surface-variant font-label-sm text-label-sm">
<span>The Tanvir Family, Brooklyn NY</span>
<span class="text-secondary font-medium">Verified Household</span>
</div>
</div>
<div class="flex items-center gap-3 text-on-surface-variant font-label-sm text-label-sm px-1">
<div class="flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-success">lock</span>
<span>SOC2 Type II Certified</span>
</div>
<span class="text-outline-variant">•</span>
<div class="flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-success">verified_user</span>
<span>Encrypted Domestic Vault</span>
</div>
</div>
</div>
</div>
<!-- Right Panel: High-Craft Interactive Auth & OTP Flow -->
<div class="w-full lg:w-[54%] xl:w-[52%] flex flex-col justify-center px-6 sm:px-12 lg:px-16 xl:px-24 py-10 bg-canvas-bg">
<div class="max-w-xl w-full mx-auto space-y-6">
<!-- Top Flow Selector Pill (Sign In vs Register vs 6-Digit OTP Demo) -->
<div class="bg-forest-soft p-1 rounded-2xl flex items-center justify-between shadow-inner">
<button class="flex-1 py-2.5 text-center font-label-lg text-label-lg rounded-xl transition-all duration-200 bg-surface text-secondary shadow-sm" id="tab-signin" onclick="switchAuthMode('signin')">
            Sign In
          </button>
<button class="flex-1 py-2.5 text-center font-label-lg text-label-lg rounded-xl transition-all duration-200 text-on-surface-variant hover:text-on-surface" id="tab-register" onclick="switchAuthMode('register')">
            Register Family
          </button>
<button class="py-2.5 px-3.5 text-center font-label-sm text-label-sm rounded-xl transition-all duration-200 text-tertiary flex items-center gap-1 hover:bg-coral-soft/50" id="tab-otp" onclick="switchAuthMode('otp')" title="Preview OTP Verification state (/verify)">
<span class="material-symbols-outlined text-[16px]">pin</span>
<span>OTP View</span>
</button>
</div>
<!-- Dynamic Header Message based on State -->
<div class="space-y-1" id="auth-header-container">
<h2 class="font-headline-lg text-headline-lg text-on-surface tracking-tight" id="auth-title">
            Welcome back to your Household
          </h2>
<p class="font-body-md text-body-md text-on-surface-variant" id="auth-subtitle">
            Access your shared pantry inventory, routine chore rotations, and real-time errand dispatch.
          </p>
</div>
<!-- AUTH FORM CONTAINER (Sign In / Register) -->
<div class="bg-surface p-6 sm:p-8 rounded-3xl shadow-[0_4px_20px_-2px_rgba(43,45,66,0.06)] space-y-5" id="auth-form-card">
<!-- Household Subdomain / Identifier Field -->
<div class="space-y-1.5">
<div class="flex items-center justify-between">
<label class="font-label-lg text-label-lg text-on-surface" for="household-id">Household Subdomain or Identifier</label>
<span class="font-label-sm text-label-sm text-on-surface-variant">e.g. tanvir-household</span>
</div>
<div class="relative flex items-center">
<div class="absolute left-3.5 text-on-surface-variant flex items-center pointer-events-none">
<span class="material-symbols-outlined text-[20px]">cottage</span>
</div>
<input class="w-full h-12 pl-11 pr-32 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all shadow-inner" id="household-id" placeholder="family-workspace" type="text" value="tanvir-household"/>
<span class="absolute right-3.5 font-label-sm text-label-sm text-on-surface-variant pointer-events-none bg-forest-soft px-2 py-0.5 rounded-md">
                .familyessentials.app
              </span>
</div>
</div>
<!-- Email Address Input -->
<div class="space-y-1.5">
<label class="font-label-lg text-label-lg text-on-surface" for="auth-email">Household Admin Email</label>
<div class="relative flex items-center">
<div class="absolute left-3.5 text-on-surface-variant flex items-center pointer-events-none">
<span class="material-symbols-outlined text-[20px]">mail</span>
</div>
<input class="w-full h-12 pl-11 pr-4 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all shadow-inner" id="auth-email" placeholder="name@familydomain.com" type="email" value="samir@tanvir-residence.org"/>
</div>
</div>
<!-- Phone Number Input (Registration & Multi-Factor dispatch) -->
<div class="space-y-1.5" id="phone-group">
<div class="flex items-center justify-between">
<label class="font-label-lg text-label-lg text-on-surface" for="auth-phone">SMS Errand &amp; Urgent Dispatch Number</label>
<span class="font-label-sm text-label-sm text-secondary">Verified for voice call priority</span>
</div>
<div class="flex gap-2">
<div class="h-12 px-3 bg-canvas-bg rounded-xl flex items-center gap-1.5 font-label-md text-label-md text-on-surface shadow-inner shrink-0 cursor-pointer">
<span>🇺🇸</span>
<span class="font-semibold">+1</span>
<span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
</div>
<div class="relative flex-1 flex items-center">
<input class="w-full h-12 px-3.5 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all shadow-inner" id="auth-phone" placeholder="(555) 000-0000" type="tel" value="(555) 019-2849"/>
</div>
</div>
</div>
<!-- Password Input with Show/Hide Toggle -->
<div class="space-y-1.5">
<div class="flex items-center justify-between">
<label class="font-label-lg text-label-lg text-on-surface" for="auth-password">Household Master Password</label>
<a class="font-label-sm text-label-sm text-secondary hover:underline" href="#">Forgot password?</a>
</div>
<div class="relative flex items-center">
<div class="absolute left-3.5 text-on-surface-variant flex items-center pointer-events-none">
<span class="material-symbols-outlined text-[20px]">lock</span>
</div>
<input class="w-full h-12 pl-11 pr-11 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all shadow-inner" id="auth-password" placeholder="••••••••••••" type="password" value="FamilyVault2024!"/>
<button class="absolute right-3.5 text-on-surface-variant hover:text-on-surface focus:outline-none" onclick="togglePasswordVisibility()" title="Toggle password visibility" type="button">
<span class="material-symbols-outlined text-[20px]" id="pwd-icon">visibility</span>
</button>
</div>
</div>
<!-- Extra registration specific fields (Hidden on standard login) -->
<div class="hidden space-y-4 pt-1" id="register-extra-fields">
<div class="grid grid-cols-2 gap-3">
<div class="space-y-1">
<label class="font-label-sm text-label-sm text-on-surface">Household Size</label>
<select class="w-full h-11 px-3 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
<option>2 Members (Couple)</option>
<option selected="">3-4 Members (Family)</option>
<option>5+ Members (Multi-Gen)</option>
</select>
</div>
<div class="space-y-1">
<label class="font-label-sm text-label-sm text-on-surface">Primary Timezone</label>
<select class="w-full h-11 px-3 bg-canvas-bg rounded-xl font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
<option selected="">America / New York (EST)</option>
<option>America / Chicago (CST)</option>
<option>America / Los Angeles (PST)</option>
</select>
</div>
</div>
</div>
<!-- Remember Device & Consent Checkbox -->
<div class="space-y-3 pt-1">
<label class="flex items-center gap-2.5 cursor-pointer select-none">
<input checked="" class="w-4 h-4 rounded text-secondary accent-secondary focus:ring-secondary/30" type="checkbox"/>
<span class="font-body-sm text-body-sm text-on-surface">
                Remember this trusted domestic workstation for 30 days
              </span>
</label>
<div class="hidden" id="register-terms">
<label class="flex items-start gap-2.5 cursor-pointer select-none">
<input checked="" class="w-4 h-4 mt-0.5 rounded text-secondary accent-secondary focus:ring-secondary/30 shrink-0" type="checkbox"/>
<span class="font-body-sm text-body-sm text-on-surface-variant leading-tight">
                  I consent to shared domestic location broadcasts and acknowledge the 
                  <a class="text-secondary underline" href="#">Family Data Privacy Policy</a> and 
                  <a class="text-secondary underline" href="#">Terms of Service</a>.
                </span>
</label>
</div>
</div>
<!-- Primary Submit Button -->
<button class="w-full h-13 py-3.5 bg-secondary hover:bg-secondary/90 active:scale-[0.98] transition-all rounded-xl text-on-primary font-headline-sm text-headline-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg" id="auth-submit-btn" onclick="simulateAuthSubmit()">
<span id="btn-text">Sign In to Family Workspace</span>
<span class="material-symbols-outlined text-[20px]" id="btn-icon">arrow_forward</span>
<div class="hidden w-5 h-5 rounded-full border-2 border-surface border-t-transparent animate-spin" id="btn-spinner"></div>
</button>
<!-- Social SSO Partition -->
<div class="relative py-2 flex items-center justify-center">
<div class="absolute inset-0 flex items-center"><div class="w-full h-px bg-forest-soft"></div></div>
<span class="relative bg-surface px-4 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
              or continue with secure family identity
            </span>
</div>
<!-- SSO Buttons Grid -->
<div class="grid grid-cols-2 gap-3">
<button class="h-11 px-4 bg-canvas-bg hover:bg-forest-soft/60 rounded-xl flex items-center justify-center gap-2 font-label-md text-label-md text-on-surface transition-colors shadow-sm" type="button">
<svg class="w-4 h-4" viewbox="0 0 24 24">
<path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.36c.64-.78 1.08-1.86.96-2.95-1 .04-2.15.67-2.8 1.44-.58.66-1.09 1.77-.96 2.83 1.12.09 2.2-.59 2.8-1.32z" fill="currentColor"></path>
</svg>
<span>Apple ID</span>
</button>
<button class="h-11 px-4 bg-canvas-bg hover:bg-forest-soft/60 rounded-xl flex items-center justify-center gap-2 font-label-md text-label-md text-on-surface transition-colors shadow-sm" type="button">
<svg class="w-4 h-4" viewbox="0 0 24 24">
<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"></path>
<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"></path>
<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"></path>
</svg>
<span>Google Account</span>
</button>
</div>
</div>
<!-- OTP VERIFICATION INTERACTIVE STATE CONTAINER (Hidden by default, toggleable via top tab) -->
<div class="hidden bg-surface p-6 sm:p-8 rounded-3xl shadow-[0_4px_20px_-2px_rgba(43,45,66,0.06)] space-y-6" id="otp-verification-card">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-forest-soft text-secondary flex items-center justify-center">
<span class="material-symbols-outlined text-[24px]">phonelink_ring</span>
</div>
<div>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Multi-Factor Code Dispatch</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">6-digit household safety PIN verification</p>
</div>
</div>
<!-- Channel confirmation pill -->
<div class="bg-forest-soft/70 px-3.5 py-2.5 rounded-xl flex items-center justify-between text-on-surface">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-secondary">mark_chat_unread</span>
<span class="font-label-md text-label-md">Sent to +1 (555) 019-2849 &amp; samir@***.org</span>
</div>
<span class="font-label-sm text-label-sm text-secondary bg-surface px-2 py-0.5 rounded shadow-sm">Instant</span>
</div>
<!-- 6-digit OTP Inputs -->
<div class="space-y-2">
<label class="font-label-lg text-label-lg text-on-surface block text-center">
              Enter 6-digit verification passkey
            </label>
<div class="flex justify-center items-center gap-2 sm:gap-3">
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-secondary bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" type="text" value="4"/>
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-secondary bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" type="text" value="8"/>
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-secondary bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" type="text" value="2"/>
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-on-surface bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" placeholder="•" type="text"/>
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-on-surface bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" placeholder="•" type="text"/>
<input class="w-12 h-14 text-center font-display-lg text-display-lg text-on-surface bg-canvas-bg rounded-xl focus:ring-2 focus:ring-secondary/50 focus:outline-none shadow-sm" maxlength="1" placeholder="•" type="text"/>
</div>
</div>
<!-- Countdown timer & Resend -->
<div class="flex items-center justify-between text-on-surface-variant font-label-md text-label-md pt-1">
<span class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-tertiary">timer</span>
<span>Expires in <strong class="text-tertiary font-semibold" id="otp-timer">00:48</strong></span>
</span>
<button class="text-secondary hover:underline font-semibold" type="button">Resend New SMS PIN</button>
</div>
<!-- Submit OTP Button -->
<button class="w-full h-13 py-3.5 bg-secondary hover:bg-secondary/90 transition-all rounded-xl text-on-primary font-headline-sm text-headline-sm flex items-center justify-center gap-2 shadow-md" onclick="completeOtpVerification()">
<span>Verify &amp; Open Family Dashboard</span>
<span class="material-symbols-outlined text-[20px]">verified</span>
</button>
</div>
<!-- Cross-Portal Links / Landlord Admin Switcher -->
<div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 text-on-surface-variant font-body-sm text-body-sm">
<a class="inline-flex items-center gap-1 text-on-surface hover:text-secondary transition-colors font-medium" href="#">
<span class="material-symbols-outlined text-[18px]">domain</span>
<span>Switch to Landlord &amp; Building Portal →</span>
</a>
<a class="inline-flex items-center gap-1 hover:text-on-surface transition-colors" href="#">
<span class="material-symbols-outlined text-[16px]">contact_support</span>
<span>Family Support Desk</span>
</a>
</div>
</div>
</div>
</div>
</div>
<script>
  let currentAuthMode = 'signin';
  let otpSeconds = 48;
  let otpInterval;

  function switchAuthMode(mode) {
    currentAuthMode = mode;
    const tabSignin = document.getElementById('tab-signin');
    const tabRegister = document.getElementById('tab-register');
    const tabOtp = document.getElementById('tab-otp');
    const authHeaderTitle = document.getElementById('auth-title');
    const authHeaderSubtitle = document.getElementById('auth-subtitle');
    const authFormCard = document.getElementById('auth-form-card');
    const otpVerificationCard = document.getElementById('otp-verification-card');
    const registerExtra = document.getElementById('register-extra-fields');
    const registerTerms = document.getElementById('register-terms');
    const btnText = document.getElementById('btn-text');

    // Reset tab active states
    [tabSignin, tabRegister, tabOtp].forEach(el => {
      el.className = "flex-1 py-2.5 text-center font-label-lg text-label-lg rounded-xl transition-all duration-200 text-on-surface-variant hover:text-on-surface";
    });
    tabOtp.className = "py-2.5 px-3.5 text-center font-label-sm text-label-sm rounded-xl transition-all duration-200 text-tertiary flex items-center gap-1 hover:bg-coral-soft/50";

    if (mode === 'signin') {
      tabSignin.className = "flex-1 py-2.5 text-center font-label-lg text-label-lg rounded-xl transition-all duration-200 bg-surface text-secondary shadow-sm";
      authFormCard.classList.remove('hidden');
      otpVerificationCard.classList.add('hidden');
      registerExtra.classList.add('hidden');
      registerTerms.classList.add('hidden');
      authHeaderTitle.innerText = "Welcome back to your Household";
      authHeaderSubtitle.innerText = "Access your shared pantry inventory, routine chore rotations, and real-time errand dispatch.";
      btnText.innerText = "Sign In to Family Workspace";
    } else if (mode === 'register') {
      tabRegister.className = "flex-1 py-2.5 text-center font-label-lg text-label-lg rounded-xl transition-all duration-200 bg-surface text-secondary shadow-sm";
      authFormCard.classList.remove('hidden');
      otpVerificationCard.classList.add('hidden');
      registerExtra.classList.remove('hidden');
      registerTerms.classList.remove('hidden');
      authHeaderTitle.innerText = "Register a New Family Workspace";
      authHeaderSubtitle.innerText = "Create your private multi-device domestic sanctuary. Setup takes under 2 minutes.";
      btnText.innerText = "Create Workspace & Continue";
    } else if (mode === 'otp') {
      tabOtp.className = "py-2.5 px-3.5 text-center font-label-sm text-label-sm rounded-xl transition-all duration-200 bg-tertiary text-on-tertiary shadow-sm flex items-center gap-1";
      authFormCard.classList.add('hidden');
      otpVerificationCard.classList.remove('hidden');
      authHeaderTitle.innerText = "Two-Factor Household Check";
      authHeaderSubtitle.innerText = "Enter the 6-digit confirmation key dispatched to your family's verified primary line.";
      startOtpCountdown();
    }
  }

  function togglePasswordVisibility() {
    const pwdInput = document.getElementById('auth-password');
    const pwdIcon = document.getElementById('pwd-icon');
    if (pwdInput.type === 'password') {
      pwdInput.type = 'text';
      pwdIcon.innerText = 'visibility_off';
    } else {
      pwdInput.type = 'password';
      pwdIcon.innerText = 'visibility';
    }
  }

  function simulateAuthSubmit() {
    const btnText = document.getElementById('btn-text');
    const btnIcon = document.getElementById('btn-icon');
    const btnSpinner = document.getElementById('btn-spinner');

    btnText.innerText = "Securing Connection...";
    btnIcon.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    setTimeout(() => {
      btnSpinner.classList.add('hidden');
      btnIcon.classList.remove('hidden');
      // Automatically advance to OTP verification screen for demonstration
      switchAuthMode('otp');
    }, 900);
  }

  function startOtpCountdown() {
    clearInterval(otpInterval);
    otpSeconds = 48;
    const timerDisplay = document.getElementById('otp-timer');
    otpInterval = setInterval(() => {
      otpSeconds--;
      if (otpSeconds <= 0) {
        clearInterval(otpInterval);
        timerDisplay.innerText = "00:00";
      } else {
        const sec = otpSeconds < 10 ? '0' + otpSeconds : otpSeconds;
        timerDisplay.innerText = '00:' + sec;
      }
    }, 1000);
  }

  function completeOtpVerification() {
    alert("Household authenticated successfully! Redirecting to Family Essentials Dashboard...");
  }
</script></main><footer class="w-full bg-canvas-bg shadow-[0_-1px_6px_rgba(43,45,66,0.02)]"><div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop py-space-md flex flex-col sm:flex-row items-center justify-between gap-space-sm text-on-surface-variant"><div class="flex items-center gap-space-xs font-body-sm text-body-sm"><span class="material-symbols-outlined text-[16px] text-secondary">verified_user</span><span>© 2024 Family Essentials Inc. Multi-Tenant Domestic Management.</span></div><div class="flex items-center gap-space-md font-label-sm text-label-sm"><a class="hover:text-on-surface transition-colors" href="#">Privacy Policy</a><a class="hover:text-on-surface transition-colors" href="#">Terms of Service</a><a class="hover:text-on-surface transition-colors" href="#">SOC-2 Security</a><a class="hover:text-on-surface transition-colors" href="#">Help Center</a></div></div></footer></body></html>