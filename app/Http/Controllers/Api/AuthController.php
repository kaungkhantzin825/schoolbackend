<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Email a password reset link.
     *
     * Always answers with the same message whether or not the address exists —
     * a different response would turn this into an account-enumeration oracle.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $generic = [
            'message' => 'If an account exists for that email, a reset link is on its way.',
        ];

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json($generic);
        }

        try {
            $token = Password::broker()->createToken($user);
            Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
        } catch (\Throwable $e) {
            // Never report success when the mail did not actually leave —
            // the user would sit waiting for a link that is never coming.
            report($e);

            return response()->json([
                'message' => 'We could not send the reset email right now. Please try again shortly.',
            ], 500);
        }

        return response()->json($generic);
    }

    /**
     * Consume a reset token and set the new password.
     *
     * All existing Sanctum tokens are revoked: they were minted while the old
     * password was in force, and a reset is often the response to a compromise.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Your password has been reset. You can sign in now.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'This reset link is invalid or has expired. Please request a new one.',
            Password::INVALID_USER => 'We could not find an account with that email address.',
            default => 'Unable to reset your password. Please request a new link.',
        };

        return response()->json(['message' => $message], 422);
    }

    /**
     * Let a signed-in user replace their password.
     *
     * Verifier accounts are provisioned with a generated password that is
     * emailed to them, so they need a way to set their own.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Invalidate every other session; keep the one making the change.
        $currentId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('university'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('university'));
    }
}
