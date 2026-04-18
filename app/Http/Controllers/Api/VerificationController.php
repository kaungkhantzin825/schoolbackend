<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\VerificationLog;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            'graduate_name' => 'required|string',
            'father_name' => 'required|string',
            'degree' => 'required|string',
            'graduation_year' => 'required|integer',
            'verifier_name' => 'nullable|string',
            'verifier_email' => 'nullable|email',
            'organization_type' => 'nullable|string',
            'organization_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Search for student
        $student = Student::where('university_id', $request->university_id)
            ->where('graduate_name', 'like', '%' . $request->graduate_name . '%')
            ->where('father_name', 'like', '%' . $request->father_name . '%')
            ->where('degree', 'like', '%' . $request->degree . '%')
            ->where('graduation_year', $request->graduation_year)
            ->first();

        $result = $student ? 'verified' : 'not_found';
        $status = $student ? 'success' : 'failed';

        // Log the verification attempt
        $log = VerificationLog::create([
            'university_id' => $request->university_id,
            'student_id' => $student?->id,
            'verifier_name' => $request->verifier_name ?? 'Anonymous',
            'verifier_email' => $request->verifier_email,
            'organization_type' => $request->organization_type ?? 'Unknown',
            'organization_name' => $request->organization_name ?? 'Unknown',
            'searched_name' => $request->graduate_name,
            'searched_father_name' => $request->father_name,
            'searched_degree' => $request->degree,
            'searched_year' => $request->graduation_year,
            'result' => $result,
            'status' => $status,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'verified' => (bool) $student,
            'result' => $result,
            'status' => $status,
            'student' => $student ? $student->load('university') : null,
            'log_id' => $log->id,
        ]);
    }

    public function logs(Request $request)
    {
        $query = VerificationLog::with(['university', 'student']);

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

        if ($request->has('result')) {
            $query->where('result', $request->result);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($logs);
    }

    public function recentActivity(Request $request)
    {
        $query = VerificationLog::with(['university', 'student']);

        if ($request->user() && $request->user()->isUniversityAdmin()) {
            $query->where('university_id', $request->user()->university_id);
        }

        $activities = $query->orderBy('created_at', 'desc')->limit(10)->get();

        return response()->json($activities);
    }
}
