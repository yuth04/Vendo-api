<?php

namespace App\Http\Controllers\api\v1\Auth;
use App\Mail\ForgotPasswordMail;
use App\Mail\LoginNotificationMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetSuccess;
use App\Mail\ResendNewCode;
use App\Mail\SendCode;
use App\Mail\WelcomeUserMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Services\CartService;
class AuthController extends Controller
{

    private function verifyTurnstile(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $captcha = Http::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
            'response' => $request->captcha_token,
            'remoteip' => $request->ip(),
        ]);
        \Log::info('Turnstile response:', $captcha->json());
        return $captcha->json('success') === true;
    }
    public function register(Request $request)
    {
        // Verify clouldFlare
        $request->validate(['captcha_token' => 'required|string']);
        if (!$this->verifyTurnstile($request)) {
            return response()->json(['message' => 'CAPTCHA verification failed'], 422);
        }
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
                'role' => 'customer',
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
        $request->validate(['captcha_token' => 'required|string']);
        if (!$this->verifyTurnstile($request)) {
            return response()->json(['message' => 'CAPTCHA verification failed'], 422);
        }
        try {


            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'errors' => ['email' => 'Invalid email or password']
                ], 401);
            }

            $user = Auth::user();
            if ($user->status === 'inactive') {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact admin.'
                ], 403);
            }
            // 2. Check verification
            if (!$user->is_verified) {
                return response()->json([
                    'errors' => ['email' => 'Please verify your email before logging in.']
                ], 403);
            }
            // Create token (Sanctum)
            // $user->tokens()->delete();
            $request->user()?->currentAccessToken()?->delete();

            $token = $user->createToken('api-token')->plainTextToken;
            Mail::to($user->email)->send(new LoginNotificationMail($user));
            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'image' => $user->image,
                    'is_verified' => $user->is_verified,
                    'token' => $token,
                ]

            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
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
    // change password     'token' => $token,
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
        Mail::to($user->email)->send(new PasswordChangedMail($user));
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
    // reset password
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
    // verify email for create account
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
            'email_verified_at' => now(),
            'created_by' => null,
            'updated_by' => null,
        ]);
        $auditInfo = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'image' => $user->image,
            'created_at' => now()->format('d-m-Y g:i:s A'),
        ];
        $user->update([
            'created_by' => $auditInfo,
            'updated_by' => $auditInfo,
        ]);
        Cache::forget('register_' . $request->email);
        Mail::to($user->email)->send(new WelcomeUserMail($user));
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
