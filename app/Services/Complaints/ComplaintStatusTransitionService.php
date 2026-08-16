<?php

namespace App\Services\Complaints;

use App\Jobs\SendStatusPushNotification;
use App\Models\Complaint;
use App\Models\ComplaintReport;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

class ComplaintStatusTransitionService
{
    private const ALLOWED_TRANSITIONS = [
        ComplaintStatus::DRAFT => [
            ComplaintStatus::SUBMITTED,
        ],

        ComplaintStatus::SUBMITTED => [
            ComplaintStatus::UNDER_REVIEW,
            ComplaintStatus::REJECTED,
        ],

        ComplaintStatus::UNDER_REVIEW => [
            ComplaintStatus::FORWARDED_TO_DEPARTMENT,
            ComplaintStatus::REJECTED,
        ],

        ComplaintStatus::FORWARDED_TO_DEPARTMENT => [
            ComplaintStatus::IN_PROGRESS,
            ComplaintStatus::REJECTED,
        ],

        ComplaintStatus::IN_PROGRESS => [
            ComplaintStatus::RESOLVED,
            ComplaintStatus::REJECTED,
        ],
    ];

    /*
     * This method records the initial status history of a newly created report.
     *
     * The report may already contain current_status_id because that database
     * column is expected to be non-null after the data migration.
     */
    public function recordInitialStatusLocked(
        ComplaintReport $report,
        string $statusKey,
        ?User $actor = null,
        ?string $note = null,
        bool $isPublic = true
    ): ComplaintReport {
        $initialStatus = $this->getStatus($statusKey);

        if ($report->current_status_id === null) {
            $report->update([
                'current_status_id' => $initialStatus->id,
            ]);
        }

        if ((int) $report->current_status_id !== (int) $initialStatus->id) {
            throw ValidationException::withMessages([
                'status' => [
                    'The report current status does not match the requested initial status.',
                ],
            ]);
        }

        if ($report->statusHistories()->exists()) {
            throw ValidationException::withMessages([
                'status' => [
                    'The initial status history has already been recorded for this report.',
                ],
            ]);
        }

        $report->statusHistories()->create([
            'from_status_id' => null,
            'to_status_id' => $initialStatus->id,
            'changed_by' => $actor?->id,
            'note' => $note,
            'is_public' => $isPublic,
        ]);

        return $report->refresh()->load('currentStatus:id,key,name,is_terminal');
    }

    /*
     * Changes the status of an unlinked complaint report.
     *
     * This is used for:
     * - draft -> submitted
     * - submitted -> rejected
     *
     * A linked report must be changed through transitionUnifiedComplaintLocked()
     * so that every report under the unified complaint stays synchronized.
     */
    public function transitionReportLocked(
        ComplaintReport $report,
        string $targetStatusKey,
        User $actor,
        ?string $note = null,
        bool $isPublic = true
    ): ComplaintReport {
        if ($report->complaint_id !== null) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'A linked report must be transitioned through its unified complaint.',
                ],
            ]);
        }

        $currentStatus = $this->statusById((int) $report->current_status_id);
        $targetStatus = $this->getStatus($targetStatusKey);

        $this->assertTransitionAllowed($currentStatus->key, $targetStatus->key);

        return $this->applyReportTransitionLocked(
            $report,
            $currentStatus,
            $targetStatus,
            $actor,
            $note,
            $isPublic
        );
    }

    /*
     * Changes the aggregate complaint status and all linked report statuses.
     *
     * The caller must execute this method inside a database transaction and
     * must lock the unified complaint before calling it.
     */
    public function transitionUnifiedComplaintLocked(
        Complaint $complaint,
        string $targetStatusKey,
        User $actor,
        ?string $note = null,
        bool $isPublic = true
    ): Complaint {
        $currentComplaintStatus = $this->statusById((int) $complaint->current_status_id);
        $targetStatus = $this->getStatus($targetStatusKey);

        $this->assertTransitionAllowed($currentComplaintStatus->key, $targetStatus->key);

        $reports = $complaint->reports()
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        if ($reports->isEmpty()) {
            throw ValidationException::withMessages([
                'complaint' => [
                    'The unified complaint does not contain any complaint reports.',
                ],
            ]);
        }

        $reportStatuses = $this->loadStatusesForReports($reports);

        foreach ($reports as $report) {
            $reportStatus = $reportStatuses->get((int) $report->current_status_id);

            if ($reportStatus === null) {
                throw new LogicException("Complaint report status [{$report->current_status_id}] does not exist.");
            }

            if ($reportStatus->key !== $currentComplaintStatus->key) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Complaint report [{$report->id}] is not synchronized with the unified complaint status.",
                    ],
                ]);
            }

            $this->assertTransitionAllowed($reportStatus->key, $targetStatus->key);
        }

        $complaint->update([
            'current_status_id' => $targetStatus->id,
        ]);

        foreach ($reports as $report) {
            $reportStatus = $reportStatuses->get((int) $report->current_status_id);

            $this->applyReportTransitionLocked(
                $report,
                $reportStatus,
                $targetStatus,
                $actor,
                $note,
                $isPublic
            );
        }

        return $complaint->refresh()->load([
            'currentStatus:id,key,name,is_terminal',
            'reports.currentStatus:id,key,name,is_terminal',
        ]);
    }

    /*
     * Synchronizes a newly linked report with the current status of its
     * unified complaint.
     *
     * Example:
     *
     * Report: submitted
     * Complaint: in_progress
     *
     * The report will pass through:
     *
     * submitted
     * -> under_review
     * -> forwarded_to_department
     * -> in_progress
     *
     * A history record is created for every transition.
     */
    public function synchronizeReportToUnifiedComplaintLocked(
        ComplaintReport $report,
        Complaint $complaint,
        User $actor,
        ?string $finalNote = null,
        bool $isPublic = true
    ): ComplaintReport {
        if ((int) $report->complaint_id !== (int) $complaint->id) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'The report is not linked to the specified unified complaint.',
                ],
            ]);
        }

        $currentReportStatus = $this->statusById((int) $report->current_status_id);
        $targetComplaintStatus = $this->statusById((int) $complaint->current_status_id);

        if ($currentReportStatus->key === $targetComplaintStatus->key) {
            return $report->refresh()->load('currentStatus:id,key,name,is_terminal');
        }

        if ($targetComplaintStatus->is_terminal) {
            throw ValidationException::withMessages([
                'complaint' => [
                    'A report cannot be synchronized with a terminal complaint.',
                ],
            ]);
        }

        $transitionPath = $this->findTransitionPath(
            $currentReportStatus->key,
            $targetComplaintStatus->key
        );

        $lastIndex = count($transitionPath) - 1;

        foreach ($transitionPath as $index => $nextStatusKey) {
            $nextStatus = $this->getStatus($nextStatusKey);
            $note = $index === $lastIndex ? $finalNote : null;

            $report = $this->applyReportTransitionLocked(
                $report,
                $currentReportStatus,
                $nextStatus,
                $actor,
                $note,
                $isPublic
            );

            $currentReportStatus = $nextStatus;
        }

        return $report->refresh()->load('currentStatus:id,key,name,is_terminal');
    }

    public function getStatus(string $statusKey): ComplaintStatus
    {
        $status = ComplaintStatus::query()
            ->where('key', $statusKey)
            ->first();

        if ($status === null) {
            throw new LogicException("Complaint status [{$statusKey}] does not exist.");
        }

        return $status;
    }

    public function canTransition(string $fromStatusKey, string $toStatusKey): bool
    {
        $allowedTargets = self::ALLOWED_TRANSITIONS[$fromStatusKey] ?? [];

        return in_array($toStatusKey, $allowedTargets, true);
    }

    private function applyReportTransitionLocked(
        ComplaintReport $report,
        ComplaintStatus $currentStatus,
        ComplaintStatus $targetStatus,
        User $actor,
        ?string $note,
        bool $isPublic
    ): ComplaintReport {
        $this->assertTransitionAllowed($currentStatus->key, $targetStatus->key);

        $report->update([
            'current_status_id' => $targetStatus->id,
        ]);

        $report->statusHistories()->create([
            'from_status_id' => $currentStatus->id,
            'to_status_id' => $targetStatus->id,
            'changed_by' => $actor->id,
            'note' => $note,
            'is_public' => $isPublic,
        ]);


        $report->loadMissing('citizenProfile');

        SendStatusPushNotification::dispatch(
            userId: (int) $report->citizenProfile->user_id,
            type: 'complaint_report',
            entityId: (int) $report->id,
            status: $targetStatus->key,
            title: 'تحديث حالة الشكوى',
            body: "أصبحت حالة الشكوى: {$targetStatus->name}",
        )->afterCommit();

        return $report->refresh();
    }

    private function assertTransitionAllowed(string $fromStatusKey, string $toStatusKey): void
    {
        if (! $this->canTransition($fromStatusKey, $toStatusKey)) {
            throw ValidationException::withMessages([
                'status' => [
                    "The complaint cannot transition from [{$fromStatusKey}] to [{$toStatusKey}].",
                ],
            ]);
        }
    }

    private function statusById(int $statusId): ComplaintStatus
    {
        $status = ComplaintStatus::query()->find($statusId);

        if ($status === null) {
            throw new LogicException("Complaint status [{$statusId}] does not exist.");
        }

        return $status;
    }

    private function loadStatusesForReports(Collection $reports): Collection
    {
        $statusIds = $reports
            ->pluck('current_status_id')
            ->filter()
            ->map(fn ($statusId) => (int) $statusId)
            ->unique()
            ->values();

        return ComplaintStatus::query()
            ->whereIn('id', $statusIds)
            ->get()
            ->keyBy(fn (ComplaintStatus $status) => (int) $status->id);
    }

    /*
     * Returns only the target keys that must be visited.
     *
     * Example:
     *
     * from: submitted
     * to: in_progress
     *
     * result:
     * [
     *     under_review,
     *     forwarded_to_department,
     *     in_progress,
     * ]
     */
    private function findTransitionPath(string $fromStatusKey, string $toStatusKey): array
    {
        $queue = [
            [
                'status' => $fromStatusKey,
                'path' => [],
            ],
        ];

        $visited = [
            $fromStatusKey => true,
        ];

        while ($queue !== []) {
            $item = array_shift($queue);
            $currentStatusKey = $item['status'];
            $currentPath = $item['path'];

            foreach (self::ALLOWED_TRANSITIONS[$currentStatusKey] ?? [] as $nextStatusKey) {
                if (isset($visited[$nextStatusKey])) {
                    continue;
                }

                $nextPath = [
                    ...$currentPath,
                    $nextStatusKey,
                ];

                if ($nextStatusKey === $toStatusKey) {
                    return $nextPath;
                }

                $visited[$nextStatusKey] = true;

                $queue[] = [
                    'status' => $nextStatusKey,
                    'path' => $nextPath,
                ];
            }
        }

        throw ValidationException::withMessages([
            'status' => [
                "The report cannot be synchronized from [{$fromStatusKey}] to [{$toStatusKey}].",
            ],
        ]);
    }
}
