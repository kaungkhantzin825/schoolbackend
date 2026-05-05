<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Degree;
use Illuminate\Http\Request;

class DegreeController extends Controller
{
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

        $degree = Degree::create($request->all());

        return response()->json($degree->load('university'), 201);
    }

    public function show(Degree $degree)
    {
        return response()->json($degree->load('university'));
    }

    public function update(Request $request, Degree $degree)
    {
        $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'sometimes|required|in:bachelor,master,doctorate',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $degree->update($request->all());

        return response()->json($degree->load('university'));
    }

    public function destroy(Degree $degree)
    {
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
