<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\StoreRequestRequest;
use App\Http\Requests\Requests\UpdateRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Item;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Plans\QuotaService;
use App\Requests\RequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceRequest::query()->with(['item', 'place', 'requester', 'assignee']);

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')));
        $query->when($request->filled('place_id'), fn ($q) => $q->where('place_id', (int) $request->input('place_id')));
        $query->when($request->filled('store'), fn ($q) => $q->where('store_name', 'like', '%'.$request->string('store').'%'));

        $assignee = $request->input('assignee');

        if ($assignee === 'me') {
            $query->where('assignee_id', $request->user()->getKey());
        } elseif ($assignee === 'unassigned') {
            $query->whereNull('assignee_id');
        } elseif ($assignee !== null && $assignee !== '') {
            $query->where('assignee_id', (int) $assignee);
        }

        if ($request->input('due') === 'overdue') {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereIn('status', ['pending', 'accepted', 'in_progress']);
        } elseif ($request->input('due') === 'today') {
            $query->whereDate('due_at', now()->toDateString());
        }

        return ServiceRequestResource::collection($query->latest('id')->paginate(50))->response();
    }

    public function store(StoreRequestRequest $request, RequestWorkflow $workflow): JsonResponse
    {
        $attributes = $request->validated();

        // Handing work to somebody else is a dispatch, and juniors may not do it —
        // the policy is enforced on reassignment, so it must be enforced here too.
        if (! empty($attributes['assignee_id'])) {
            $this->authorize('dispatch', ServiceRequest::class);
        }

        // The plan's monthly request allowance.
        app(QuotaService::class)->assertCanCreateRequest();

        if (! empty($attributes['item_id'])) {
            $item = Item::query()->findOrFail($attributes['item_id']);
            $attributes['title'] = ($attributes['title'] ?? null) ?: $item->name;
            $attributes['category'] = $attributes['category'] ?? $item->category;
            $attributes['store_name'] = $attributes['store_name'] ?? $item->default_store;
        } else {
            // Free-text quick add: link to an existing catalog item or grow the catalog.
            $attributes['item_id'] = $this->resolveCatalogItem(
                (string) $attributes['title'],
                $attributes['category'] ?? null,
                $request,
            );
        }

        if (! empty($attributes['place_id'])) {
            $place = Place::query()->findOrFail($attributes['place_id']);
            $attributes['store_name'] = $attributes['store_name'] ?? $place->name;
        }

        $model = $workflow->create($request->user(), $attributes);

        return (new ServiceRequestResource($model->load(['item', 'place', 'requester', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        return (new ServiceRequestResource(
            $serviceRequest->load([
                'item', 'place', 'requester', 'assignee', 'assignments.assignedTo', 'assignments.assignedBy',
                'reminders', 'voiceCallLogs',
            ]),
        ))->response();
    }

    public function update(UpdateRequestRequest $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('update', $serviceRequest);

        $model = $workflow->update($serviceRequest, $request->validated());

        return (new ServiceRequestResource($model->load(['item', 'place', 'requester', 'assignee'])))->response();
    }

    private function resolveCatalogItem(string $title, ?string $category, Request $request): int
    {
        $existing = Item::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($title)])
            ->first();

        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        return (int) Item::create([
            'name' => $title,
            'category' => $category,
            'created_by' => $request->user()->getKey(),
        ])->getKey();
    }
}
