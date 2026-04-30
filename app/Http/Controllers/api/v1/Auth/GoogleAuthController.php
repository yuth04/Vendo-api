<?php

namespace App\Http\Controllers\api\v1\Auth;

use App\Mail\GoogleWelcomeMail;
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
                return redirect(config('app.frontend_url') . '/auth/login?error=inactive');
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
                    'email_verified_at' => now()->format('d-m-Y h:i:s A'),
                    'role' => 'customer',
                    'created_at'=>now()->format('d-m-Y h:i:s A')
                ]);
                Mail::to($user->email)->send(new GoogleWelcomeMail($user));
            } else {
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'is_verified' => true,
                        'email_verified_at' => now(),
                        'image' => $user->image ?? ($googleUser->avatar ?? $googleUser->user['picture'] ?? null),
                    ]);
                }
            }

            // Delete old tokens
            $user->tokens()->delete();

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