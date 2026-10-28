<?php

namespace App\Http\Controllers\WorkUnit;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkUnit\AssignDepartmentManagerRequest;
use App\Http\Resources\WorkUnits\WorkUnitResource;
use App\Http\Traits\ApiResponse;
use App\Models\WorkUnit;
use App\Services\WorkUnit\WorkUnitDepartmentManagerService;
use Illuminate\Http\JsonResponse;

class WorkUnitDepartmentManagerController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WorkUnitDepartmentManagerService $service)
    {
    }

    public function update(AssignDepartmentManagerRequest $request, WorkUnit $workUnit): JsonResponse
    {
        $workUnit = $this->service->assign($request->user(), $workUnit, (int) $request->validated('department_manager_id'));

        return $this->successResponse((new WorkUnitResource($workUnit))->resolve($request), 'The department manager was assigned to the work unit successfully.');
    }
}
