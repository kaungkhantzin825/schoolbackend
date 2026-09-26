<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Public: register a verifier organization and provision its account.
     *
     * The email must not already belong to a user — otherwise this public,
     * unauthenticated endpoint could be used to hijack an existing account
     * (including an admin's) by having it reassigned and a token issued.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'confirm_email' => 'required|same:email',
            'organization_name' => 'required|string|max:255',
            'organization_type' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'email_verified' => 'nullable|boolean',
            'agreed_terms' => 'accepted',
            'agreed_privacy' => 'accepted',
        ], [
            'email.unique' => 'An account with this email address already exists. Please sign in instead.',
        ]);

        $registration = RegistrationRequest::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'organization_name' => $validated['organization_name'],
            'organization_type' => $validated['organization_type'],
            'country' => $validated['country'],
            'email_verified' => (bool) ($validated['email_verified'] ?? false),
            'agreed_terms' => true,
            'agreed_privacy' => true,
            'status' => 'approved',
        ]);

        // Provision the verifier account. The password is random (never a
        // shared default) and is emailed so they can sign in again later —
        // the registration form itself never collects one.
        $plainPassword = Str::password(12);

        $user = User::create([
            'name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'role' => 'verifier',
        ]);

        $this->sendCredentialsEmail($registration, $plainPassword);

        $token = $user->createToken('verifier_auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful! Your verifier account is ready.',
            'registration' => $registration,
            'user' => $user,
            'token' => $token,
            'redirect' => '/verifier/dashboard',
        ], 201);
    }

    /**
     * Protected: list registration requests (Super Admin review queue).
     */
    public function index(Request $request)
    {
        $query = RegistrationRequest::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->orderByDesc('created_at')
                ->paginate(min((int) $request->input('per_page', 50), 200))
        );
    }

    /**
     * Protected: approve / reject a registration request.
     */
    public function update(Request $request, RegistrationRequest $registrationRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'review_notes' => 'nullable|string',
        ]);

        $registrationRequest->update($validated);

        if ($validated['status'] === 'approved') {
            $this->provisionVerifierAccount($registrationRequest);
        }

        return response()->json($registrationRequest->fresh());
    }

    /**
     * Create (or reuse) the verifier's login and email them their credentials
     * plus a link to the Verifier Dashboard.
     */
    private function provisionVerifierAccount(RegistrationRequest $registrationRequest): void
    {
        $user = User::where('email', $registrationRequest->email)->first();
        $plainPassword = null;

        if (!$user) {
            $plainPassword = Str::password(12);
            User::create([
                'name' => $registrationRequest->full_name,
                'email' => $registrationRequest->email,
                'password' => Hash::make($plainPassword),
                'role' => 'verifier',
            ]);
        }

        $this->sendCredentialsEmail($registrationRequest, $plainPassword);
    }

    /**
     * Email the verifier their login details. Never throws — provisioning
     * must still succeed when SMTP is unreachable.
     */
    private function sendCredentialsEmail(RegistrationRequest $registrationRequest, ?string $plainPassword): void
    {
        $loginUrl = rtrim(config('app.frontend_url'), '/') . '/login';
        $body = $this->credentialsEmailHtml($registrationRequest, $plainPassword, $loginUrl);

        try {
            Mail::html($body, function ($message) use ($registrationRequest) {
                $message->to($registrationRequest->email, $registrationRequest->full_name)
                    ->subject('Your MAVER Verifier account is ready');
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function credentialsEmailHtml(RegistrationRequest $registrationRequest, ?string $plainPassword, string $loginUrl): string
    {
        $passwordLine = $plainPassword
            ? "<p><strong>Temporary password:</strong> {$plainPassword}<br><span style=\"color:#64748b;font-size:13px\">Please change it after logging in.</span></p>"
            : "<p>Use your existing MAVER password to log in.</p>";

        return <<<HTML
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto">
                <h2 style="color:#163172">Welcome to MAVER</h2>
                <p>Hi {$registrationRequest->full_name},</p>
                <p>Your verifier registration for <strong>{$registrationRequest->organization_name}</strong> has been approved.</p>
                <p><strong>Login email:</strong> {$registrationRequest->email}</p>
                {$passwordLine}
                <p><a href="{$loginUrl}" style="background:#1d4ed8;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block">Go to Login</a></p>
                <p style="color:#94a3b8;font-size:12px">Myanmar Academic Verification &amp; Educational Registry</p>
            </div>
        HTML;
    }
}
