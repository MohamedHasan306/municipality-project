<?php

namespace Database\Seeders;

use App\Models\CitizenProfile;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintReport;
use App\Models\ComplaintStatus;
use App\Models\ComplaintStatusHistory;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComplaintDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $municipalityId = $this->municipalityId();
            $citizenProfiles = $this->citizenProfiles($municipalityId);
            $categories = $this->categories();
            $statusIds = $this->statusIds();
            $technicalOfficeUserId = $this->technicalOfficeUserId($municipalityId);
            $now = now();

            foreach ($this->clusters() as $clusterIndex => $cluster) {
                $category = $categories[$clusterIndex % $categories->count()];

                $complaintDefinitions = [
                    [
                        'status_key' => ComplaintStatus::UNDER_REVIEW,
                        'days_ago' => 2,
                        'latitude_offset' => 0.00000,
                        'longitude_offset' => 0.00000,
                        'text_location' => $cluster['text_location'],
                    ],
                    [
                        'status_key' => ComplaintStatus::FORWARDED_TO_DEPARTMENT,
                        'days_ago' => 3,
                        'latitude_offset' => 0.00012,
                        'longitude_offset' => 0.00006,
                        'text_location' => $cluster['text_location'],
                    ],
                    [
                        'status_key' => ComplaintStatus::IN_PROGRESS,
                        'days_ago' => 5,
                        'latitude_offset' => -0.00010,
                        'longitude_offset' => 0.00008,
                        'text_location' => $cluster['text_location'],
                    ],
                    $this->negativeCase($clusterIndex, $cluster),
                ];

                foreach ($complaintDefinitions as $complaintIndex => $definition) {
                    $number = ($clusterIndex * 4) + $complaintIndex + 1;
                    $submittedAt = $now->copy()->subDays($definition['days_ago']);
                    $latitude = $cluster['latitude'] + $definition['latitude_offset'];
                    $longitude = $cluster['longitude'] + $definition['longitude_offset'];
                    $statusKey = $definition['status_key'];

                    $complaintTitle = sprintf(
                        '[DEMO-C%02d] %s complaint %d',
                        $number,
                        $cluster['label'],
                        $complaintIndex + 1
                    );

                    $complaint = Complaint::query()->updateOrCreate([
                        'municipality_id' => $municipalityId,
                        'title' => $complaintTitle,
                    ], [
                        'category_id' => $category->id,
                        'current_status_id' => $statusIds[$statusKey],
                        'canonical_description' => "Demo unified complaint for {$cluster['label']}.",
                        'text_location' => $definition['text_location'],
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'submitted_at' => $submittedAt,
                    ]);

                    $citizenProfile = $citizenProfiles[
                    ($number - 1) % $citizenProfiles->count()
                    ];

                    $reportTitle = sprintf(
                        '[DEMO-R%02d] Report for complaint %d',
                        $number,
                        $number
                    );

                    $report = $this->saveReport([
                        'title' => $reportTitle,
                        'citizen_profile_id' => $citizenProfile->id,
                        'complaint_id' => $complaint->id,
                        'municipality_id' => $municipalityId,
                        'category_id' => $category->id,
                        'current_status_id' => $statusIds[$statusKey],
                        'description' => "Citizen report linked to demo complaint {$number}.",
                        'text_location' => $definition['text_location'],
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'submitted_at' => $submittedAt,
                        'linked_by' => $technicalOfficeUserId,
                        'linked_at' => $submittedAt->copy()->addHours(1),
                    ]);

                    $this->seedHistory(
                        $report,
                        $statusKey,
                        $statusIds,
                        $technicalOfficeUserId,
                        $submittedAt
                    );
                }

                $pendingCitizenProfile = $citizenProfiles[
                ($clusterIndex + 24) % $citizenProfiles->count()
                ];

                $pendingTitle = sprintf(
                    '[DEMO-PENDING-%02d] Similar report for %s',
                    $clusterIndex + 1,
                    $cluster['label']
                );

                $pendingSubmittedAt = $now->copy()->subDay();

                $pendingReport = $this->saveReport([
                    'title' => $pendingTitle,
                    'citizen_profile_id' => $pendingCitizenProfile->id,
                    'complaint_id' => null,
                    'municipality_id' => $municipalityId,
                    'category_id' => $category->id,
                    'current_status_id' => $statusIds[ComplaintStatus::SUBMITTED],
                    'description' => "Pending report used to test similar complaints and merge for {$cluster['label']}.",
                    'text_location' => $cluster['text_location'],
                    'latitude' => $cluster['latitude'] + 0.00004,
                    'longitude' => $cluster['longitude'] + 0.00003,
                    'submitted_at' => $pendingSubmittedAt,
                    'linked_by' => null,
                    'linked_at' => null,
                ]);

                $this->seedHistory(
                    $pendingReport,
                    ComplaintStatus::SUBMITTED,
                    $statusIds,
                    $technicalOfficeUserId,
                    $pendingSubmittedAt
                );
            }
        }, attempts: 3);

        $this->command?->info('Twenty-four unified complaints and thirty complaint reports were seeded successfully.');
        $this->command?->info('Use reports starting with [DEMO-PENDING-] to test similarity, merge, and direct rejection.');
    }

    private function saveReport(array $data): ComplaintReport
    {
        $report = ComplaintReport::withTrashed()
            ->where('title', $data['title'])
            ->first();

        if ($report === null) {
            $report = new ComplaintReport();
        }

        if ($report->exists && $report->trashed()) {
            $report->restore();
        }

        $report->fill($data);
        $report->save();

        return $report->refresh();
    }

    private function seedHistory(
        ComplaintReport $report,
        string $targetStatusKey,
        Collection $statusIds,
        ?int $technicalOfficeUserId,
                        $submittedAt
    ): void {
        ComplaintStatusHistory::query()
            ->where('complaint_report_id', $report->id)
            ->delete();

        $path = $this->statusPath($targetStatusKey);

        $previousStatusId = null;

        foreach ($path as $index => $statusKey) {
            $historyTime = $submittedAt->copy()->addHours($index);

            ComplaintStatusHistory::query()->create([
                'complaint_report_id' => $report->id,
                'from_status_id' => $previousStatusId,
                'to_status_id' => $statusIds[$statusKey],
                'changed_by' => $index === 0
                    ? null
                    : $technicalOfficeUserId,
                'note' => null,
                'is_public' => true,
                'created_at' => $historyTime,
                'updated_at' => $historyTime,
            ]);

            $previousStatusId = $statusIds[$statusKey];
        }
    }

    private function statusPath(string $targetStatusKey): array
    {
        return match ($targetStatusKey) {
            ComplaintStatus::SUBMITTED => [
                ComplaintStatus::SUBMITTED,
            ],

            ComplaintStatus::UNDER_REVIEW => [
                ComplaintStatus::SUBMITTED,
                ComplaintStatus::UNDER_REVIEW,
            ],

            ComplaintStatus::FORWARDED_TO_DEPARTMENT => [
                ComplaintStatus::SUBMITTED,
                ComplaintStatus::UNDER_REVIEW,
                ComplaintStatus::FORWARDED_TO_DEPARTMENT,
            ],

            ComplaintStatus::IN_PROGRESS => [
                ComplaintStatus::SUBMITTED,
                ComplaintStatus::UNDER_REVIEW,
                ComplaintStatus::FORWARDED_TO_DEPARTMENT,
                ComplaintStatus::IN_PROGRESS,
            ],

            ComplaintStatus::RESOLVED => [
                ComplaintStatus::SUBMITTED,
                ComplaintStatus::UNDER_REVIEW,
                ComplaintStatus::FORWARDED_TO_DEPARTMENT,
                ComplaintStatus::IN_PROGRESS,
                ComplaintStatus::RESOLVED,
            ],

            ComplaintStatus::REJECTED => [
                ComplaintStatus::SUBMITTED,
                ComplaintStatus::UNDER_REVIEW,
                ComplaintStatus::REJECTED,
            ],

            default => throw new RuntimeException(
                "Unsupported complaint status [{$targetStatusKey}]."
            ),
        };
    }

    private function negativeCase(int $clusterIndex, array $cluster): array
    {
        return match ($clusterIndex) {
            0 => [
                'status_key' => ComplaintStatus::RESOLVED,
                'days_ago' => 2,
                'latitude_offset' => 0.00005,
                'longitude_offset' => -0.00004,
                'text_location' => $cluster['text_location'],
            ],

            1 => [
                'status_key' => ComplaintStatus::UNDER_REVIEW,
                'days_ago' => 15,
                'latitude_offset' => 0.00005,
                'longitude_offset' => -0.00004,
                'text_location' => $cluster['text_location'],
            ],

            2 => [
                'status_key' => ComplaintStatus::UNDER_REVIEW,
                'days_ago' => 2,
                'latitude_offset' => 0.00005,
                'longitude_offset' => -0.00004,
                'text_location' => $cluster['text_location'].' - different landmark',
            ],

            3 => [
                'status_key' => ComplaintStatus::UNDER_REVIEW,
                'days_ago' => 2,
                'latitude_offset' => 0.00130,
                'longitude_offset' => 0.00000,
                'text_location' => $cluster['text_location'],
            ],

            4 => [
                'status_key' => ComplaintStatus::REJECTED,
                'days_ago' => 2,
                'latitude_offset' => 0.00005,
                'longitude_offset' => -0.00004,
                'text_location' => $cluster['text_location'],
            ],

            default => [
                'status_key' => ComplaintStatus::RESOLVED,
                'days_ago' => 2,
                'latitude_offset' => 0.00005,
                'longitude_offset' => -0.00004,
                'text_location' => $cluster['text_location'],
            ],
        };
    }

    private function clusters(): array
    {
        return [
            [
                'label' => 'Road and pothole',
                'text_location' => 'Main Street near the central market',
                'latitude' => 13.8575400,
                'longitude' => 12.5994900,
            ],
            [
                'label' => 'Electricity',
                'text_location' => 'Al Noor Street near the public school',
                'latitude' => 13.8675400,
                'longitude' => 12.6094900,
            ],
            [
                'label' => 'Water leakage',
                'text_location' => 'Al Salam neighborhood near the mosque',
                'latitude' => 13.8775400,
                'longitude' => 12.6194900,
            ],
            [
                'label' => 'Garbage',
                'text_location' => 'Municipality Square beside the bus station',
                'latitude' => 13.8875400,
                'longitude' => 12.6294900,
            ],
            [
                'label' => 'Sewer',
                'text_location' => 'Al Amal Street near the health center',
                'latitude' => 13.8975400,
                'longitude' => 12.6394900,
            ],
            [
                'label' => 'Public park',
                'text_location' => 'Central Park near the eastern gate',
                'latitude' => 13.9075400,
                'longitude' => 12.6494900,
            ],
        ];
    }

    private function categories(): Collection
    {
        $categories = ComplaintCategory::query()
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->get();

        if ($categories->isEmpty()) {
            $categories = ComplaintCategory::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        if ($categories->isEmpty()) {
            throw new RuntimeException(
                'No active complaint category was found. Run the complaint category seeder first.'
            );
        }

        return $categories;
    }

    private function citizenProfiles(int $municipalityId): Collection
    {
        $profiles = CitizenProfile::query()
            ->where('municipality_id', $municipalityId)
            ->orderBy('id')
            ->get();

        if ($profiles->isEmpty()) {
            throw new RuntimeException(
                'No citizen profile was found for the selected municipality.'
            );
        }

        return $profiles;
    }

    private function statusIds(): Collection
    {
        $requiredStatusKeys = [
            ComplaintStatus::SUBMITTED,
            ComplaintStatus::UNDER_REVIEW,
            ComplaintStatus::FORWARDED_TO_DEPARTMENT,
            ComplaintStatus::IN_PROGRESS,
            ComplaintStatus::RESOLVED,
            ComplaintStatus::REJECTED,
        ];

        $statusIds = ComplaintStatus::query()
            ->whereIn('key', $requiredStatusKeys)
            ->pluck('id', 'key');

        $missingStatusKeys = array_diff(
            $requiredStatusKeys,
            $statusIds->keys()->all()
        );

        if ($missingStatusKeys !== []) {
            throw new RuntimeException(
                'Missing complaint statuses: '.implode(', ', $missingStatusKeys)
            );
        }

        return $statusIds;
    }

    private function technicalOfficeUserId(int $municipalityId): ?int
    {
        return User::role('technical_office')
            ->whereHas('employeeProfile', fn ($query) => $query
                ->where('municipality_id', $municipalityId)
                ->where('status', 'active')
            )
            ->orderBy('id')
            ->value('id');
    }

    private function municipalityId(): int
    {
        $configuredMunicipalityId = (int) env(
            'SEED_MUNICIPALITY_ID',
            0
        );

        if ($configuredMunicipalityId > 0) {
            $exists = Municipality::query()
                ->whereKey($configuredMunicipalityId)
                ->exists();

            if (! $exists) {
                throw new RuntimeException(
                    'The municipality configured in SEED_MUNICIPALITY_ID does not exist.'
                );
            }

            return $configuredMunicipalityId;
        }

        $municipalityId = Municipality::query()
            ->where('status', true)
            ->orderBy('id')
            ->value('id');

        $municipalityId ??= Municipality::query()
            ->orderBy('id')
            ->value('id');

        if ($municipalityId === null) {
            throw new RuntimeException(
                'No municipality was found. Run the municipality seeder first.'
            );
        }

        return (int) $municipalityId;
    }
}
