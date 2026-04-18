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
            'logo_url' => 'nullable|url',
            'status' => 'required|in:active,inactive',
        ]);

        $university = University::create($request->all());

        return response()->json($university, 201);
    }

    public function show(University $university)
    {
        return response()->json($university->load('students'));
    }

    public function update(Request $request, University $university)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'status' => 'sometimes|required|in:active,inactive',
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
            ->get(['id', 'name', 'location', 'logo_url']);

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
