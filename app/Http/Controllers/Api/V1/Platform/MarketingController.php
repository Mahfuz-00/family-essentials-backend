<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\MarketingAnnouncement;
use App\Models\MarketingLead;
use App\Models\MarketingPromotion;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function overview(): JsonResponse
    {
        $totalLeads = MarketingLead::count();
        $newLeads = MarketingLead::where('status', 'new')->count();
        $convertedLeads = MarketingLead::where('status', 'converted')->count();

        $activePromotions = MarketingPromotion::where('is_active', true)->count();
        $totalRedemptions = (int) MarketingPromotion::sum('times_redeemed');

        $activeAnnouncements = MarketingAnnouncement::where('is_active', true)->count();
        $totalImpressions = (int) MarketingAnnouncement::sum('impressions_count');
        $totalClicks = (int) MarketingAnnouncement::sum('clicks_count');

        $ctrPercent = $totalImpressions > 0
            ? round(($totalClicks / $totalImpressions) * 100, 1)
            : 0.0;

        $leadConversionRate = $totalLeads > 0
            ? round(($convertedLeads / $totalLeads) * 100, 1)
            : 0.0;

        return response()->json([
            'data' => [
                'total_leads' => $totalLeads,
                'new_leads' => $newLeads,
                'converted_leads' => $convertedLeads,
                'lead_conversion_rate' => $leadConversionRate,
                'active_promotions' => $activePromotions,
                'total_redemptions' => $totalRedemptions,
                'active_announcements' => $activeAnnouncements,
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'banner_ctr_percent' => $ctrPercent,
            ],
        ]);
    }

    public function promotions(Request $request): JsonResponse
    {
        $query = MarketingPromotion::query()
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->string('status') === 'active') {
                    $q->where('is_active', true);
                } elseif ($request->string('status') === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($sub) => $sub->where('code', 'ilike', $term)->orWhere('description', 'ilike', $term));
            })
            ->latest('id');

        $promotions = $query->paginate(min(50, max(1, $request->integer('per_page', 20))));

        return response()->json($promotions);
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:marketing_promotions,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', 'in:percent,fixed_amount'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'duration' => ['nullable', 'string', 'in:once,repeating,forever'],
            'duration_in_months' => ['nullable', 'integer', 'min:1'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'applicable_tiers' => ['nullable', 'array'],
            'applicable_tiers.*' => ['string'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $promotion = MarketingPromotion::create([
            'code' => strtoupper(trim($validated['code'])),
            'description' => $validated['description'] ?? null,
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'duration' => $validated['duration'] ?? 'once',
            'duration_in_months' => $validated['duration_in_months'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'times_redeemed' => 0,
            'applicable_tiers' => $validated['applicable_tiers'] ?? null,
            'starts_at' => $validated['starts_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->audit->record('marketing.promotion_created', [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'discount_type' => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
        ], 'info');

        return response()->json([
            'message' => 'Promotion code created successfully.',
            'data' => $promotion,
        ], 201);
    }

    public function togglePromotion(MarketingPromotion $promotion): JsonResponse
    {
        $promotion->is_active = ! $promotion->is_active;
        $promotion->save();

        $this->audit->record('marketing.promotion_toggled', [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'is_active' => $promotion->is_active,
        ], 'info');

        return response()->json([
            'message' => $promotion->is_active ? "Promotion {$promotion->code} activated." : "Promotion {$promotion->code} paused.",
            'data' => $promotion,
        ]);
    }

    public function deletePromotion(MarketingPromotion $promotion): JsonResponse
    {
        $code = $promotion->code;
        $promotion->delete();

        $this->audit->record('marketing.promotion_deleted', [
            'code' => $code,
        ], 'warning');

        return response()->json([
            'message' => "Promotion {$code} removed.",
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $announcements = MarketingAnnouncement::query()
            ->latest('id')
            ->get();

        return response()->json(['data' => $announcements]);
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'cta_text' => ['nullable', 'string', 'max:128'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'in:primary,success,warning,info'],
            'target_audience' => ['nullable', 'string', 'in:all,trial_only,free_only,paying_only'],
            'dismissable' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $announcement = MarketingAnnouncement::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'cta_text' => $validated['cta_text'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'tone' => $validated['tone'] ?? 'primary',
            'target_audience' => $validated['target_audience'] ?? 'all',
            'dismissable' => $validated['dismissable'] ?? true,
            'starts_at' => $validated['starts_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->audit->record('marketing.announcement_created', [
            'id' => $announcement->id,
            'title' => $announcement->title,
        ], 'info');

        return response()->json([
            'message' => 'Announcement campaign published.',
            'data' => $announcement,
        ], 201);
    }

    public function toggleAnnouncement(MarketingAnnouncement $announcement): JsonResponse
    {
        $announcement->is_active = ! $announcement->is_active;
        $announcement->save();

        $this->audit->record('marketing.announcement_toggled', [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'is_active' => $announcement->is_active,
        ], 'info');

        return response()->json([
            'message' => $announcement->is_active ? 'Announcement live.' : 'Announcement hidden.',
            'data' => $announcement,
        ]);
    }

    public function deleteAnnouncement(MarketingAnnouncement $announcement): JsonResponse
    {
        $title = $announcement->title;
        $announcement->delete();

        $this->audit->record('marketing.announcement_deleted', [
            'title' => $title,
        ], 'warning');

        return response()->json([
            'message' => 'Announcement removed.',
        ]);
    }

    public function leads(Request $request): JsonResponse
    {
        $query = MarketingLead::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($sub) => $sub->where('email', 'ilike', $term)->orWhere('name', 'ilike', $term));
            })
            ->latest('id');

        $leads = $query->paginate(min(50, max(1, $request->integer('per_page', 25))));

        return response()->json($leads);
    }

    public function updateLead(Request $request, MarketingLead $lead): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,contacted,converted,unsubscribed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead->status = $validated['status'];
        if (array_key_exists('notes', $validated)) {
            $lead->notes = $validated['notes'];
        }
        $lead->save();

        $this->audit->record('marketing.lead_updated', [
            'id' => $lead->id,
            'email' => $lead->email,
            'status' => $lead->status,
        ], 'info');

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data' => $lead,
        ]);
    }

    public function deleteLead(MarketingLead $lead): JsonResponse
    {
        $email = $lead->email;
        $lead->delete();

        $this->audit->record('marketing.lead_deleted', [
            'email' => $email,
        ], 'warning');

        return response()->json([
            'message' => "Lead {$email} removed.",
        ]);
    }

    public function exportLeads(Request $request): StreamedResponse
    {
        $this->audit->record('marketing.leads_exported', [], 'info');

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Email', 'Name', 'Source', 'Campaign', 'Status', 'Notes', 'Created At']);

            MarketingLead::query()->orderBy('id')->chunk(200, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->id,
                        $lead->email,
                        $lead->name,
                        $lead->source,
                        $lead->campaign,
                        $lead->status,
                        $lead->notes,
                        $lead->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 'fairshare_marketing_leads_'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getReferralSettings(): JsonResponse
    {
        $settings = PlatformSetting::get('referral_program', [
            'enabled' => true,
            'reward_type' => 'credit_minor', // 'credit_minor' or 'free_months'
            'reward_value' => 1000, // $10.00 credit or 1 month
            'referrer_bonus_cents' => 1000,
            'referee_discount_percent' => 20,
            'min_subscription_tier' => 'plus',
            'terms_markdown' => "# FairShare Family Referral Program\n\nInvite other households to FairShare. When they upgrade to any paid tier, both of you receive a \$10.00 bill credit.",
        ]);

        return response()->json(['data' => $settings]);
    }

    public function updateReferralSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reward_type' => ['required', 'string', 'in:credit_minor,free_months'],
            'reward_value' => ['required', 'integer', 'min:0'],
            'referrer_bonus_cents' => ['required', 'integer', 'min:0'],
            'referee_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'min_subscription_tier' => ['nullable', 'string'],
            'terms_markdown' => ['nullable', 'string'],
        ]);

        PlatformSetting::set('referral_program', $validated);

        $this->audit->record('marketing.referral_settings_updated', [
            'enabled' => $validated['enabled'],
            'reward_type' => $validated['reward_type'],
        ], 'info');

        return response()->json([
            'message' => 'Referral program configuration updated.',
            'data' => $validated,
        ]);
    }

    /**
     * Default seeded editorial articles focused on improving household functionality.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultBlogPosts(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'how-to-improve-household-functionality',
                'title' => 'How to Improve Household Functionality: The 5-Pillar Domestic Operating System',
                'category' => 'Household Functionality',
                'read_time_minutes' => 8,
                'author_name' => 'Dr. Maya Lin',
                'author_role' => 'Domestic Systems Researcher',
                'icon' => 'cottage',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => '2026-11-10T09:00:00Z',
                'summary' => 'Most household friction is not a relationship problem—it is a systems design problem. Learn how five operational habits replace chaotic group chats and invisible mental load with calm daily execution.',
                'key_takeaways' => [
                    'Replace multi-owner "someone should" requests with strict single named ownership.',
                    'Separate standing recurring cadences (trash night, filters, bills) from ad-hoc errands.',
                    'Trigger shopping lists by physical store arrival (geofencing) rather than arbitrary clock alarms.',
                    'Maintain minimum safety-stock thresholds (par levels) for high-frequency pantry staples.',
                ],
                'sections' => [
                    [
                        'heading' => '1. Diagnose the Hidden Bottleneck: Invisible Mental Load',
                        'body' => "In almost every household, domestic work is split into two unequal halves: visible execution (buying the groceries, fixing the leaking tap) and invisible cognitive orchestration (noticing the olive oil is low, remembering which pharmacy has the prescription, tracking when property taxes are due). When orchestration lives inside one person's head or scrolls off the top of a family WhatsApp thread, the household operates in reactive firefighting mode.",
                    ],
                    [
                        'heading' => '2. Enforce Single Named Ownership on Every Errand',
                        'body' => 'Organizational psychology proves that when a task is assigned to "everyone," nobody owns it. Saying "Can someone grab milk on the way home?" results in either zero cartons of milk or two cartons bought at different stores. High-functioning households assign every request to a single named owner with an explicit status: Open, Accepted, In Progress, or Completed.',
                    ],
                    [
                        'heading' => '3. Anchor Errands to Places, Not Just Times',
                        'body' => 'A 5:30 PM phone reminder to buy batteries fails if you are stuck in traffic or still in a meeting. You swipe the notification away, and by the time you pass the hardware store at 6:15 PM, the reminder is gone. Tying household requests to saved store locations ensures your checklist surfaces the exact moment you pull into the parking lot.',
                    ],
                    [
                        'heading' => '4. Put Standing Chores & Pantry Staples on Autopilot',
                        'body' => 'Never waste willpower remembering repeatable maintenance. Trash collection, HVAC filter replacements, internet bills, and staple pantry restocks follow predictable cycles. By setting standing cadence rules and minimum safety-stock thresholds, your household board automatically generates the errand before you run out.',
                    ],
                    [
                        'heading' => '5. Run a 10-Minute Sunday Logistics Sync',
                        'body' => 'Instead of debating chores at 7:30 PM on a Tuesday when everyone is exhausted, review your household overview once a week. Check upcoming due items, balance the shared family ledger, and assign ownership for the week ahead in ten calm minutes.',
                    ],
                ],
                'action_checklist' => [
                    'Move all active grocery and repair requests out of text threads and onto a shared Kanban board.',
                    'Pin your top 3 weekly stops (supermarket, pharmacy, hardware store) as saved places.',
                    'Set safety-stock thresholds for your 10 most-used kitchen and household staples.',
                    'Configure recurring rules for weekly trash, monthly utilities, and quarterly home maintenance.',
                ],
            ],
            [
                'id' => 2,
                'slug' => 'defeating-domestic-bystander-effect',
                'title' => 'Why "Could Someone Grab Milk?" Fails: Defeating the Domestic Bystander Effect',
                'category' => 'Accountability & Roles',
                'read_time_minutes' => 6,
                'author_name' => 'Marcus Vance',
                'author_role' => 'Family Operations Architect',
                'icon' => 'view_kanban',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => '2026-11-08T10:30:00Z',
                'summary' => 'Ambiguous ownership is the number-one cause of dropped chores and duplicate purchases. Here is how single-assignee workflows bring effortless clarity to busy partners.',
                'key_takeaways' => [
                    'Shared tasks without a single lead owner suffer from a 64% higher drop rate.',
                    'Explicit acceptance handshakes eliminate the need for "Did you see my text?" follow-ups.',
                    'Reassignment should take one tap when schedules shift.',
                ],
                'sections' => [
                    [
                        'heading' => 'The Psychology of Diffusion of Responsibility at Home',
                        'body' => 'When a request is broadcast to a group chat without a named owner, each partner subconsciously assumes the other has more bandwidth to handle it. Worse, the person who originally noticed the need must keep tracking whether it was actually done—doubling their mental fatigue.',
                    ],
                    [
                        'heading' => 'The Three-State Domestic Handshake',
                        'body' => 'Improving household functionality requires closing the feedback loop. When an errand moves from Requested to Accepted to Completed, both partners gain instant visual certainty without sending a single nagging message.',
                    ],
                    [
                        'heading' => 'Age-Appropriate Roles for Teens and Children',
                        'body' => 'Household functionality scales when older kids participate with clear boundaries. Giving teens task-completion access while keeping household banking and tax ledgers scoped to adults builds responsibility without compromising privacy.',
                    ],
                ],
                'action_checklist' => [
                    'Audit your current to-do list and assign exactly one primary owner to every open item.',
                    'Use priority tags (Standard vs. Urgent) sparingly so urgent calls always command attention.',
                    'Invite teens with scoped member roles so they can own their weekly standing chores.',
                ],
            ],
            [
                'id' => 3,
                'slug' => 'location-aware-checklists-vs-time-alarms',
                'title' => 'Context Over Clocks: Why Location-Aware Checklists Beat 5:30 PM Phone Alarms',
                'category' => 'Smart Geofencing',
                'read_time_minutes' => 5,
                'author_name' => 'Elena Rostova',
                'author_role' => 'Product Ergonomics Lead',
                'icon' => 'pin_drop',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => '2026-11-05T14:15:00Z',
                'summary' => 'Time-based reminders interrupt you when you cannot act and stay silent when you are actually standing in the store aisle. Contextual store boundaries solve the timing mismatch.',
                'key_takeaways' => [
                    '88% of second-trip grocery runs happen because items were remembered at the wrong time.',
                    'Grouping errands by store location lets one commuter clear five requests in a single stop.',
                    'Privacy-first foreground checks protect battery life and never record movement history.',
                ],
                'sections' => [
                    [
                        'heading' => 'Why Time Alarms Create Notification Blindness',
                        'body' => 'Modern families suffer from alarm fatigue. When a phone buzzes at 4:00 PM saying "Buy laundry detergent," you are likely at your desk or picking up kids. Once dismissed, that alert will not return when you actually drive past the supermarket.',
                    ],
                    [
                        'heading' => 'Designing Store-Centric Errand Batches',
                        'body' => 'When every household request is tagged to a specific venue—such as the neighborhood pharmacy, organic market, or hardware store—any family member arriving at that location immediately sees the consolidated checklist for the entire home.',
                    ],
                ],
                'action_checklist' => [
                    'Create saved places for your grocery store, pharmacy, and wholesale club.',
                    'Attach store tags whenever you add a new item to the household board.',
                    'Open Shopping Mode upon arrival to check off quantities in real time.',
                ],
            ],
            [
                'id' => 4,
                'slug' => 'par-level-pantry-safety-stock-thresholds',
                'title' => 'The Par-Level Pantry: How Safety-Stock Thresholds Prevent Dinner-Time Emergencies',
                'category' => 'Pantry & Staples',
                'read_time_minutes' => 7,
                'author_name' => 'Chef Julian Brooks',
                'author_role' => 'Culinary & Home Inventory Advisor',
                'icon' => 'kitchen',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => '2026-11-02T11:00:00Z',
                'summary' => 'Professional kitchens never run out of olive oil or flour mid-service because they track par levels. Here is how to apply lightweight safety-stock buffers to your home pantry.',
                'key_takeaways' => [
                    'Never wait until a staple hits zero to add it to the shopping board.',
                    'Define a minimum safety threshold (e.g. 1 backup bottle or 2 packs) for critical essentials.',
                    'Use 1-tap errand generation the moment the backup unit is opened.',
                ],
                'sections' => [
                    [
                        'heading' => 'Zero Is Too Late for Household Essentials',
                        'body' => 'Discovering you are out of baby diapers, dish soap, or coffee beans at 6:30 AM forces an expensive, stressful emergency store run. High-functioning households trigger replenishment when stock hits the safety buffer—not when the shelf is bare.',
                    ],
                    [
                        'heading' => 'The Two-Container Buffer Rule',
                        'body' => 'Keep one active unit in use and one reserve unit in the pantry. The moment you unseal the reserve unit, tap "+ Errand" in your Pantry module so it lands on the next scheduled grocery run with zero urgency stress.',
                    ],
                ],
                'action_checklist' => [
                    'List the 15 items that cause the most disruption when your household runs out.',
                    'Set each item’s safety threshold to 1 reserve unit in the Pantry & Staples tracker.',
                    'Convert low-stock alerts into assigned store errands with a single tap.',
                ],
            ],
            [
                'id' => 5,
                'slug' => 'frictionless-family-finance-shared-ledgers',
                'title' => 'Frictionless Family Finance: Running Shared Household Ledgers Without Surveillance',
                'category' => 'Household Finance',
                'read_time_minutes' => 6,
                'author_name' => 'Tariq Al-Mansoor',
                'author_role' => 'Household Finance Specialist',
                'icon' => 'account_balance_wallet',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => '2026-10-28T08:45:00Z',
                'summary' => 'Couples and co-parents need clear visibility into shared expenses, monthly caps, and savings goals—without handing over private bank passwords or auditing every personal coffee.',
                'key_takeaways' => [
                    'Dedicated shared ledgers separate common household bills from private personal spending.',
                    'Optical receipt scanning (OCR) turns paper grocery receipts into categorized entries in seconds.',
                    'Tracking net worth, assets, liabilities, and upcoming taxes in one dashboard prevents seasonal surprises.',
                ],
                'sections' => [
                    [
                        'heading' => 'Why Bank-Scraping Budget Apps Cause Domestic Burnout',
                        'body' => 'Connecting every checking account and credit card to an automated aggregator often turns partners into auditors. Instead of focusing on shared household goals, couples get bogged down categorizing hundreds of unrelated personal transactions.',
                    ],
                    [
                        'heading' => 'Intentional Shared Ledgers & Receipt OCR',
                        'body' => 'By maintaining purposeful household ledgers—for groceries, home maintenance, children’s activities, and recurring utilities—and scanning store receipts directly into the ledger, families stay under their monthly cap with complete transparency and mutual trust.',
                    ],
                ],
                'action_checklist' => [
                    'Set a realistic Monthly Household Cap aligned with your recurring fixed costs.',
                    'Snap machine-printed grocery receipts with the OCR scanner to log spend and restock pantry items simultaneously.',
                    'Review your Household Overview balance sheet monthly to track emergency fund goals and upcoming tax obligations.',
                ],
            ],
            [
                'id' => 6,
                'slug' => 'repeatable-chores-standing-cadence-playbook',
                'title' => 'Putting Repeatable Chores on Autopilot: The Standing Cadence Playbook',
                'category' => 'Automation & Routines',
                'read_time_minutes' => 5,
                'author_name' => 'Dr. Maya Lin',
                'author_role' => 'Domestic Systems Researcher',
                'icon' => 'event_repeat',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => '2026-10-22T16:00:00Z',
                'summary' => 'Up to 70% of domestic tasks repeat on a weekly, monthly, or seasonal loop. Automating their dispatch frees hours of weekly planning.',
                'key_takeaways' => [
                    'Convert repetitive chores into standing rules that spawn onto the active board automatically.',
                    'Rotate or pre-assign owners so everyone knows their standing weekly responsibilities.',
                    'Pause standing rules in one click when the household travels on vacation.',
                ],
                'sections' => [
                    [
                        'heading' => 'Stop Recreating the Same Errands Every Week',
                        'body' => 'If your family buys fresh produce every Saturday, pays internet on the 18th, and cleans the water filter every month, typing those tasks manually is wasted effort. Standing cadence rules ensure the right task appears on the right morning with the right owner already attached.',
                    ],
                    [
                        'heading' => 'The Seasonal Preventative Maintenance Loop',
                        'body' => 'Home repairs become expensive when preventative care is forgotten. Scheduling quarterly and monthly standing rules protects your home equity while keeping daily mental clutter at zero.',
                    ],
                ],
                'action_checklist' => [
                    'Create weekly standing rules for grocery runs and bin collection nights.',
                    'Add monthly standing rules for utility settlements, rent/mortgage, and appliance care.',
                    'Use the Pause toggle during family holidays so no overdue alerts accumulate while away.',
                ],
            ],
        ];
    }

    /**
     * Retrieve all blog posts from PlatformSetting (seeding defaults on first read).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getStoredBlogPosts(): array
    {
        $posts = PlatformSetting::get('marketing_blog_posts', null);
        if (! is_array($posts) || count($posts) === 0) {
            $posts = self::defaultBlogPosts();
            PlatformSetting::set('marketing_blog_posts', $posts);
        }

        return array_values($posts);
    }

    public function blogPosts(): JsonResponse
    {
        return response()->json([
            'data' => self::getStoredBlogPosts(),
        ]);
    }

    public function storeBlogPost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'read_time_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
            'summary' => ['required', 'string', 'max:1000'],
            'key_takeaways' => ['nullable', 'array'],
            'key_takeaways.*' => ['string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.heading' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string'],
            'action_checklist' => ['nullable', 'array'],
            'action_checklist.*' => ['string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $posts = self::getStoredBlogPosts();
        $nextId = collect($posts)->max('id') + 1;

        $slug = ! empty($validated['slug'])
            ? \Illuminate\Support\Str::slug($validated['slug'])
            : \Illuminate\Support\Str::slug($validated['title']);

        if (collect($posts)->contains('slug', $slug)) {
            $slug .= '-'.$nextId;
        }

        $isFeatured = (bool) ($validated['is_featured'] ?? false);
        if ($isFeatured) {
            foreach ($posts as &$existing) {
                $existing['is_featured'] = false;
            }
            unset($existing);
        }

        $newPost = [
            'id' => $nextId,
            'slug' => $slug,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'read_time_minutes' => (int) ($validated['read_time_minutes'] ?? 5),
            'author_name' => $validated['author_name'],
            'author_role' => $validated['author_role'] ?? 'Household Systems Editorial',
            'icon' => $validated['icon'] ?? 'article',
            'is_featured' => $isFeatured,
            'is_published' => (bool) ($validated['is_published'] ?? true),
            'published_at' => now()->toIso8601String(),
            'summary' => $validated['summary'],
            'key_takeaways' => array_values(array_filter($validated['key_takeaways'] ?? [])),
            'sections' => array_values($validated['sections']),
            'action_checklist' => array_values(array_filter($validated['action_checklist'] ?? [])),
        ];

        array_unshift($posts, $newPost);
        PlatformSetting::set('marketing_blog_posts', $posts);

        $this->audit->record('marketing.blog_post_created', [
            'id' => $newPost['id'],
            'slug' => $newPost['slug'],
            'title' => $newPost['title'],
        ], 'info');

        return response()->json([
            'message' => 'Blog post published to website.',
            'data' => $newPost,
        ], 201);
    }

    public function updateBlogPost(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'read_time_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
            'summary' => ['required', 'string', 'max:1000'],
            'key_takeaways' => ['nullable', 'array'],
            'key_takeaways.*' => ['string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.heading' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string'],
            'action_checklist' => ['nullable', 'array'],
            'action_checklist.*' => ['string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $posts = self::getStoredBlogPosts();
        $foundIndex = null;
        foreach ($posts as $idx => $post) {
            if ((int) ($post['id'] ?? 0) === $id) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return response()->json(['message' => 'Blog post not found.'], 404);
        }

        $isFeatured = (bool) ($validated['is_featured'] ?? $posts[$foundIndex]['is_featured'] ?? false);
        if ($isFeatured) {
            foreach ($posts as &$existing) {
                $existing['is_featured'] = false;
            }
            unset($existing);
        }

        $slug = ! empty($validated['slug'])
            ? \Illuminate\Support\Str::slug($validated['slug'])
            : ($posts[$foundIndex]['slug'] ?? \Illuminate\Support\Str::slug($validated['title']));

        $posts[$foundIndex] = array_merge($posts[$foundIndex], [
            'slug' => $slug,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'read_time_minutes' => (int) ($validated['read_time_minutes'] ?? 5),
            'author_name' => $validated['author_name'],
            'author_role' => $validated['author_role'] ?? 'Household Systems Editorial',
            'icon' => $validated['icon'] ?? 'article',
            'is_featured' => $isFeatured,
            'is_published' => (bool) ($validated['is_published'] ?? true),
            'summary' => $validated['summary'],
            'key_takeaways' => array_values(array_filter($validated['key_takeaways'] ?? [])),
            'sections' => array_values($validated['sections']),
            'action_checklist' => array_values(array_filter($validated['action_checklist'] ?? [])),
        ]);

        PlatformSetting::set('marketing_blog_posts', array_values($posts));

        $this->audit->record('marketing.blog_post_updated', [
            'id' => $id,
            'slug' => $posts[$foundIndex]['slug'],
            'title' => $posts[$foundIndex]['title'],
        ], 'info');

        return response()->json([
            'message' => 'Blog post updated.',
            'data' => $posts[$foundIndex],
        ]);
    }

    public function toggleBlogPost(int $id): JsonResponse
    {
        $posts = self::getStoredBlogPosts();
        foreach ($posts as &$post) {
            if ((int) ($post['id'] ?? 0) === $id) {
                $post['is_published'] = ! ($post['is_published'] ?? true);
                PlatformSetting::set('marketing_blog_posts', array_values($posts));

                $this->audit->record('marketing.blog_post_toggled', [
                    'id' => $id,
                    'slug' => $post['slug'],
                    'is_published' => $post['is_published'],
                ], 'info');

                return response()->json([
                    'message' => $post['is_published'] ? 'Blog post published on website.' : 'Blog post unpublished (draft).',
                    'data' => $post,
                ]);
            }
        }

        return response()->json(['message' => 'Blog post not found.'], 404);
    }

    public function deleteBlogPost(int $id): JsonResponse
    {
        $posts = self::getStoredBlogPosts();
        $filtered = array_values(array_filter($posts, fn ($p) => (int) ($p['id'] ?? 0) !== $id));

        if (count($filtered) === count($posts)) {
            return response()->json(['message' => 'Blog post not found.'], 404);
        }

        PlatformSetting::set('marketing_blog_posts', $filtered);

        $this->audit->record('marketing.blog_post_deleted', [
            'id' => $id,
        ], 'warning');

        return response()->json([
            'message' => 'Blog post removed.',
        ]);
    }
}
