<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    /**
     * Public: submit a verifier-organization registration request.
     * Stored with status "pending" for a Super Admin to review.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'confirm_email' => 'required|same:email',
            'organization_name' => 'required|string|max:255',
            'organization_type' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'email_verified' => 'nullable|boolean',
            'agreed_terms' => 'accepted',
            'agreed_privacy' => 'accepted',
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
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Registration submitted successfully. Our team will review your request and contact you by email.',
            'registration' => $registration,
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

        return response()->json($query->orderBy('created_at', 'desc')->get());
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

        return response()->json($registrationRequest);
    }
}
