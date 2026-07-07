<?php

namespace App\Http\Controllers\api\v1\Auth;
use App\Mail\ForgotPasswordMail;
use App\Mail\LoginNotificationMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetSuccess;
use App\Mail\ResendNewCode;
use App\Mail\SendCode;
use App\Mail\WelcomeUserMail;
use App\Models\LoginHistory;
use App\Models\Notification;
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
use App\Traits\NotifiesUsers;

class AuthController extends Controller
{
    use NotifiesUsers;
    private function verifyTurnstile(Request $request): bool
    {

        if (app()->environment('local')) {
            return true;
        }
        if ($request->captcha_token === env('POSTMAN_TEST_TOKEN')) {
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
        $request->validate(['captcha_token' => 'required|string']);
        if (!$this->verifyTurnstile($request)) {
            return response()->json(['message' => 'CAPTCHA verification failed'], 422);
        }
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
            ], [
                'password.min' => 'Password must be at least 8 characters.',
                'password.confirmed' => 'Password confirmation does not match.'
            ]);

            $verification_code = random_int(100000, 999999);
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

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
            //  Check verification
            if (!$user->is_verified) {
                return response()->json([
                    'errors' => ['email' => 'Please verify your email before logging in.']
                ], 403);
            }
            // Create token (Sanctum)
            // $user->tokens()->delete();
            // $request->user()?->currentAccessToken()?->delete();

            $token = $user->createToken('api-token')->plainTextToken;
            $this->checkNewLogin($user, $request);
            $this->initializeNotification(
                $user,
                'Welcome Back!',
                'Welcome back, ' . $user->first_name . '! Good to see you again.',
                'security',
                'user',
                '/profile'
            );

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // check new login and save login history
    private function checkNewLogin($user, Request $request)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $device = $this->parseDevice($userAgent);
        $location = $this->parseLocation($ip);

        $login = LoginHistory::firstOrCreate(
            [
                'user_id' => $user->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ],
            [
                'device' => $device,
                'location' => $location,
                'last_seen_at' => now(),
            ]
        );

        if ($login->wasRecentlyCreated) {
            $isFirstLogin = LoginHistory::where('user_id', $user->id)->count() === 1;

            if (!$isFirstLogin) {
                $this->initializeLoginNotification($user, $ip, $device, $location);
            }
        } else {
            $login->update(['last_seen_at' => now()]);
        }
    }

    private function parseDevice(?string $userAgent): ?string
    {
        if (!$userAgent)
            return 'Unknown device';

        if (str_contains($userAgent, 'Mobile'))
            return 'Mobile device';
        if (str_contains($userAgent, 'Windows'))
            return 'Windows PC';
        if (str_contains($userAgent, 'Macintosh'))
            return 'Mac';
        if (str_contains($userAgent, 'Linux'))
            return 'Linux PC';

        return 'Unknown device';
    }

    private function parseLocation(?string $ip): ?string
    {
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return 'Localhost';
        }

        // Placeholder — wire up a geoip package for real city/country lookup
        return null;
    }

    private function initializeLoginNotification($user, $ip, $device, $location)
    {
        $locationText = $location ? " from {$location}" : '';

        return Notification::create([
            'user_id' => $user->id,
            'title' => 'New Login Detected',
            'message' => "A new login was detected{$device}{$locationText} (IP: {$ip}).",
            'type' => 'security',
            'icon' => 'shield-alert',
            'url' => '/account/security',
            'is_read' => false,
        ]);
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
        $this->initializeNotification(
            $user,
            'Password Changed',
            'Your password was changed on ' . now()->format('M d, Y h:i A') . '. If this wasn\'t you, contact support immediately.',
            'security',
            'lock',
            '/profile'
        );
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
        $this->initializeNotification(
            $user,
            'Password Reset Successful',
            'Your password was reset on ' . now()->format('M d, Y h:i A') . '. If this wasn\'t you, contact support immediately.',
            'security',
            'lock',
            '/profile'
        );
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
            'verification_code' => 'required|string'
        ]);

        $data = Cache::get('register_' . $request->email);

        if (!$data) {
            return response()->json([
                'message' => 'Verification expired. Please register again.'
            ], 400);
        }

        if (now()->gt($data['verification_code_expire'])) {
            Cache::forget('register_' . $request->email);

            return response()->json([
                'message' => 'Verification code expired. Please request a new one.'
            ], 400);
        }

        if ((string) $data['verification_code'] !== (string) $request->verification_code) {
            return response()->json([
                'message' => 'Invalid verification code.'
            ], 400);
        }

        try {
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

            $this->initializeNotification(
                $user,
                'Welcome, ' . $user->first_name . ' to Vendo Store!',
                'Thanks for joining us. Happy shopping!',
                'security',
                'user',
                '/profile'
            );

            Mail::to($user->email)->send(new WelcomeUserMail($user));

            return response()->json([
                'message' => 'Account verified successfully',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'image' => $user->image,
                    'is_verified' => $user->is_verified,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Account creation failed.',
                'error' => $e->getMessage()
            ], 500);
        }
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

        $newCode = random_int(100000, 999999);

        $data['verification_code'] = $newCode;

        // reset cache with new code
        Cache::put('register_' . $request->email, $data, now()->addMinutes(5));

        Mail::to($request->email)->send(new ResendNewCode($newCode));

        return response()->json([
            'message' => 'New verification code sent'
        ]);
    }
}
