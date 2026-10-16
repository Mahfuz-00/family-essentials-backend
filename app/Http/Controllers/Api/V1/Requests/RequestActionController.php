<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\AssignRequestRequest;
use App\Http\Requests\Requests\DeclineRequestRequest;
use App\Http\Requests\Requests\SnoozeRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Requests\RequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RequestActionController extends Controller
{
    public function assign(
        AssignRequestRequest $request,
        ServiceRequest $serviceRequest,
        RequestWorkflow $workflow,
    ): JsonResponse {
        $this->authorize('assign', $serviceRequest);

        $assignee = User::query()->findOrFail($request->integer('assignee_id'));

        return $this->respond($workflow->assign($serviceRequest, $assignee, $request->user()));
    }

    public function accept(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->accept($serviceRequest, $request->user()));
    }

    public function decline(
        DeclineRequestRequest $request,
        ServiceRequest $serviceRequest,
        RequestWorkflow $workflow,
    ): JsonResponse {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->decline($serviceRequest, $request->user(), $request->input('note')));
    }

    public function start(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->start($serviceRequest, $request->user()));
    }

    public function complete(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->complete($serviceRequest, $request->user()));
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('cancel', $serviceRequest);

        return $this->respond($workflow->cancel($serviceRequest, $request->user()));
    }

    public function unavailable(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->markUnavailable($serviceRequest, $request->user(), $request->input('note')));
    }

    public function snooze(
        SnoozeRequestRequest $request,
        ServiceRequest $serviceRequest,
        RequestWorkflow $workflow,
    ): JsonResponse {
        $this->authorize('respond', $serviceRequest);

        return $this->respond($workflow->snooze($serviceRequest, Carbon::parse($request->input('until'))));
    }

    private function respond(ServiceRequest $model): JsonResponse
    {
        return (new ServiceRequestResource(
            $model->load([
                'item', 'requester', 'assignee', 'assignments.assignedTo', 'assignments.assignedBy',
            ]),
        ))->response();
    }
}
