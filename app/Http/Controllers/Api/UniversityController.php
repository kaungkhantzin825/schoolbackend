<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\Request;

class UniversityController extends Controller
{
    public function index(Request $request)
    {
        $query = University::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $universities = $query->withCount('students')->get();

        return response()->json($universities);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/uploads\/)/'],
            'status' => 'required|in:active,inactive',
            'verification_notice' => 'nullable|string',
        ]);

        $university = University::create($request->all());

        return response()->json($university, 201);
    }

    /**
     * Upload a university logo and return its path.
     *
     * Logos used to be pasted in as external URLs (Wikipedia, etc.), which
     * silently broke: those hosts block hot-linking from other domains, so
     * the browser got a 403 and the page fell back to a placeholder.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,jpg,png,webp,svg|max:2048',
        ]);

        $file = $request->file('logo');
        $name = 'uni_' . uniqid() . '.' . strtolower($file->getClientOriginalExtension());
        $directory = public_path('uploads/university-logos');

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return response()->json([
                'message' => 'Upload folder could not be created on the server. Create "public/uploads/university-logos" and make it writable (chmod 775).',
            ], 500);
        }

        if (!is_writable($directory)) {
            return response()->json([
                'message' => 'Upload folder is not writable on the server. Run: chmod -R 775 public/uploads',
            ], 500);
        }

        try {
            $file->move($directory, $name);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The logo could not be saved on the server. Please check the upload folder permissions.',
            ], 500);
        }

        return response()->json(['url' => '/uploads/university-logos/' . $name]);
    }

    /**
     * Public university profile.
     *
     * Must NOT include the student list: this endpoint is unauthenticated and
     * every student row carries an NRC number and date of birth. It also
     * returned the entire cohort on every page load.
     */
    public function show(University $university)
    {
        return response()->json($university->loadCount('students'));
    }

    public function update(Request $request, University $university)
    {
        // Enforce role authorization
        if ($request->user() && $request->user()->isUniversityAdmin() && $request->user()->university_id !== $university->id) {
            return response()->json(['message' => 'Forbidden: You can only edit your own university.'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/uploads\/)/'],
            'status' => 'sometimes|required|in:active,inactive',
            'verification_notice' => 'nullable|string',
        ]);

        $university->update($request->all());

        return response()->json($university);
    }

    public function destroy(University $university)
    {
        $university->delete();

        return response()->json(['message' => 'University deleted successfully']);
    }

    public function search(Request $request)
    {
        $search = $request->input('q', '');

        $universities = University::where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'location', 'logo_url', 'description', 'verification_notice']);

        return response()->json($universities);
    }

    public function stats()
    {
        $stats = [
            'total_universities' => University::count(),
            'active_universities' => University::where('status', 'active')->count(),
            'total_students' => \App\Models\Student::count(),
            'total_verifications' => \App\Models\VerificationLog::count(),
            'success_rate' => $this->calculateSuccessRate(),
        ];

        return response()->json($stats);
    }

    private function calculateSuccessRate()
    {
        $total = \App\Models\VerificationLog::count();
        if ($total === 0) return 0;

        $successful = \App\Models\VerificationLog::where('status', 'success')->count();
        return round(($successful / $total) * 100, 2);
    }
}
