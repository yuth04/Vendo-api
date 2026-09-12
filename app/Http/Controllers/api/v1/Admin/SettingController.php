<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Services\SystemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use ImageKit\ImageKit;

class SettingController extends Controller
{
    // Setting Service
    public function __construct(protected SettingService $setting, protected SystemLogService $systemLogService)
    {

    }
    // Change Name
    public function changeName(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can access this endpoint',
            ], 403);
        }
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',

        ]);
        $user = auth()->user();
        $oldValues = $user->only(['first_name', 'last_name']);
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
        ]);
        $newValues = $user->only(['first_name', 'last_name']);

        // System Log Audit
        $this->systemLogService->log(
            action: 'CHANGE_NAME',
            module: 'Profile Settings',
            description: "Changed name from '{$oldValues['first_name']} {$oldValues['last_name']}' to '{$request->first_name} {$request->last_name}'",
            entity: $user,
            oldValues: $oldValues,
            newValues: $newValues
        );
        return response()->json([
            'message' => 'Name changed successfully',
            'user' => $user,
        ], 200);
    }
    // Change Password
    public function changePassword(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can access this endpoint',
            ], 403);
        }
        $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 400);
        }
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
        // System Log Audit
        $this->systemLogService->log(
            action: 'CHANGE_PASSWORD',
            module: 'Profile Settings',
            description: "User {$user->email} updated their account password.",
            entity: $user,
            oldValues: null,
            newValues: null
        );
        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }
    // change profile picture
    public function changeProfileImage(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can access this endpoint',
            ], 403);
        }
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $user = $request->user();
        $oldImage = $user->image;
        $imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );

        // Delete old image if exists
        if ($user->image_file_id) {
            try {
                $deleteResponse = $imageKit->deleteFile($user->image_file_id);
                if (!isset($deleteResponse->success) || !$deleteResponse->success) {
                    // Optional: log the delete failure
                    \Log::warning("Failed to delete old image: " . $user->image_file_id);
                }
            } catch (\Exception $e) {
                \Log::error("ImageKit delete error: " . $e->getMessage());
            }
        }

        $file = $request->file('image');

        // Upload new image
        try {
            $upload = $imageKit->upload([
                'file' => base64_encode(file_get_contents($file)),
                'fileName' => $file->getClientOriginalName(),
                'folder' => '/avatars',
            ]);

            $user->update([
                'image' => $upload->result->url,
                'image_file_id' => $upload->result->fileId,
            ]);
            // System Log Audit
            $this->systemLogService->log(
                action: 'UPDATE_AVATAR',
                module: 'Profile Settings',
                description: "User {$user->email} updated their profile image.",
                entity: $user,
                oldValues: ['image' => $oldImage],
                newValues: ['image' => $user->image]
            );
            return response()->json([
                'message' => 'Avatar updated successfully',
                'image' => $user->image,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function index()
    {
        return response()->json([
            'store_name' => $this->setting->get('store_name'),
            'store_logo' => $this->setting->get('store_logo'),
            'favicon' => $this->setting->get('favicon'),
            'store_description' => $this->setting->get('store_description'),
            'support_email' => $this->setting->get('support_email'),
            'phone_number' => $this->setting->get('phone_number'),
            'address' => $this->setting->get('address'),
            'timezone' => $this->setting->get('timezone'),
            'language' => $this->setting->get('language'),
            'currency' => $this->setting->get('currency'),
        ]);
    }
    public function update(Request $request): JsonResponse
    {
        if (!in_array(Auth::user()->role, ['super-admin', 'admin'])) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_description' => 'nullable|string',
            'support_email' => 'required|email',
            'phone_number' => 'required|string|max:30',
            'address' => 'required|string',
            'timezone' => 'required|string',
            'language' => 'required|string',
            'currency' => 'required|in:USD,KHR',
            'store_logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
        ]);

        $fields = [
            'store_name',
            'store_description',
            'support_email',
            'phone_number',
            'address',
            'timezone',
            'language',
            'currency',
        ];

        $oldSettings = [];
        $newSettings = [];

        foreach ($fields as $field) {
            $oldSettings[$field] = $this->setting->get($field);
            $this->setting->set($field, $validated[$field]);
            $newSettings[$field] = $validated[$field];
        }

        if ($request->hasFile('store_logo')) {
            $path = $request->file('store_logo')->store('settings', 'public');
            $this->setting->set('store_logo', $path);
            $newSettings['store_logo'] = $path;
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            $this->setting->set('favicon', $path);
            $newSettings['favicon'] = $path;
        }

        // System Log Audit
        $this->systemLogService->log(
            action: 'UPDATE_SETTINGS',
            module: 'Settings',
            description: 'Updated store configuration by ' . Auth::user()->email,
            entity: null,
            oldValues: $oldSettings,
            newValues: $newSettings
        );

        return response()->json([
            'message' => 'Settings updated successfully.',
        ]);
    }
}
