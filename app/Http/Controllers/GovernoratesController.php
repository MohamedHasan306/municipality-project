<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;

use App\Models\Governorate;
use Illuminate\Http\Request;

class GovernoratesController extends Controller
{
    use ApiResponse;
    public function index()
    {

        $governorates = Governorate::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            $governorates,
            'Governorates retrieved successfully.'
        );
    }

    public function municipalities(Governorate $governorate)
    {
        $municipalities = $governorate->municipalities()
            ->select('id', 'name', 'governorate_id')
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            $municipalities,
            'Municipalities retrieved successfully.'
        );
    }


}
