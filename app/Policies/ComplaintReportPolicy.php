<?php

namespace App\Policies;


use App\Models\ComplaintReport;
use App\Models\User;

class ComplaintReportPolicy
{
    public function view(User $user, ComplaintReport $report): bool
    {
        return $user->can('view own complaints')
            && $this->ownsReport($user, $report);
    }

    public function update(User $user, ComplaintReport $report): bool
    {
        return $user->can('update own complaint draft')
            && $this->ownsReport($user, $report)
            && $this->isDraft($report);
    }

    public function delete(User $user, ComplaintReport $report): bool
    {
        return $user->can('delete own complaint draft')
            && $this->ownsReport($user, $report)
            && $this->isDraft($report);
    }

    public function submit(User $user, ComplaintReport $report): bool
    {
        return $user->can('submit complaint')
            && $this->ownsReport($user, $report)
            && $this->isDraft($report);
    }

    public function uploadImages(User $user, ComplaintReport $report): bool
    {
        return $user->can('update own complaint draft')
            && $this->ownsReport($user, $report)
            && $this->isDraft($report);
    }

    public function deleteImage(User $user, ComplaintReport $report): bool
    {
        return $user->can('update own complaint draft')
            && $this->ownsReport($user, $report)
            && $this->isDraft($report);
    }

    private function ownsReport(
        User $user,
        ComplaintReport $report
    ): bool {
        return $user->citizenProfile !== null
            && $user->citizenProfile->id === $report->citizen_profile_id;
    }

    private function isDraft(ComplaintReport $report): bool
    {
        return $report->submitted_at === null
            && $report->complaint_id === null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view own complaints')
            && $user->citizenProfile !== null;
    }

    public function create(User $user): bool
    {
        return $user->can('create complaint')
            && $user->citizenProfile !== null;
    }
}
