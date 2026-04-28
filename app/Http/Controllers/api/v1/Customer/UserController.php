<?php

namespace App\Http\Controllers\api\v1\Customer;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Auth;
use ImageKit\ImageKit;
class UserController extends Controller
{
    // user's order history
    public function userOrder()
    {
        $users = User::with('orders.items.variant.product')->get();

        return response()->json([
            'message' => 'Users with orders retrieved successfully',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status'=> $user->status,
                    'orders' => $user->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'total' => $order->total_price,
                            'status' => $order->status,
                            'payment' => $order->payment->status,

                            'items' => $order->items->map(function ($item) {
                                return [
                                    'quantity' => $item->quantity,
                                    'price' => $item->price,
                                    'product_name' => optional($item->variant->product)->productName,
                                ];
                            })
                        ];
                    })
                ];
            })
        ]);
    }
    public function getAllUser()
    {
        $users = User::where('role','customer')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Retrieved user success',
            'users' => $users
        ]);
    }
    public function getAllAdmin()
    {
        $users = User::where('role',"admin")->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Retrieved admin success',
            'users' => $users
        ]);
    }
    public function updateUser(Request $request, $id)
    {
        $admin = Auth::user();

        if ($admin->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin can action this.'
            ], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'required',
                'email:rfc,dns',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => 'sometimes|string|in:admin,customer',
            'status' => 'string|in:active,inactive',
            'image' => 'nullable|image|max:5120'
            
        ]);
        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'role',
            'status'
        ]);

        $imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );

        // ✅ Upload only if image exists
        if ($request->hasFile('image')) {

            $file = $request->file('image');

            try {
                $upload = $imageKit->upload([
                    'file' => base64_encode(file_get_contents($file)),
                    'fileName' => $file->getClientOriginalName(),
                    'folder' => '/avatars',
                ]);

                // Save new image first
                $data['image'] = $upload->result->url;
                $data['image_file_id'] = $upload->result->fileId;

                // THEN delete old image safely
                if ($user->image_file_id) {
                    try {
                        $imageKit->deleteFile($user->image_file_id);
                    } catch (\Exception $e) {
                        \Log::error("Delete old image failed: " . $e->getMessage());
                    }
                }

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to upload image: ' . $e->getMessage(),
                ], 500);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'Updated user successfully',
            'user' => $user
        ]);
    }
    // search by id
    public function searchUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        return response()->json([
            'message' => 'Search user successfully',
            'user' => $user
        ]);
    }
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        $user->delete();
        return response()->json([
            'message' => 'Search user successfully',
            'user' => $user
        ]);
    }

}
