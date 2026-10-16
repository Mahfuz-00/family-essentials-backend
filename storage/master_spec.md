# UI/UX Stitch Master Specification & Page-by-Page Prompt Guide

**Target Audience for Stitch:** Google Stitch AI UI Generator  
**Architecture:** Flutter (Mobile App) & Laravel MVC (Web Dashboard)  
**SaaS Model:** Multi-Tenant Landlord-Tenant Database (Platform Admin = Landlord; Tenants = Client Families)  

---

## SECTION 1: Design Tokens & Multi-Theme Soft Color Foundation

The platform supports **Three Family-Friendly Soft Themes**, ensuring a modern SaaS look that is calm and approachable for families. Every component must reference these exact tokens.

### Theme 1: "Warm Sage" (Default Family Theme)
* **Canvas Background (`--color-bg`):** `#FDFCF7` (Warm Cream)
* **Surface / Card (`--color-surface`):** `#FFFFFF` (Pure White)
* **Primary Brand (`--color-primary`):** `#7C9082` (Muted Sage Green)
* **Secondary Action (`--color-secondary`):** `#4A7C59` (Deep Forest Teal)
* **Accent / Urgency (`--color-accent`):** `#E07A5F` (Gentle Coral)
* **Text Primary (`--color-text-main`):** `#2B2D42` (Dark Slate)
* **Text Secondary (`--color-text-muted`):** `#6C757D` (Muted Gray)
* **Semantic Success:** `#81B29A`
* **Semantic Warning:** `#F4A261`
* **Semantic Error:** `#E76F51`

### Theme 2: "Coastal Breeze" (Calm Blue Theme)
* **Canvas Background:** `#F4F7F6` | **Surface:** `#FFFFFF`
* **Primary Brand:** `#457B9D` | **Secondary Action:** `#1D3557` | **Accent:** `#E63946`
* **Text Primary:** `#1A1A1A` | **Text Secondary:** `#595959`

### Theme 3: "Sunset Blossom" (Warm Sunset Theme)
* **Canvas Background:** `#FFF9F5` | **Surface:** `#FFFFFF`
* **Primary Brand:** `#D97706` | **Secondary Action:** `#B45309` | **Accent:** `#DC2626`
* **Text Primary:** `#1F2937` | **Text Secondary:** `#4B5563`

---

## SECTION 2: Web Dashboard — Platform Admin (Landlord) Screens

*Goal:* Manage system-wide tenants, subscriptions, and platform health.

### Page W-PA-01: Platform Admin Login
* **URL:** `/admin/login`
* **Layout:** Centered card on canvas background, max-width 480px, soft drop-shadow (`0 10px 25px rgba(0,0,0,0.05)`), border-radius 20px.
* **Header:** Logo icon (Shield icon in Primary color), Title "Family Essentials Platform Admin", Subtitle "Authorized Landlord Access Only".
* **Form Fields:** 
  1. Email Input (floating label, helper text "admin@familyessentials.app").
  2. Password Input (with visibility toggle icon).
* **Actions:** Primary Button "Sign In to Landlord Console" (full width, Secondary color). Footer link: "Forgot Password?".
* **Microcopy & States:** Loading spinner inside button on submit; inline validation for invalid email formats.

### Page W-PA-02: Platform Admin Global Analytics Dashboard
* **URL:** `/admin/dashboard`
* **Layout:** Sidebar Navigation (collapsible) + Top Header Bar + Scrollable Content Grid.
* **Header:** Page Title "Platform Overview", Breadcrumb "Admin > Dashboard", Quick Tenant Search Bar, User Profile Avatar with dropdown.
* **Content Modules:**
  1. **Metric Cards Row (4 columns):** Total Active Tenants (e.g., 1,240), Monthly Recurring Revenue ($MRR), Active Queue Workers (Redis health status), System Error Rate (0.01%).
  2. **Tenant Growth Chart:** Line graph showing weekly tenant signups over the last 6 months.
  3. **Subscription Tier Breakdown:** Donut chart (Pilot vs. Standard vs. Enterprise).
* **States:** Skeleton loaders for metric cards during initial data fetch.

### Page W-PA-03: Tenant Management Console (CRUD & Provisioning)
* **URL:** `/admin/tenants`
* **Layout:** Table datagrid with pagination, filters (Status: Active, Trial, Suspended), and action buttons.
* **Table Columns:** Tenant ID, Family/Workspace Name, Primary Admin Email, Subscription Tier, Created Date, Status Badge, Actions.
* **Actions Toolbar:** "Provision New Tenant" (primary modal trigger), Bulk Suspend, Export CSV.
* **Provision Tenant Modal:** Modal dialog with fields for Workspace Name, Admin Email, Initial Password, and Tier selection dropdown.

### Page W-PA-04: Global Audit & Security Log Explorer
* **URL:** `/admin/audit-logs`
* **Layout:** Full-width log stream view with advanced JSON query filters.
* **Content:** Timestamp, Tenant ID, Actor User ID, Action Type (`tenant.created`, `rls.violation`, `auth.login`), IP Address, Response Status Badge.

---

## SECTION 3: Web Dashboard — Tenant Admin (Client Family) Screens

*Goal:* Client family management, subscription billing, and household oversight via web.

### Page W-TA-01: Tenant Admin Login & Registration
* **URL:** `/login` & `/register`
* **Layout:** Split screen on desktop (Left: Soft branding illustration with family care messaging; Right: Auth form container).
* **Form Elements:** Email, Password, Phone Number (for SMS verification), Terms checkbox.
* **Email/Phone Verification Screen (`/verify`):** 6-digit OTP input boxes with auto-advance, "Resend Code" countdown timer (60s).

### Page W-TA-02: Household Kanban Overview & Today Dashboard
* **URL:** `/app/dashboard`
* **Layout:** Top Header (Tenant Workspace Name + Theme Selector toggle), Sidebar (Dashboard, Requests, Places, Members, Billing), Main Content Area.
* **Content Modules:**
  1. **Overdue Banner:** High-priority alert bar showing tasks past due date with Quick Action "Acknowledge" or "Snooze".
  2. **Kanban Board (4 columns):** *Overdue*, *Due Today*, *Assigned to Me*, *Completed*. Each card shows item name, quantity badge, assignee avatar, due time, and priority color pip.
  3. **Quick Add Trigger:** Floating action bar at bottom right: "+ Add New Request".

### Page W-TA-03: Family Member & Role Management
* **URL:** `/app/members`
* **Layout:** Grid of member profile cards + "Invite Member" primary CTA button.
* **Card Details:** Member Avatar, Name, Email, Role Badge (`Tenant Admin` or `Member`), Device Connection Status (Online/Offline), Actions (Edit Role, Revoke Access).
* **Invite Modal:** Email input, Role selection dropdown, "Send Invite Link" button.

### Page W-TA-04: Saved Places & Geofence Map Console
* **URL:** `/app/places`
* **Layout:** Split view (Left: List of saved places like Home, Supermarket, Pharmacy; Right: Interactive Map container with circular radius overlays).
* **Place Card:** Name, Latitude/Longitude, Radius Slider (150m to 500m), Linked Category tags.

### Page W-TA-05: Billing & Subscription Portal
* **URL:** `/app/billing`
* **Layout:** Stacked card containers.
* **Content:** Current Plan badge ("Pilot Family Tier"), Renewal Date, Payment Method card summary, Invoice History table with download PDF buttons, "Upgrade to Standard SaaS" CTA button.

---

## SECTION 4: Mobile App — Tenant Admin & Family Members Screens

*Goal:* Primary consumer experience optimized for mobile devices (iOS & Android). Soft pastel backgrounds, large touch targets (minimum 48x48dp), and fast one-handed navigation.

### Screen M-01: Splash & Authentication Gateway (`/auth/splash`)
* **Layout:** Full screen gradient canvas using Primary brand color fading into Canvas background. Centered animated family icon, App Title "Family Essentials", and Tagline "Never forget a household need again."
* **Buttons:** "Sign In" (Primary filled button) and "Create Family Workspace" (Outlined button).

### Screen M-02: Registration & Phone/Email Verification (`/auth/register`, `/auth/verify`)
* **Layout:** Vertical scrollable form with safe area padding.
* **Fields:** Full Name, Email, Phone Number, Password.
* **Verification View:** Clean numeric keypad entry for 6-digit SMS/Email verification code.

### Screen M-03: Home / Today Screen (`/app/home`)
* **Layout:** 
  * *Top App Bar:* Greeting ("Good evening, Tanvir"), Family Workspace Name dropdown, Notification Bell icon with badge.
  * *Scrollable Body:* 
    1. Overdue Summary Banner (collapsible warning card).
    2. "Due Today" Section header with item count.
    3. Vertical list of Task Cards (Checkbox, Item Name & Quantity, Assignee Avatar, Due Time badge, Priority tag).
  * *Bottom Navigation Bar:* Home (Active), Shopping Mode, Saved Places, Settings.
  * *Floating Action Button (FAB):* Centered prominent "+" button for Quick Add.

### Screen M-04: Quick Add Modal (`/app/quick-add`)
* **Layout:** Bottom sheet modal with rounded top corners (`radius 24px`), sliding up from bottom.
* **Form Elements:**
  1. Natural Language / Text Input: "What does the family need? (e.g. Milk 2 liters)"
  2. Quantity & Unit Stepper (`-` `2` `+` `liters`)
  3. Assignee Selector (Horizontal scrollable avatar chips of family members)
  4. Priority Selector Chips (`Low`, `Normal`, `High`, `Urgent`)
  5. Due Time Picker (Quick selectors: "Today 8 PM", "Tomorrow morning", "Custom time")
* **Action Footer:** "Save & Notify Assignee" primary button.

### Screen M-05: Request Details & Status Action Screen (`/app/requests/{id}`)
* **Layout:** Detailed scrollable view with sticky action footer.
* **Content:** Item metadata, Requester info, Assignee status badge, Activity history timeline (Added -> Assigned -> Reminded -> Completed), Location geofence link.
* **Interactive Actions:** 
  * If user is Assignee: "Accept Task", "Handover to Another Member", "Defer", "Mark as Done".
  * If user is Requester: "Cancel Request", "Edit Details".

### Screen M-06: Shopping Mode Screen (`/app/shopping`)
* **Layout:** Optimized for fast, one-handed grocery store use.
* **Content:** Items automatically grouped by store/category (e.g., *Supermarket*, *Pharmacy*). Large high-contrast checkboxes, strike-through styling on completion, and prominent "Mark Store Done" button.

### Screen M-07: Saved Places & Geofence Settings (`/app/settings/places`)
* **Layout:** List of saved locations with toggle switches for geofence alerts. Add button to drop a pin on the map.

### Screen M-08: Reminder & Voice Call Settings (`/app/settings/reminders`)
* **Layout:** Toggle switches for Time-based reminders, Push notifications, Quiet Hours start/end time pickers, and **Opt-In Toggle for Automated Voice-Call Escalation** (with explanatory helper text about limits and quiet hours).

---

## SECTION 5: Content, Microcopy & Edge Case Library

### Microcopy Guidelines
* **Primary CTAs:** Action-oriented and clear ("Save & Notify", "Mark as Done", "Send Invite", "Accept Task").
* **Success Messages:** Friendly and affirmative ("Request added! Your family has been notified.", "Task completed successfully.").
* **Validation Errors:** Helpful and non-blaming ("Please enter a valid email address like name@example.com", "Password must be at least 8 characters.").

### Edge Cases & Boundary States
* **Zero-State (No Tasks):** Display a warm illustration of a house with the prompt: *"All caught up! No pending requests for your family right now. Tap '+' to add one."*
* **Offline / Network Disconnected:** Persistent top banner warning: *"You are offline. Changes will sync when connection is restored."* Local cache serves existing requests.
* **Location Permission Denied:** Fallback banner explaining why geofencing requires background location permission, with a direct link to device settings.
* **Extreme Text Overflow:** Multi-line ellipsis truncation for item names exceeding 45 characters, with full expansion on card tap.