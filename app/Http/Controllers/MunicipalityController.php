<?php

namespace App\Http\Controllers;

use App\Http\Requests\Municipality\StoreMunicipalityRequest;
use App\Http\Requests\Municipality\UpdateMunicipalityRequest;
use App\Http\Resources\MunicipalityResource;
use App\Http\Traits\ApiResponse;
use App\Models\Municipality;
use Illuminate\Http\Request;

class MunicipalityController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $municipalities = Municipality::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->boolean('status'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return $this->successResponse(
            MunicipalityResource::collection($municipalities),
            'The municipalities have been successfully retrieved.'
        );
    }

    public function store(StoreMunicipalityRequest $request)
    {
        $municipality = Municipality::create($request->validated());

        return $this->successResponse(
            new MunicipalityResource($municipality),
            'The municipality has been successfully created.',
            201
        );
    }

    public function show(Municipality $municipality)
    {
        return $this->successResponse(
            new MunicipalityResource($municipality),
            'Municipality data has been successfully retrieved.'
        );
    }

    public function update(UpdateMunicipalityRequest $request, Municipality $municipality)
    {
        $municipality->update($request->validated());

        return $this->successResponse(
            new MunicipalityResource($municipality->fresh()),
            'The municipality has been successfully updated.'
        );
    }



    public function activate(Municipality $municipality)
    {
        if ($municipality->status) {
            return $this->successResponse(
                new MunicipalityResource($municipality),
                'The municipality is already activated.'
            );
        }

        $municipality->update([
            'status' => true,
        ]);

        return $this->successResponse(
            new MunicipalityResource($municipality->fresh()),
            'The municipality has been successfully activated.'
        );
    }

    public function deactivate(Municipality $municipality)
    {
        if (! $municipality->status) {
            return $this->successResponse(
                new MunicipalityResource($municipality),
                'The municipality is already deactivated.'
            );
        }

        $municipality->update([
            'status' => false,
        ]);

        return $this->successResponse(
            new MunicipalityResource($municipality->fresh()),
            'The municipality has been successfully deactivated.'
        );
    }
}
