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
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'staff') {
            return response()->json([
                'message' => 'Only super-admin or staff can access this endpoint',
            ], 403);
        }
        $users = User::with('orders.items.variant.product')->get();

        return response()->json([
            'message' => 'Users with orders retrieved successfully',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status' => $user->status,
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
        $users = User::where('role', 'customer')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Users retrieved success',
            'users' => $users
        ]);
    }
    public function getAllAdmin()
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can access this endpoint',
            ], 403);
        }
        $users = User::whereIn('role', ["super-admin", "admin"])->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Admins retrieved success',
            'users' => $users
        ]);
    }
    public function getAllStaff()
    {
        $staffs = User::where('role', 'staff')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Get all staff successfully',
            'staffs' => $staffs
        ]);
    }
    // update user
    public function updateUser(Request $request, $id)
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json([
                'message' => 'Only super-admin can access this endpoint',
            ], 403);
        }
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email:rfc,dns',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => 'sometimes|string|in:super-admin,admin,staff,customer',
            'status' => 'nullable|string|in:active,inactive',
            'image' => 'nullable|image|max:5120',
        ]);

        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'role',
            'status',

        ]);

        $authUser = Auth::user();
        $data['updated_by'] = [
            'id' => $authUser->id,
            'first_name' => $authUser->first_name,
            'last_name' => $authUser->last_name,
            'email' => $authUser->email,
            'image' => $authUser->image,
            'updated_at' => now()->format('d-m-Y h:i:s A'),
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            try {
                $upload = $imageKit->upload([
                    'file' => base64_encode(file_get_contents($file)),
                    'fileName' => $file->getClientOriginalName(),
                    'folder' => '/avatars',
                ]);

                $data['image'] = $upload->result->url;
                $data['image_file_id'] = $upload->result->fileId;

                if ($user->image_file_id) {
                    try {
                        $imageKit->deleteFile($user->image_file_id);
                    } catch (\Exception $e) {
                        \Log::error('Delete old image failed: ' . $e->getMessage());
                    }
                }

            } catch (\Exception $e) {
                \Log::error('Image upload failed: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Failed to upload image. Please try again.',
                ], 500);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'Updated user successfully',
            'user' => $user->fresh()
        ]);
    }
    // search user by id
    public function searchUser($id)
    {
        $user = User::where('role', 'customer')->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        return response()->json([
            'message' => 'Search user successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'image' => $user->image,
                'role' => $user->role,
                'created_by' => [
                    'id' => $user->creator?->id,
                    'first_name' => $user->creator?->first_name,
                    'last_name' => $user->creator?->last_name,
                    'email' => $user->creator?->email,
                    'image' => $user->creator?->image,
                    'created_at' => $user->created_at->format('d-m-Y g:i:s A'),
                ],

                'updated_by' => [
                    'id' => $user->updater?->id,
                    'first_name' => $user->updater?->first_name,
                    'last_name' => $user->updater?->last_name,
                    'email' => $user->updater?->email,
                    'image' => $user->updater?->image,
                    'updated_at' => $user->updated_at->format('d-m-Y g:i:s A'),
                ],
            ]
        ]);
    }
    // search admin by id
    public function searchAdmin($id)
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json([
                'message' => 'Only super-admin can access this endpoint',
            ], 403);
        }
        $user = User::whereIn('role', ['super-admin', 'admin', 'staff'])->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        return response()->json([
            'message' => 'Search admin successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'image' => $user->image,
                'role' => $user->role,
                'created_by' => [
                    'id' => $user->creator?->id,
                    'first_name' => $user->creator?->first_name,
                    'last_name' => $user->creator?->last_name,
                    'email' => $user->creator?->email,
                    'image' => $user->creator?->image,
                    'created_at' => $user->created_at->format('d-m-Y g:i:s A'),
                ],

                'updated_by' => [
                    'id' => $user->updater?->id,
                    'first_name' => $user->updater?->first_name,
                    'last_name' => $user->updater?->last_name,
                    'email' => $user->updater?->email,
                    'image' => $user->updater?->image,
                    'updated_at' => $user->updated_at->format('d-m-Y g:i:s A'),
                ],
            ]
        ]);
    }
    // search staff by id
    public function searchStaff($id)
    {
        $user = User::where('role', 'staff')->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        return response()->json([
            'message' => 'Search staff successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'image' => $user->image,
                'role' => $user->role,
                'created_by' => [
                    'id' => $user->creator?->id,
                    'first_name' => $user->creator?->first_name,
                    'last_name' => $user->creator?->last_name,
                    'email' => $user->creator?->email,
                    'image' => $user->creator?->image,
                    'created_at' => $user->created_at->format('d-m-Y g:i:s A'),
                ],

                'updated_by' => [
                    'id' => $user->updater?->id,
                    'first_name' => $user->updater?->first_name,
                    'last_name' => $user->updater?->last_name,
                    'email' => $user->updater?->email,
                    'image' => $user->updater?->image,
                    'updated_at' => $user->updated_at->format('d-m-Y g:i:s A'),
                ],
            ]
        ]);
    }
    // delete user
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json([
                'message' => 'Only super-admin can access this endpoint',
            ], 403);
        }
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        if (auth()->user()->role !== 'super-admin') {
            return response()->json([
                'message' => 'Only super-admin can access this endpoint',
            ], 403);
        }
        $user->delete();
        return response()->json([
            'message' => 'User deleted successfully',
            'user' => $user
        ]);
    }
    // update role user
    public function updateRoleUser(Request $request, $id)
    {
        $authUser = Auth::user();
        if ($authUser->role !== 'super-admin' && $authUser->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin can access this endpoint',
            ], 403);
        }

        $request->validate([
            'role' => 'required|string|in:super-admin,admin,staff,customer'
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }
        if ($user->id === $authUser->id) {
            return response()->json([
                'message' => 'You cannot change your own role.'
            ], 403);
        }

        // admin restriction
        if ($authUser->role === 'admin') {
            // admin cannot update super-admin
            if ($user->role === 'super-admin') {
                return response()->json([
                    'message' => 'Admin cannot update super-admin role.'
                ], 403);
            }
            // Admin can update admin, staff, and customer
            if (!in_array($request->role, ['admin', 'staff', 'customer'])) {
                return response()->json([
                    'message' => 'Admin can only assign admin, staff and customer roles.'
                ], 403);
            }
        }
        if ($user->role === $request->role) {
            return response()->json([
                'message' => "This user is already {$user->role}."
            ], 200);
        }

        $user->update([
            'role' => $request->role,
            'updated_by' => [
                'id' => $authUser->id,
                'first_name' => $authUser->first_name,
                'last_name' => $authUser->last_name,
                'email' => $authUser->email,
                'image' => $authUser->image,
                'updated_at' => now()->format('d-m-Y g:i:s A'),
            ],
        ]);

        return response()->json([
            'message' => 'Updated role successfully.',
            'user' => $user->fresh()
        ]);
    }
}
