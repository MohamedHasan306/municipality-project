<?php

namespace App\Services\ServiceRequests;

use App\Jobs\SendStatusPushNotification;
use App\Models\EmployeeProfile;
use App\Models\ServiceRequest;
use App\Models\ServiceStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ServiceRequestWorkflowService
{
    public function paginateForTechnicalOffice(User $actor, ?string $status, int $perPage): LengthAwarePaginator
    {
        $allowedStatuses = [
            ServiceStatus::SUBMITTED,
            ServiceStatus::UNDER_REVIEW,
        ];

        $selectedStatuses = $status === null ? $allowedStatuses : [$status];

        if (array_diff($selectedStatuses, $allowedStatuses) !== []) {
            throw ValidationException::withMessages([
                'status' => ['The requested status filter is not available to the technical office.'],
            ]);
        }

        return $this->paginateForEmployee(
            $actor,
            'technical_office',
            'review service requests',
            $selectedStatuses,
            $perPage
        );
    }

    public function paginateForEngineeringOffice(User $actor, int $perPage): LengthAwarePaginator
    {
        return $this->paginateForEmployee(
            $actor,
            'engineering_office',
            'engineering approve service requests',
            [ServiceStatus::PENDING_ENGINEERING_APPROVAL],
            $perPage
        );
    }

    public function paginateForMayor(User $actor, int $perPage): LengthAwarePaginator
    {
        return $this->paginateForEmployee(
            $actor,
            'mayor',
            'mayor approve service requests',
            [ServiceStatus::PENDING_MAYOR_APPROVAL],
            $perPage
        );
    }

    public function showForEmployee(User $actor, ServiceRequest $serviceRequest, string $requiredRole): ServiceRequest
    {
        $this->assertEmployeeCanView($actor, $serviceRequest, $requiredRole);

        return $serviceRequest->load($this->employeeRelations());
    }

    public function startReview(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition($actor, $serviceRequest, ServiceStatus::UNDER_REVIEW);
    }

    public function forwardToEngineering(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition(
            $actor,
            $serviceRequest,
            ServiceStatus::PENDING_ENGINEERING_APPROVAL
        );
    }

    public function rejectByTechnicalOffice(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition($actor, $serviceRequest, ServiceStatus::REJECTED);
    }

    public function forwardToMayor(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition($actor, $serviceRequest, ServiceStatus::PENDING_MAYOR_APPROVAL);
    }

    public function rejectByEngineeringOffice(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition($actor, $serviceRequest, ServiceStatus::REJECTED);
    }

    public function rejectByMayor(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->performTransition($actor, $serviceRequest, ServiceStatus::REJECTED);
    }

    public function recordInitialDraftLocked(ServiceRequest $serviceRequest, User $actor): void
    {
        $serviceRequest->loadMissing('currentStatus');

        if ($serviceRequest->currentStatus?->code !== ServiceStatus::DRAFT) {
            throw new LogicException('A new service request must start in draft status.');
        }

        if ($serviceRequest->statusHistories()->exists()) {
            throw new ConflictHttpException('The initial service request status has already been recorded.');
        }

        $serviceRequest->statusHistories()->create([
            'from_status_id' => null,
            'to_status_id' => $serviceRequest->current_status_id,
            'changed_by' => $actor->id,
        ]);
    }

    public function transitionLocked(ServiceRequest $lockedRequest, string $toStatusCode, User $actor): ServiceRequest
    {
        $lockedRequest->loadMissing([
            'currentStatus',
            'citizenProfile',
            'serviceTypeVersion.serviceType',
        ]);

        $fromStatusCode = $lockedRequest->currentStatus?->code;

        if ($fromStatusCode === null) {
            throw new LogicException('The service request current status is missing.');
        }

        $transitionKey = "{$fromStatusCode}:{$toStatusCode}";
        $transition = $this->allowedTransitions()[$transitionKey] ?? null;

        if ($transition === null) {
            throw new ConflictHttpException(
                "The service request cannot transition from [{$fromStatusCode}] to [{$toStatusCode}]."
            );
        }

        $this->assertActorForTransition(
            $actor,
            $lockedRequest,
            $transition['role'],
            $transition['permission']
        );

        $targetStatus = ServiceStatus::query()
            ->where('code', $toStatusCode)
            ->firstOrFail();

        $fromStatusId = $lockedRequest->current_status_id;
        $lockedRequest->current_status_id = $targetStatus->id;

        if ($toStatusCode === ServiceStatus::SUBMITTED) {
            $lockedRequest->submitted_at = now();
        }

        $lockedRequest->save();

        $lockedRequest->statusHistories()->create([
            'from_status_id' => $fromStatusId,
            'to_status_id' => $targetStatus->id,
            'changed_by' => $actor->id,
        ]);

        SendStatusPushNotification::dispatch(
            userId: (int) $lockedRequest->citizenProfile->user_id,
            type: 'service_request',
            entityId: (int) $lockedRequest->id,
            status: $targetStatus->code,
            title: 'تحديث حالة طلب الخدمة',
            body: "أصبحت حالة طلب {$lockedRequest->id}: {$targetStatus->name_ar}",
        )->afterCommit();

        return $lockedRequest->fresh($this->employeeRelations());
    }

    public function assertEmployeeCanAccess(
        User $actor,
        ServiceRequest $serviceRequest,
        string $requiredRole,
        ?string $requiredPermission = null
    ): EmployeeProfile {
        abort_unless($actor->hasRole($requiredRole), 403, 'You do not have the required role.');

        if ($requiredPermission !== null) {
            abort_unless($actor->can($requiredPermission), 403, 'You do not have the required permission.');
        }

        $employeeProfile = $actor->employeeProfile;

        abort_if($employeeProfile === null, 403, 'An employee profile is required.');
        abort_unless(
            $employeeProfile->status === 'active',
            403,
            'Only active employees may process service requests.'
        );

        $serviceRequest->loadMissing('serviceTypeVersion.serviceType');

        abort_unless(
            (int) $employeeProfile->municipality_id
                === (int) $serviceRequest->serviceTypeVersion->serviceType->municipality_id,
            403,
            'You cannot access a service request from another municipality.'
        );

        return $employeeProfile;
    }

    public function assertEmployeeCanView(
        User $actor,
        ServiceRequest $serviceRequest,
        string $requiredRole
    ): EmployeeProfile {
        $permission = $this->permissionForRole($requiredRole);
        $employeeProfile = $this->assertEmployeeCanAccess(
            $actor,
            $serviceRequest,
            $requiredRole,
            $permission
        );

        $serviceRequest->loadMissing('currentStatus');
        $allowedStatuses = $this->viewableStatusesForRole($requiredRole);

        abort_unless(
            in_array($serviceRequest->currentStatus?->code, $allowedStatuses, true),
            404,
            'The service request is not available to this office in its current status.'
        );

        return $employeeProfile;
    }

    private function performTransition(
        User $actor,
        ServiceRequest $serviceRequest,
        string $targetStatusCode
    ): ServiceRequest {
        return DB::transaction(function () use ($actor, $serviceRequest, $targetStatusCode): ServiceRequest {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            return $this->transitionLocked($lockedRequest, $targetStatusCode, $actor);
        }, 3);
    }

    private function paginateForEmployee(
        User $actor,
        string $requiredRole,
        string $requiredPermission,
        array $statusCodes,
        int $perPage
    ): LengthAwarePaginator {
        abort_unless($actor->hasRole($requiredRole), 403, 'You do not have the required role.');
        abort_unless($actor->can($requiredPermission), 403, 'You do not have the required permission.');

        $employeeProfile = $actor->employeeProfile;
        abort_if($employeeProfile === null, 403, 'An employee profile is required.');
        abort_unless(
            $employeeProfile->status === 'active',
            403,
            'Only active employees may access service requests.'
        );

        return ServiceRequest::query()
            ->with($this->employeeRelations())
            ->whereHas(
                'serviceTypeVersion.serviceType',
                fn ($query) => $query->where('municipality_id', $employeeProfile->municipality_id)
            )
            ->whereHas(
                'currentStatus',
                fn ($query) => $query->whereIn('code', $statusCodes)
            )
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    private function assertActorForTransition(
        User $actor,
        ServiceRequest $serviceRequest,
        string $requiredRole,
        string $requiredPermission
    ): void {
        if ($requiredRole === 'citizen') {
            abort_unless(
                $actor->hasRole('citizen') && $actor->can($requiredPermission),
                403,
                'Only an authorized citizen can submit this service request.'
            );
            abort_unless(
                (int) $actor->citizenProfile?->id === (int) $serviceRequest->citizen_profile_id,
                403,
                'You do not own this service request.'
            );

            return;
        }

        $this->assertEmployeeCanAccess(
            $actor,
            $serviceRequest,
            $requiredRole,
            $requiredPermission
        );
    }

    private function allowedTransitions(): array
    {
        return [
            ServiceStatus::DRAFT.':'.ServiceStatus::SUBMITTED => [
                'role' => 'citizen',
                'permission' => 'submit service request',
            ],
            ServiceStatus::SUBMITTED.':'.ServiceStatus::UNDER_REVIEW => [
                'role' => 'technical_office',
                'permission' => 'review service requests',
            ],
            ServiceStatus::UNDER_REVIEW.':'.ServiceStatus::PENDING_ENGINEERING_APPROVAL => [
                'role' => 'technical_office',
                'permission' => 'review service requests',
            ],
            ServiceStatus::SUBMITTED.':'.ServiceStatus::REJECTED => [
                'role' => 'technical_office',
                'permission' => 'review service requests',
            ],
            ServiceStatus::UNDER_REVIEW.':'.ServiceStatus::REJECTED => [
                'role' => 'technical_office',
                'permission' => 'review service requests',
            ],
            ServiceStatus::PENDING_ENGINEERING_APPROVAL.':'.ServiceStatus::PENDING_MAYOR_APPROVAL => [
                'role' => 'engineering_office',
                'permission' => 'engineering approve service requests',
            ],
            ServiceStatus::PENDING_ENGINEERING_APPROVAL.':'.ServiceStatus::REJECTED => [
                'role' => 'engineering_office',
                'permission' => 'engineering approve service requests',
            ],
            ServiceStatus::PENDING_MAYOR_APPROVAL.':'.ServiceStatus::APPROVED_AND_DOCUMENT_ISSUED => [
                'role' => 'mayor',
                'permission' => 'mayor approve service requests',
            ],
            ServiceStatus::PENDING_MAYOR_APPROVAL.':'.ServiceStatus::REJECTED => [
                'role' => 'mayor',
                'permission' => 'mayor approve service requests',
            ],
        ];
    }

    private function permissionForRole(string $role): string
    {
        return match ($role) {
            'technical_office' => 'review service requests',
            'engineering_office' => 'engineering approve service requests',
            'mayor' => 'mayor approve service requests',
            default => throw new LogicException("Unsupported service request role [{$role}]."),
        };
    }

    private function viewableStatusesForRole(string $role): array
    {
        return match ($role) {
            'technical_office' => [ServiceStatus::SUBMITTED, ServiceStatus::UNDER_REVIEW],
            'engineering_office' => [ServiceStatus::PENDING_ENGINEERING_APPROVAL],
            'mayor' => [ServiceStatus::PENDING_MAYOR_APPROVAL],
            default => throw new LogicException("Unsupported service request role [{$role}]."),
        };
    }

    private function employeeRelations(): array
    {
        return [
            'citizenProfile.user',
            'citizenProfile.municipality',
            'serviceTypeVersion.serviceType.municipality',
            'serviceTypeVersion.fields',
            'currentStatus',
            'attachments',
            'generatedDocument.signature',
        ];
    }
}
