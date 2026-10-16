<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Platform Admin Login | Family Essentials Landlord Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-[#FDFCF7] text-brand-slate font-sans flex flex-col justify-between antialiased relative overflow-x-hidden selection:bg-brand-primary/20 selection:text-brand-secondary">

  <!-- Subtle Ambient Background Accents -->
  <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-brand-primary/10 blur-3xl"></div>
    <div class="absolute top-1/2 -left-48 w-[450px] h-[450px] rounded-full bg-[#81B29A]/10 blur-3xl"></div>
    <div class="absolute -bottom-32 right-1/4 w-80 h-80 rounded-full bg-brand-secondary/5 blur-2xl"></div>
    <!-- Fine Grid Lines -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#7C908208_1px,transparent_1px),linear-gradient(to_bottom,#7C908208_1px,transparent_1px)] bg-[size:32px_32px]"></div>
  </div>

  <!-- Header / Navigation Minimal Bar -->
  <header class="relative z-10 w-full px-8 py-5 border-b border-[#E8E6DE] bg-white/70 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <!-- Brand Logotype & Shield -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-secondary to-brand-primary flex items-center justify-center text-white shadow-sm ring-1 ring-black/5">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-extrabold text-base tracking-tight text-brand-slate">Family Essentials</span>
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 border border-stone-200">Platform Admin</span>
          </div>
          <p class="text-[11px] font-medium text-brand-muted">Multi-Tenant Landlord Governance Console</p>
        </div>
      </div>

      <!-- Infrastructure Status Pill -->
      <div class="flex items-center gap-4">
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/70 text-emerald-800 text-xs font-semibold">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
          Cluster: us-east-multi-rls (100% Healthy)
        </div>
        <a href="{{ url('/') }}" class="text-xs font-medium text-brand-muted hover:text-brand-slate transition-colors flex items-center gap-1.5">
          <svg class="w-4 h-4 text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          SRE Support
        </a>
      </div>
    </div>
  </header>

  <!-- Main Auth Container -->
  <main class="relative z-10 flex-1 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-[480px]">
      
      <!-- Security Badge Alert at Top of Card -->
      <div class="mb-4 flex items-center justify-between px-4 py-2.5 rounded-xl bg-amber-50/80 border border-amber-200/80 text-amber-900 shadow-sm backdrop-blur-sm text-xs">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-brand-warning flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span class="font-medium">Strict zero-trust network zone</span>
        </div>
        <span class="font-mono text-[10px] text-amber-800 bg-amber-100/70 px-2 py-0.5 rounded font-semibold border border-amber-300/50">TLS 1.3 Strict</span>
      </div>

      <!-- Auth Card -->
      <div class="bg-white rounded-[24px] border border-[#E8E6DE] shadow-[0_12px_36px_rgba(43,45,66,0.06),0_2px_6px_rgba(43,45,66,0.03)] p-8 sm:p-10 relative">
        
        <!-- Header & Logo Icon -->
        <div class="text-center mb-8">
          <div class="mx-auto w-14 h-14 rounded-2xl bg-[#F4F6F4] border border-[#DCE4DE] flex items-center justify-center text-brand-secondary shadow-inner mb-4 ring-4 ring-[#FDFCF7]">
            <svg class="w-7 h-7 text-brand-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold tracking-tight text-brand-slate mb-1">Family Essentials</h1>
          <p class="text-xs font-semibold uppercase tracking-wider text-brand-primary mb-1">Platform Admin Console</p>
          <p class="text-xs text-brand-muted">Authorized Landlord & SRE infrastructure access only</p>
        </div>

        <!-- Form Elements -->
        <form class="space-y-5" action="{{ url('/admin/dashboard') }}" method="GET">
          
          <!-- Email Input -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="admin-email" class="block text-xs font-semibold text-brand-slate">
                Admin Work Email
              </label>
              <span class="text-[11px] font-mono text-brand-muted">SSO or IAM bound</span>
            </div>
            <div class="relative rounded-xl shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                </svg>
              </div>
              <input 
                type="email" 
                id="admin-email" 
                name="email"
                value="admin@familyessentials.app" 
                class="w-full pl-10 pr-4 py-3 bg-[#FDFCF7] border border-[#DCE1DC] rounded-xl text-sm font-medium text-brand-slate placeholder:text-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-primary/30 focus:border-brand-primary transition-all shadow-sm"
                placeholder="admin@familyessentials.app"
                required
              />
            </div>
            <p class="mt-1.5 text-[11px] text-brand-muted flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-emerald-600 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              Verified Supabase Auth root identity
            </p>
          </div>

          <!-- Password Input -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="admin-password" class="block text-xs font-semibold text-brand-slate">
                Master Password
              </label>
              <a href="#reset" class="text-xs font-medium text-brand-secondary hover:underline">
                Forgot password?
              </a>
            </div>
            <div class="relative rounded-xl shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <input 
                type="password" 
                id="admin-password" 
                name="password"
                value="••••••••••••••••••••" 
                class="w-full pl-10 pr-11 py-3 bg-[#FDFCF7] border border-[#DCE1DC] rounded-xl text-sm font-medium text-brand-slate placeholder:text-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-primary/30 focus:border-brand-primary transition-all shadow-sm font-mono tracking-widest"
                required
              />
              <button type="button" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-stone-400 hover:text-brand-slate transition-colors" title="Toggle visibility">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Hardware MFA Key / Token Checkbox -->
          <div class="pt-1 pb-1 flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" checked class="w-4 h-4 rounded border-[#DCE1DC] text-brand-secondary focus:ring-brand-primary/40 focus:ring-offset-0 bg-[#FDFCF7]">
              <span class="text-xs font-medium text-brand-slate">Enforce FIDO2 / Hardware Security Key</span>
            </label>
            <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Yubikey Ready</span>
          </div>

          <!-- Primary Sign In Action -->
          <button 
            type="submit" 
            class="w-full py-3.5 px-4 rounded-xl bg-brand-secondary hover:bg-[#3D6649] active:scale-[0.99] text-white font-semibold text-sm tracking-wide shadow-md shadow-brand-secondary/20 flex items-center justify-center gap-2 transition-all duration-150">
            <span>Sign In to Landlord Console</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>

          <!-- Alternative Auth Options / SSO -->
          <div class="relative py-2">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-[#E8E6DE]"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
              <span class="bg-white px-3 font-semibold text-[10px] tracking-wider text-brand-muted">Or Landlord IAM SSO</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <button type="button" class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-[#DCE1DC] bg-[#FDFCF7] hover:bg-[#F4F6F4] text-xs font-semibold text-brand-slate transition-colors shadow-sm">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
              </svg>
              <span>Google Cloud IAM</span>
            </button>
            <button type="button" class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-[#DCE1DC] bg-[#FDFCF7] hover:bg-[#F4F6F4] text-xs font-semibold text-brand-slate transition-colors shadow-sm">
              <svg class="w-4 h-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
              <span>Okta / SAML 2.0</span>
            </button>
          </div>
        </form>

        <!-- Zero-Trust / Tenant Boundary Notice -->
        <div class="mt-6 pt-5 border-t border-[#F0EFE8] flex items-start gap-2.5">
          <svg class="w-4 h-4 text-brand-primary flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-[11px] text-brand-muted leading-relaxed">
            All sign-in attempts stream immutably to <span class="font-mono text-stone-700 font-medium">ClickHouse / audit_logs</span> with SHA-256 HMAC integrity signatures. Client tenant workspaces remain isolated under PostgreSQL schema level restrictions.
          </p>
        </div>

      </div>

      <!-- Quick Switch to Tenant Family Login -->
      <div class="mt-6 text-center">
        <p class="text-xs text-brand-muted">
          Looking for client household dashboard? 
          <a href="{{ url('/login') }}" class="font-semibold text-brand-secondary hover:underline ml-1">
            Sign In to Household Workspace &rarr;
          </a>
        </p>
      </div>

    </div>
  </main>

  <!-- Persistent Compliance & Audit Footer -->
  <footer class="relative z-10 w-full px-8 py-5 border-t border-[#E8E6DE] bg-white/60 backdrop-blur-sm">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-brand-muted">
      <div class="flex items-center gap-6">
        <span class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span>SOC2 Type II & HIPAA Certified Partition</span>
        </span>
        <span class="hidden md:inline font-mono text-[11px] text-stone-400">Node: rls-gateway-prod-04</span>
        <span class="hidden md:inline font-mono text-[11px] text-stone-400">Build: v2.4.1-rc3</span>
      </div>
      <div class="flex items-center gap-5 text-[11px]">
        <a href="#security" class="hover:text-brand-slate transition-colors">Security Whitepaper</a>
        <span class="text-stone-300">•</span>
        <a href="#privacy" class="hover:text-brand-slate transition-colors">RLS Isolation Spec</a>
        <span class="text-stone-300">•</span>
        <a href="#status" class="hover:text-brand-slate transition-colors">Platform Status</a>
      </div>
    </div>
  </footer>

</body>
</html>
