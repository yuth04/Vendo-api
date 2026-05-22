<?php

namespace App\Http\Controllers\api\v1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Auth;
use Exception;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $adddress = Address::where('user_id', $user->id)->get();

        if ($adddress->isEmpty()) {
            return response()->json([
                'message' => 'Address is empty'
            ]);
        }
        return response()->json([
            'message' => 'Address retrieved successfully',
            'address' => $adddress
        ]);
    }
    // store
    public function store(Request $request)
    {

        try {
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'address_line' => 'required|string|max:500',
                'city' => 'required|string|max:255',
                'province' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'phone' => 'required|string|max:20',
                'is_default' => 'nullable|boolean',
            ]);

            $user = Auth::user();
            if ($request->is_default) {
                Address::where('user_id', $user->id)
                    ->update(['is_default' => false]);
            }
            $address = Address::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'address_line' => $request->address_line,
                'city' => $request->city,
                'province' => $request->province,
                'postal_code' => $request->postal_code,
                'phone' => $request->phone,
                'is_default' => $request->is_default

            ]);

            return response()->json([
                'message' => 'Address created successfully',
                'address' => $address
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create address',
                'error' => $e->getMessage()
            ], 500);
        }

    }
    // update
    public function update(Request $request, $id)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address_line' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        $address = Address::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        if ($request->is_default) {
            Address::where('user_id', $user->id)
                ->update(['is_default' => false]);
        }
        if (!$address) {
            return response()->json([
                'message' => 'Address not found'
            ], 404);
        }
        $address->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'address_line' => $request->address_line,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'phone' => $request->phone,
            'is_default' => $request->is_default ?? false
        ]);
        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $address
        ]);
    }
    // delete
    public function destroy($id)
    {
        $user = Auth::user();

        $address = Address::where('id', $id)
            ->where('user_id', $user->id)->first();
        if (!$address) {
            return response()->json([
                'message' => 'Address not found'
            ], 404);
        }
        $address->delete();
        return response()->json([
            'message' => 'Address deleted successfully'
        ]);

    }
    public function provinces()
    {
        $provinces = [
            "Phnom Penh",
            "Banteay Meanchey",
            "Battambang",
            "Kampong Cham",
            "Kampong Chhnang",
            "Kampong Speu",
            "Kampong Thom",
            "Kampot",
            "Kandal",
            "Koh Kong",
            "Kratie",
            "Mondulkiri",
            "Oddar Meanchey",
            "Pailin",
            "Preah Vihear",
            "Prey Veng",
            "Pursat",
            "Ratanakiri",
            "Siem Reap",
            "Preah Sihanouk",
            "Stung Treng",
            "Svay Rieng",
            "Takeo",
            "Tbong Khmum",
            "Kep"
        ];

        return response()->json($provinces);
    }
}
