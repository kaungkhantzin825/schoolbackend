<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with('university');

        // Filter by university if user is university admin
        if ($request->user()->isUniversityAdmin()) {
            $query->where('university_id', $request->user()->university_id);
        }

        if ($request->has('university_id')) {
            $query->where('university_id', $request->university_id);
        }

        $students = $query->paginate(20);

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            'graduate_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female,Other',
            'date_of_birth' => 'required|date',
            'nrc_number' => 'required|string|unique:students,nrc_number',
            'student_id' => 'nullable|string',
            'degree' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'graduation_year' => 'required|integer|min:1900|max:' . (date('Y') + 10),
            'photo_url' => 'nullable|url',
        ]);

        $student = Student::create($request->all());

        return response()->json($student->load('university'), 201);
    }

    public function show(Student $student)
    {
        return response()->json($student->load('university'));
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'graduate_name' => 'sometimes|required|string|max:255',
            'father_name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|in:Male,Female,Other',
            'date_of_birth' => 'sometimes|required|date',
            'nrc_number' => 'sometimes|required|string|unique:students,nrc_number,' . $student->id,
            'student_id' => 'nullable|string',
            'degree' => 'sometimes|required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'graduation_year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 10),
            'photo_url' => 'nullable|url',
        ]);

        $student->update($request->all());

        return response()->json($student->load('university'));
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Student deleted successfully']);
    }

    /**
     * Upload a student photo and return its public URL.
     * Stored under public/uploads/student-photos (no storage:link required).
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $file = $request->file('photo');
        $name = 'stu_' . uniqid() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path('uploads/student-photos'), $name);

        return response()->json([
            'url' => rtrim(config('app.url'), '/') . '/uploads/student-photos/' . $name,
        ]);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            'students' => 'required|array',
            'students.*.graduate_name' => 'required|string',
            'students.*.father_name' => 'required|string',
            'students.*.gender' => 'required|in:Male,Female,Other',
            'students.*.date_of_birth' => 'required|date',
            'students.*.nrc_number' => 'required|string',
            'students.*.degree' => 'required|string',
            'students.*.graduation_year' => 'required|integer',
        ]);

        $inserted = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->students as $index => $studentData) {
                try {
                    $studentData['university_id'] = $request->university_id;
                    Student::create($studentData);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Bulk upload failed', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Bulk upload completed',
            'inserted' => $inserted,
            'errors' => $errors,
        ]);
    }
}
