<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Degree;
use Illuminate\Http\Request;

class DegreeController extends Controller
{
    /** A university admin may only manage their own university's degrees. */
    private function denyIfForeign(Request $request, int $universityId)
    {
        $user = $request->user();

        if ($user && $user->isUniversityAdmin() && $user->university_id !== $universityId) {
            return response()->json([
                'message' => 'This degree belongs to another university.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = Degree::with('university');

        // Filter by university if user is university admin
        if ($request->user() && $request->user()->isUniversityAdmin()) {
            $query->where('university_id', $request->user()->university_id);
        }

        if ($request->has('university_id')) {
            $query->where('university_id', $request->university_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $degrees = $query->get();

        return response()->json($degrees);
    }

    public function store(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|in:bachelor,master,doctorate',
            'status' => 'required|in:active,inactive',
        ]);

        // Tenants always write to their own university, whatever they posted.
        $user = $request->user();
        $universityId = $user && $user->isUniversityAdmin()
            ? $user->university_id
            : $request->integer('university_id');

        if ($deny = $this->denyIfForeign($request, (int) $universityId)) {
            return $deny;
        }

        $degree = Degree::create(array_merge(
            $request->only(['code', 'name', 'description', 'level', 'status']),
            ['university_id' => $universityId],
        ));

        return response()->json($degree->load('university'), 201);
    }

    public function show(Request $request, Degree $degree)
    {
        if ($deny = $this->denyIfForeign($request, $degree->university_id)) {
            return $deny;
        }

        return response()->json($degree->load('university'));
    }

    public function update(Request $request, Degree $degree)
    {
        if ($deny = $this->denyIfForeign($request, $degree->university_id)) {
            return $deny;
        }

        $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'sometimes|required|in:bachelor,master,doctorate',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $degree->update($request->only(['code', 'name', 'description', 'level', 'status']));

        return response()->json($degree->load('university'));
    }

    public function destroy(Request $request, Degree $degree)
    {
        if ($deny = $this->denyIfForeign($request, $degree->university_id)) {
            return $deny;
        }

        $degree->delete();

        return response()->json(['message' => 'Degree deleted successfully']);
    }

    /**
     * Public: Get all active degrees for a specific university.
     * Used by the public verification form (no auth required).
     */
    public function byUniversity($universityId)
    {
        $degrees = Degree::where('university_id', $universityId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'level']);

        return response()->json($degrees);
    }
}
