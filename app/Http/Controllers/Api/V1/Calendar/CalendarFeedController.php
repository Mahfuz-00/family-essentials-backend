<?php

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarFeedToken;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Get or create the unique subscription URL for this household or user
     */
    public function getFeedUrl(Request $request): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $tenant = Tenant::findOrFail($tenantId);
        $user = $request->user();

        $tokenRecord = CalendarFeedToken::forTenant($tenant, $user);
        $feedUrl = url("/api/v1/calendar/feed/{$tokenRecord->token}.ics");
        $webcalUrl = str_replace(['http://', 'https://'], 'webcal://', $feedUrl);

        return response()->json([
            'data' => [
                'token' => $tokenRecord->token,
                'feed_url' => $feedUrl,
                'webcal_url' => $webcalUrl,
                'scope' => $tokenRecord->scope,
            ],
        ]);
    }

    /**
     * Public RFC 5545 iCalendar stream consumed by Google Calendar, Apple Calendar, and Outlook
     */
    public function feed(string $token): Response
    {
        $token = str_replace('.ics', '', $token);
        $tokenRecord = CalendarFeedToken::with(['tenant', 'user'])->where('token', $token)->first();

        if (! $tokenRecord) {
            abort(404, 'Calendar feed not found or expired.');
        }

        $tokenRecord->last_accessed_at = now();
        $tokenRecord->save();

        $tenant = $tokenRecord->tenant;

        // Fetch open errands with due dates
        $requests = ServiceRequest::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->with(['place', 'assignee'])
            ->orderBy('due_at')
            ->take(200)
            ->get();

        $calendarName = "FairShare: {$tenant->name}";
        $now = gmdate('Ymd\THis\Z');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//FairShare//Family Essentials Calendar//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:{$calendarName}\r\n";
        $ics .= "X-WR-TIMEZONE:UTC\r\n";

        foreach ($requests as $req) {
            $dueUtc = gmdate('Ymd\THis\Z', strtotime($req->due_at));
            $endUtc = gmdate('Ymd\THis\Z', strtotime($req->due_at) + 1800); // 30-min block
            $assigneeName = $req->assignee?->name ?? 'Unassigned';
            $location = $req->place?->name ?? $req->store_name ?? 'Household';
            $summary = "[{$req->priority}] {$req->title} ({$assigneeName})";
            $desc = "Errand: {$req->title}\\nAssignee: {$assigneeName}\\nStatus: {$req->status}\\nPriority: {$req->priority}";
            if ($req->notes) {
                $desc .= "\\nNotes: {$req->notes}";
            }

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:fairshare-req-{$req->id}@familyessentials.app\r\n";
            $ics .= "DTSTAMP:{$now}\r\n";
            $ics .= "DTSTART:{$dueUtc}\r\n";
            $ics .= "DTEND:{$endUtc}\r\n";
            $ics .= "SUMMARY:{$summary}\r\n";
            $ics .= "DESCRIPTION:{$desc}\r\n";
            $ics .= "LOCATION:{$location}\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="fairshare_family_schedule.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
