<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\DepartmentRiskService;
use Illuminate\Http\JsonResponse;

class RiskDashboardController extends Controller
{
    public function __construct(private readonly DepartmentRiskService $risk)
    {
    }

    /** GET /api/departments/risk-summary */
    public function summary(): JsonResponse
    {
        return response()->json($this->risk->summary());
    }

    /** GET /api/departments/{department}/historical-risk */
    public function history(Department $department): JsonResponse
    {
        return response()->json($this->risk->history($department));
    }
}
