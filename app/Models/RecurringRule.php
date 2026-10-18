<?php

namespace App\Models;

use App\Enums\Cadence;
use App\Enums\GeofenceEventType;
use App\Enums\RequestPriority;
use App\Models\Concerns\BelongsToTenant;
use App\Recurring\RecurringSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A repeating chore, expressed as a recipe for a *new* request each cycle.
 *
 * The rule is never the task: it spawns a fresh pending request and moves its
 * own `next_run_on` forward. Reopening an old request would rewrite history and
 * the audit ledger, so it never happens.
 */
#[Fillable([
    'title', 'quantity', 'notes', 'category', 'priority',
    'cadence', 'weekday', 'day_of_month',
    'assignee_id', 'place_id', 'location_trigger_place_id', 'location_trigger_direction',
    'amount_minor', 'ledger_id', 'active', 'created_by',
])]
class RecurringRule extends Model
{
    use BelongsToTenant;

    protected $table = 'recurring_rules';

    /**
     * Mirrors the table defaults, so a freshly created model behaves the same
     * before and after a round trip (a rule created without a priority must not
     * read as "no priority" to the resource).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 'normal',
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => RequestPriority::class,
            'cadence' => Cadence::class,
            'location_trigger_direction' => GeofenceEventType::class,
            'weekday' => 'integer',
            'day_of_month' => 'integer',
            'amount_minor' => 'integer',
            'next_run_on' => 'date',
            'last_run_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function triggerPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'location_trigger_place_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'recurring_rule_id');
    }

    /**
     * Due when it is switched on and its next cycle has arrived (or was missed).
     */
    public function isDue(?Carbon $on = null): bool
    {
        if (! $this->active) {
            return false;
        }

        return $this->next_run_on !== null
            && $this->next_run_on->lessThanOrEqualTo(($on ?? Carbon::today())->copy()->startOfDay());
    }

    /**
     * Move the clock to the next occurrence strictly after $from.
     *
     * Strictly-after is what skips missed cycles instead of back-filling them: a
     * rule whose next run was ten days ago spawns once and jumps forward, rather
     * than replaying ten days of chores.
     */
    public function scheduleNextRun(?Carbon $from = null): void
    {
        $this->next_run_on = RecurringSchedule::next(
            $this->cadence,
            $this->weekday,
            $this->day_of_month,
            $from,
        );
    }

    /**
     * A weekly rule keeps a weekday; a monthly one keeps a day; neither keeps
     * the other's. Normalised here so a cadence change cannot leave a stale
     * `weekday` behind that a later cadence switch would silently revive.
     */
    public function normaliseCadenceFields(): void
    {
        if ($this->cadence !== Cadence::Weekly) {
            $this->weekday = null;
        }

        if ($this->cadence !== Cadence::Monthly) {
            $this->day_of_month = null;
        }
    }
}
