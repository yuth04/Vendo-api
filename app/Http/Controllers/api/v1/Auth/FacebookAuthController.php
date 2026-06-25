<?php

namespace App\Http\Controllers\api\v1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class FacebookAuthController extends Controller
{
    public function redirectFacebook()
    {
        return Socialite::driver('facebook')
            ->stateless()
            ->scopes(['email', 'public_profile'])
            ->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            $user = User::where('facebook_id', $facebookUser->getId()) // ✅ fix 1: wherer → where
                ->orWhere('email', $facebookUser->getEmail())
                ->first();

            // Check if user is inactive
            if ($user && $user->status === 'inactive') {
                return redirect(config('app.frontend_url') . '/auth/login?error=inactive');
            }

            if (!$user) {
                // Split name
                $nameParts = explode(' ', $facebookUser->getName(), 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';

                // Create new user
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $facebookUser->getEmail(),
                    'password' => bcrypt(Str::random(16)), 
                    'facebook_id' => $facebookUser->getId(),
                    'image' => $facebookUser->getAvatar() ?? null,
                    'is_verified' => true,
                    'email_verified_at' => now()->format('d-m-Y h:i:s A'),
                    'role' => 'customer',
                    'created_by' => [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $facebookUser->getEmail(),
                        'image' => $facebookUser->getAvatar() ?? null,
                        'created_at' => now()->format('d-m-Y h:i:s A'),
                    ],
                    'updated_by' => [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $facebookUser->getEmail(),
                        'image' => $facebookUser->getAvatar() ?? null,
                        'created_at' => now()->format('d-m-Y h:i:s A'),
                    ],
                ]);

            } else {
                if (!$user->facebook_id) { // ✅ fix 2: !$user → !$user->facebook_id
                    $user->update([
                        'facebook_id' => $facebookUser->getId(),
                        'is_verified' => true,
                        'email_verified_at' => now(),
                        'image' => $user->image ?? $facebookUser->getAvatar() ?? null,
                    ]);
                }
            }

            // Delete old token for this device
            $device = request()->userAgent() ?? 'facebook-login';
            $user->tokens()
                ->where('name', $device)
                ->delete();

            // Create new token
            $token = $user->createToken('auth-token')->plainTextToken;

            return redirect(
                config('app.frontend_url') . '/auth/callback?token=' . $token
            );

        } catch (\Throwable $e) {
            return redirect(
                config('app.frontend_url') . '/auth/login?error=facebook_failed'
            );
        }
    }
}