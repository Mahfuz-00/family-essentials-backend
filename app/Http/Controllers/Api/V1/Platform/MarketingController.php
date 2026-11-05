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
}
