<?php

namespace App\Http\Controllers\api\v1;
use App\Mail\ForgotPasswordMail;
use App\Mail\PasswordResetSuccess;
use App\Mail\ResendNewCode;
use App\Mail\SendCode;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;

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

            // Create user
            $verification_code = rand(100000, 999999);
            $expire = Carbon::now()->addMinutes(10);

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'verification_code' => $verification_code,
                'verification_code_expire' => $expire,
                'is_verified' => false,
                'role' => 'customer'
            ]);
            try {
                Mail::to($request->email)->send(new SendCode($verification_code));
            } catch (\Exception $mailError) {
                \Log::error("Failed to send verification email: " . $mailError->getMessage());
            }
            return response()->json([
                'message' => 'Account created successfully. Verification code sent to email if possible.',
                'user_id' => $user->id
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
            // 1. Validate input
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            // 2. Check if user exists
            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                return response()->json([
                    'errors' => [
                        'email' => 'No account found with this email.'
                    ]
                ], 404);
            }

            // 3. Check if user email is verified
            if (!$user->is_verified) {
                return response()->json([
                    'errors' => [
                        'email' => 'Please verify your email before logging in.'
                    ]
                ], 403);
            }

            // 4. Verify password
            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'errors' => [
                        'password' => 'Incorrect password.'
                    ]
                ], 401);
            }

            // 5. Delete old tokens
            $user->tokens()->delete();

            // 6. Create new token
            $token = $user->createToken('api-token')->plainTextToken;

            // 7. Return response
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token
            ], 200);

        } catch (ValidationException $e) {
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
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate token using Laravel Password broker
        $token = Password::createToken($user);

        // Build reset link for API/frontend
        $link = url('/reset-password?token=' . $token . '&email=' . urlencode($request->email));

        // Send email with token
        Mail::to($request->email)->send(new ForgotPasswordMail($token, $link));

        // Log the link for development/debugging
        \Log::info('Password reset link: ' . $link);

        return response()->json([
            'message' => 'Password reset link has been sent to your email.',
            'link_in_log' => true,
        ], 200);
    }

    // Reset the password using token
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = Password::broker('users')->reset(
            $credentials,
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                Mail::to($user->email)->send(new PasswordResetSuccess($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password reset successfully'], 200);
        }

        return response()->json(['message' => 'Invalid token or email'], 400);
    }
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        if (!$user->verification_code || !$user->verification_code_expire) {
            return response()->json(['message' => 'Verification code not found'], 400);
        }

        if ($user->verification_code != $request->verification_code) {
            return response()->json(['message' => 'Invalid verification code'], 400);
        }

        if (now()->greaterThan($user->verification_code_expire)) {
            return response()->json(['message' => 'Verification code expired'], 400);
        }

        $user->is_verified = true;
        $user->verification_code = null;
        $user->verification_code_expire = null;
        $user->email_verified_at = now();
        $user->save();
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json([
            'message' => 'Email verified successfully',
            'user' => $user,
            'token' => $token
        ]);
    }
    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $code = rand(100000, 999999);

        $user->verification_code = $code;
        $user->verification_code_expire = now()->addMinutes(10);
        $user->save();


        Mail::to($request->email)->send(new ResendNewCode($code));
        return response()->json([
            'message' => 'New verification code sent'
        ]);
    }
}
