<?php

namespace App\Services\Complaints;

use App\Models\ComplaintReport;
use App\Models\ComplaintStatus;
use App\Models\ComplaintStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

class ComplaintStatisticsService
{
    public function overview(User $user, array $filters): array
    {
        $municipalityId = $this->employeeMunicipalityId($user);
        [$from, $to] = $this->resolvePeriod($filters);

        $resolvedStatus = ComplaintStatus::query()
            ->where('key', ComplaintStatus::RESOLVED)
            ->firstOrFail();

        $rejectedStatus = ComplaintStatus::query()
            ->where('key', ComplaintStatus::REJECTED)
            ->firstOrFail();

        $submittedInPeriod = ComplaintReport::query()
            ->where('municipality_id', $municipalityId)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$from, $to])
            ->count();

        $totalSubmittedAllTime = ComplaintReport::query()
            ->where('municipality_id', $municipalityId)
            ->whereNotNull('submitted_at')
            ->count();

        $resolvedInPeriod = ComplaintStatusHistory::query()
            ->where('to_status_id', $resolvedStatus->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('complaintReport', fn ($query) => $query
                ->where('municipality_id', $municipalityId)
            )
            ->distinct()
            ->count('complaint_report_id');

        $rejectedInPeriod = ComplaintStatusHistory::query()
            ->where('to_status_id', $rejectedStatus->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('complaintReport', fn ($query) => $query
                ->where('municipality_id', $municipalityId)
            )
            ->distinct()
            ->count('complaint_report_id');

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],

            'submitted_in_period' => $submittedInPeriod,
            'resolved_in_period' => $resolvedInPeriod,
            'rejected_in_period' => $rejectedInPeriod,
            'total_submitted_all_time' => $totalSubmittedAllTime,
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        if (isset($filters['date_from'], $filters['date_to'])) {
            return [
                Carbon::parse($filters['date_from'])->startOfDay(),
                Carbon::parse($filters['date_to'])->endOfDay(),
            ];
        }

        return [
            now()->startOfMonth(),
            now()->endOfDay(),
        ];
    }

    private function employeeMunicipalityId(User $user): int
    {
        $employeeProfile = $user->employeeProfile;

        if ($employeeProfile === null) {
            throw new AuthorizationException('This account does not have an employee profile.');
        }

        if ($employeeProfile->status !== 'active') {
            throw new AuthorizationException('This employee account is inactive.');
        }

        if ($employeeProfile->municipality_id === null) {
            throw new AuthorizationException('This account is not associated with a municipality.');
        }

        return (int) $employeeProfile->municipality_id;
    }
}
