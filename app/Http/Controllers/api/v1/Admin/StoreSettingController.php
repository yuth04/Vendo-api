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
        $settings = StoreSetting::where('is_public', true)->get()->keyBy('key');

        return response()->json([
            'message' => 'Footer retrieved successfully',
            'data' => [
                'brand_info' => [
                    'id' => $settings['brand_title']->id ?? null,
                    'title' => $settings['brand_title']->value ?? '',
                    'description' => $settings['brand_description']->value ?? '',
                ],
                'navigation_columns' => [
                    array_merge(['id' => $settings['nav_support']->id ?? null], json_decode($settings['nav_support']->value, true) ?? []),
                    array_merge(['id' => $settings['nav_quicklinks']->id ?? null], json_decode($settings['nav_quicklinks']->value, true) ?? []),
                ],
                'contact_info' => [
                    'id' => $settings['contact_heading']->id ?? null,
                    'heading' => $settings['contact_heading']->value ?? '',
                    'phone' => [
                        'id' => $settings['contact_phone_display']->id ?? null,
                        'display' => $settings['contact_phone_display']->value ?? '',
                        'raw' => $settings['contact_phone_raw']->value ?? '',
                    ],
                    'email' => $settings['contact_email']->value ?? '',
                    'address' => array_merge(
                        ['id' => $settings['contact_address']->id ?? null],
                        json_decode($settings['contact_address']->value, true) ?? []
                    ),
                ],
            ],
        ]);
    }

    // Get by id
    public function show($id): JsonResponse
    {
        $setting = StoreSetting::find($id);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting retrieved successfully.',
            'data' => $setting,
        ]);
    }

    // Store
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:store_settings,key|max:100',
            'value' => 'nullable|string',
            'group' => 'required|string|in:brand,navigation,contact,social,support,quicklinks,footer,general',
            'type' => 'required|string|in:text,image,url,email,phone,json',
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
            'group' => 'sometimes|string|in:brand,navigation,contact,social,support,quicklinks,footer,general',
            'type' => 'sometimes|string|in:text,image,url,email,phone,json',
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

    // Bulk update
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

    // Super admin - Get all
    public function getAll(): JsonResponse
    {
        $settings = StoreSetting::orderBy('group')->orderBy('key')->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    // Super admin - Get by id
    public function getById($id): JsonResponse
    {
        $setting = StoreSetting::find($id);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting retrieved successfully.',
            'data' => $setting,
        ]);
    }
}