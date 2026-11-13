<?php

namespace App\Http\Controllers;

use App\Http\Requests\VipPackageRequest;
use App\Models\VipPackage;
use App\Services\VipPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipPackageController extends Controller
{
    public function index(Request $request, VipPackageService $service): JsonResponse
    {
        return response()->json(['data' => $service->listForUser($request->user())]);
    }

    public function store(VipPackageRequest $request, VipPackageService $service): JsonResponse
    {
        return response()->json(['data' => $service->add($request->user(), $request->validated())], 201);
    }

    public function update(VipPackageRequest $request, VipPackage $vipPackage, VipPackageService $service): JsonResponse
    {
        return response()->json(['data' => $service->update($request->user(), $vipPackage, $request->validated())]);
    }

    public function toggle(Request $request, VipPackage $vipPackage, VipPackageService $service): JsonResponse
    {
        $active = $request->boolean('is_active');

        return response()->json(['data' => $service->setActive($request->user(), $vipPackage, $active)]);
    }
}
