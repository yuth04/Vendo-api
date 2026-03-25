<?php

namespace App\Http\Controllers\api\v1;
use App\Mail\ForgotPasswordMail;
use App\Mail\PasswordResetSuccess;
use App\Mail\ResendNewCode;
use App\Mail\SendCode;
use App\Models\Cart;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email:rfc,dns|unique:users,email',
                'password' => 'required|min:8|confirmed',
            ], [
                'password.min' => 'Password must be at least 8 characters.',
                'password.confirmed' => 'Password confirmation does not match.'
            ]);

            $verification_code = rand(100000, 999999);
            $expire = Carbon::now()->addMinutes(5);

            $user = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'verification_code' => $verification_code,
                'verification_code_expire' => $expire,
                'is_verified' => false,
                'role' => 'customer'
            ];

            // store in cache
            Cache::put('register_' . $validated['email'], $user, now()->addMinutes(10));

            // send email
            Mail::to($validated['email'])->send(new SendCode($verification_code));

            return response()->json([
                'message' => 'Verification code sent to email.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // login
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'local_cart' => 'nullable|array',
                'local_cart.*.product_variant_id' => 'nullable|exists:product_variants,id',
                'local_cart.*.quantity' => 'nullable|integer|min:1'
            ]);

            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                return response()->json([
                    'errors' => ['email' => 'No account found with this email.']
                ], 404);
            }

            if (!$user->is_verified) {
                return response()->json([
                    'errors' => ['email' => 'Please verify your email before logging in.']
                ], 403);
            }

            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'errors' => ['password' => 'Incorrect password.']
                ], 401);
            }

            // --- CART MERGE START ---

            $cartData = $this->mergeCart($user, $request->input('local_cart', []));
            // --- CART MERGE END ---

            $user->tokens()->delete();
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                // 'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'image', 'is_verified']),
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'image' => $user->image,
                'is_verified' => $user->is_verified,
                'token' => $token,
                'cart' => $cartData
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function mergeCart($user, $localCartItems)
    {
        return DB::transaction(function () use ($user, $localCartItems) {
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

            if (!empty($localCartItems)) {
                foreach ($localCartItems as $item) {
                    if (empty($item['product_variant_id']) || empty($item['quantity'])) {
                        continue;
                    }
                    $cartItem = $userCart->items()->firstOrNew([
                        'product_variant_id' => $item['product_variant_id']
                    ]);

                    // Sum the quantities
                    $cartItem->quantity += $item['quantity'];

                    // Optional: Stock check
                    // $variant = ProductVariant::find($item['product_variant_id']);
                    // if ($variant && $cartItem->quantity > $variant->stock) {
                    //     $cartItem->quantity = $variant->stock;
                    // }

                    $cartItem->save();
                }
            }

            // Load relations and format (using your standardized response logic)
            $userCart->load('items');

            return [
                'id' => $userCart->id,
                'items' => $userCart->items->map(function ($item) {
                    return [
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                    ];
                }),
            ];
        });
    }
    public function logout(Request $request)
    {
        if ($request->user()) {
            // revoke only current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        }

        return response()->json([
            'message' => 'No authenticated user found'
        ], 401);
    }
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
    // change password
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'min:8', 'confirmed'],
        ]);
        $user = $request->user();


        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password'
            ], 422);
        }
        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json([
            'message' => 'Password changed successfully',
        ]);

    }
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);
        $user = User::where('email', $request->email)->first();

        $otp = rand(100000, 999999);
        $expired = 5;

        $user->verification_code = $otp;
        $user->verification_code_expire = now()->addMinutes($expired);
        $user->save();

        Mail::to($user->email)->send(new ForgotPasswordMail($otp, $expired));
        return response()->json([
            'message' => 'Verification code sent to your email'
        ]);

    }
    // Verify otp
    public function verifycode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required'
        ]);
        $user = User::where('email', $request->email)
            ->where('verification_code', $request->code)
            ->first();
        if (!$user) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], 400);
        }
        if (now()->gt($user->verification_code_expire)) {
            return response()->json([
                'message' => 'Verification code expired'
            ], 400);

        }
        return response()->json([
            'message' => 'Code verified successfully'
        ]);
    }

    // Reset
    // public function resetPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //         'token' => 'required|string',
    //         'password' => 'required|string|min:8|confirmed',
    //     ]);

    //     $status = Password::broker('users')->reset(
    //         $request->only('email', 'password', 'password_confirmation', 'token'),
    //         function ($user, $password) {
    //             $user->password = Hash::make($password);
    //             $user->setRememberToken(Str::random(60));
    //             $user->save();

    //             Mail::to($user->email)->send(new PasswordResetSuccess($user));
    //         }
    //     );

    //     if ($status === Password::PASSWORD_RESET) {
    //         return response()->json([
    //             'message' => 'Password reset successfully'
    //         ], 200);
    //     }

    //     return response()->json([
    //         'message' => __($status)
    //     ], 400);
    // }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();

        $user->password = Hash::make($request->password);
        $user->verification_code = null;
        $user->verification_code_expire = null;
        $user->save();

        Mail::to($user->email)->send(new PasswordResetSuccess($user));
        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required'
        ]);

        $data = Cache::get('register_' . $request->email);

        if (!$data) {
            return response()->json([
                'message' => 'Verification expired'
            ], 400);
        }

        if (now()->gt($data['verification_code_expire'])) {
            Cache::forget('register_' . $request->email);

            return response()->json([
                'message' => 'Verification code expired'
            ], 400);
        }

        if ($data['verification_code'] != $request->verification_code) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], 400);
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'customer',
            'is_verified' => true,
            'email_verified_at' => now()
        ]);

        Cache::forget('register_' . $request->email);

        return response()->json([
            'message' => 'Account verified successfully',
            'user' => $user
        ]);
    }

    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $data = Cache::get('register_' . $request->email);

        if (!$data) {
            return response()->json([
                'message' => 'Verification session expired. Please register again.'
            ], 404);
        }

        $newCode = rand(100000, 999999);

        $data['verification_code'] = $newCode;

        // reset cache with new code
        Cache::put('register_' . $request->email, $data, now()->addMinutes(5));

        Mail::to($request->email)->send(new ResendNewCode($newCode));

        return response()->json([
            'message' => 'New verification code sent'
        ]);
    }
}
