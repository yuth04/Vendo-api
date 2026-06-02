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
<<<<<<< HEAD
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'staff') {
            return response()->json([
                'message' => 'Only super-admin or staff can access this endpoint',
            ], 403);
        }
=======
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
        $users = User::with('orders.items.variant.product')->get();

        return response()->json([
            'message' => 'Users with orders retrieved successfully',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
<<<<<<< HEAD
                    'status' => $user->status,
=======
                    'status'=> $user->status,
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
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
<<<<<<< HEAD
        $users = User::where('role', 'customer')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Users retrieved success',
=======
        $users = User::where('role','customer')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Retrieved user success',
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
            'users' => $users
        ]);
    }
    public function getAllAdmin()
    {
<<<<<<< HEAD
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
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }
        if (auth()->user()->role !== 'super-admin') {
            return response()->json([
                'message' => 'Only super-admin can action this.'
            ], 403);

        }
=======
        $users = User::whereIn('role',["admin","customer_support","staff","store_manager"])->orderBy('id', 'asc')->get();
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

>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
<<<<<<< HEAD
                'sometimes',
                'email:rfc,dns',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => 'sometimes|string|in:super-admin,admin,staff,customer',
            'status' => 'nullable|string|in:active,inactive',
            'image' => 'nullable|image|max:5120',
        ]);

=======
                'required',
                'email:rfc,dns',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => 'sometimes|string|in:admin,customer',
            'status' => 'string|in:active,inactive',
            'image' => 'nullable|image|max:5120'
            
        ]);
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'role',
<<<<<<< HEAD
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
=======
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
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de

            try {
                $upload = $imageKit->upload([
                    'file' => base64_encode(file_get_contents($file)),
                    'fileName' => $file->getClientOriginalName(),
                    'folder' => '/avatars',
                ]);

<<<<<<< HEAD
                $data['image'] = $upload->result->url;
                $data['image_file_id'] = $upload->result->fileId;

=======
                // Save new image first
                $data['image'] = $upload->result->url;
                $data['image_file_id'] = $upload->result->fileId;

                // THEN delete old image safely
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
                if ($user->image_file_id) {
                    try {
                        $imageKit->deleteFile($user->image_file_id);
                    } catch (\Exception $e) {
<<<<<<< HEAD
                        \Log::error('Delete old image failed: ' . $e->getMessage());
=======
                        \Log::error("Delete old image failed: " . $e->getMessage());
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
                    }
                }

            } catch (\Exception $e) {
<<<<<<< HEAD
                \Log::error('Image upload failed: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Failed to upload image. Please try again.',
=======
                return response()->json([
                    'message' => 'Failed to upload image: ' . $e->getMessage(),
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
                ], 500);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'Updated user successfully',
<<<<<<< HEAD
            'user' => $user->fresh()
        ]);
    }
    // search user by id
    public function searchUser($id)
    {
        $user = User::where('role', 'customer')->find($id);
=======
            'user' => $user
        ]);
    }
    // search by id
    public function searchUser($id)
    {
        $user = User::find($id);
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
        return response()->json([
            'message' => 'Search user successfully',
<<<<<<< HEAD
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
=======
            'user' => $user
        ]);
    }
>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ]);
        }
<<<<<<< HEAD
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
        if (auth()->user()->role !== 'super-admin') {
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
=======
        $user->delete();
        return response()->json([
            'message' => 'Search user successfully',
            'user' => $user
        ]);
    }

>>>>>>> 195f64e2ed9fe1656130c6108f720742a004e1de
}
