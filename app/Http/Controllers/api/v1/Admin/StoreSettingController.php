<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreSettingController extends Controller
{
    // Get all
    public function index(): JsonResponse
    {
        $settings = StoreSetting::where('is_public', true)->orderBy('group')->orderBy('key')->get();

        return response()->json([
            'success' => true,
            'message' => 'Footer retrieve successfully',
            'data' => $settings,
        ]);

    }
    // Get by id
    public function show($id)
    {
        $setting = StoreSetting::where('is_public', true)->find($id);

        if (!$setting) {

            return response()->json([
                'success' => false,
                'message ' => 'Setting not found'
            ], 404);
        }
        return response()->json([
            'message' => "Setting get successfully",
            'data' => $setting
        ]);
    }
    // Store
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:store_settings,key|max:100',
            'value' => 'nullable|string',
            'group' => 'required|string|in:brand,contact,social,support,quicklinks,footer,general',
            'type' => 'required|string|in:text,image,url,email,phone',
            'label' => 'required|string|max:100',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $setting = StoreSetting::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Setting created successfully.',
            'data' => $setting,
        ], 201);
    }
    // Update
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = StoreSetting::find($id);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|string|unique:store_settings,key,' . $id . '|max:100',
            'value' => 'nullable|string',
            'group' => 'sometimes|string|in:brand,contact,social,support,quicklinks,footer,general',
            'type' => 'sometimes|string|in:text,image,url,email,phone',
            'label' => 'sometimes|string|max:100',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $setting->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully.',
            'data' => $setting->fresh(),
        ]);
    }
    // Delete
    public function destroy(int $id): JsonResponse
    {
        $setting = StoreSetting::find($id);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully.',
        ]);
    }

    // bulk-update
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->settings as $item) {
            StoreSetting::where('key', $item['key'])
                ->update(['value' => $item['value']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
        ]);
    }
    // Super admin
    // Get all
    public function getAll(): JsonResponse
    {
        $settings = StoreSetting::orderBy('group')->orderBy('key')->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);

    }
    // Get by id
    public function getById($id)
    {
        $setting = StoreSetting::find($id);

        if (!$setting) {

            return response()->json([
                'success' => false,
                'message ' => 'Setting not found'
            ], 404);
        }
        return response()->json([
            'message' => "Setting retriev successfully",
            'data' => $setting
        ]);
    }
}
