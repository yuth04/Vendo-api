<?php

namespace App\Http\Controllers\api\v1\Auth;

use App\Mail\GoogleWelcomeMail;
use App\Services\SystemLogService;
use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
class GoogleAuthController extends Controller
{


    public function __construct(protected SystemLogService $systemLogService)
    {

    }
    //  Redirect to Google
    public function redirectGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // Google API response
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();
            if ($user && $user->status === 'inactive') {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact support.',
                ], 403);
            }

            if (!$user) {
                // Create new user
                $user = User::create([
                    'first_name' => $googleUser->user['given_name'],
                    'last_name' => $googleUser->user['family_name'],
                    'email' => $googleUser->email,
                    'password' => bcrypt(Str::random(16)),
                    'google_id' => $googleUser->id,
                    'image' => $googleUser->user['picture'] ?? $googleUser->avatar ?? null,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'role' => 'customer',
                    'created_by' => [
                        'first_name' => $googleUser->user['given_name'],
                        'last_name' => $googleUser->user['family_name'],
                        'email' => $googleUser->email,
                        'image' => $googleUser->user['picture'] ?? $googleUser->avatar ?? null,
                        'created_at' => now()->format('d-m-Y h:i:s A'),
                    ],
                    'updated_by' => [
                        'first_name' => $googleUser->user['given_name'],
                        'last_name' => $googleUser->user['family_name'],
                        'email' => $googleUser->email,
                        'image' => $googleUser->user['picture'] ?? $googleUser->avatar ?? null,
                        'created_at' => now()
                    ],
                ]);
                // Audit Log: Google Registration
                $this->systemLogService->log(
                    action: 'google_register',
                    module: 'Auth',
                    description: "User registered via Google OAuth: {$user->email}",
                    entity: $user,
                    oldValues: null,
                    newValues: $user->toArray()
                );
                // Mail::to($user->email)->send(new GoogleWelcomeMail($user));
            } else {
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'is_verified' => true,
                        'email_verified_at' => now()->format('d-m-Y h:i:s A'),
                        'image' => $user->image ?? ($googleUser->avatar ?? $googleUser->user['picture'] ?? null),
                        'last_login_at' => now()
                    ]);
                }
                // Audit Log: Google Login
                $this->systemLogService->log(
                    action: 'google_login',
                    module: 'Auth',
                    description: "User logged in via Google OAuth: {$user->email}",
                    entity: $user
                );
            }

            // Delete old tokens
            // $user->tokens()->delete();
            $device = request()->userAgent() ?? 'google-login';
            $user->tokens()
                ->where('name', $device)
                ->delete();

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Redirect to frontend with token
            return redirect(
                config('app.frontend_url') .
                '/auth/callback?token=' . $token
            );

        } catch (\Throwable $e) {
            return redirect(
                config('app.frontend_url') .
                '/auth/login?error=google_failed'
            );
        }
    }
}