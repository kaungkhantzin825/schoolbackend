<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /** Hard ceiling so one request can never ask for the whole table. */
    private const MAX_PER_PAGE = 100;

    /**
     * A university admin may only ever touch their own university's records.
     * Returns null when allowed, or a 403 response when not.
     */
    private function denyIfForeign(Request $request, int $universityId)
    {
        $user = $request->user();

        if ($user && $user->isUniversityAdmin() && $user->university_id !== $universityId) {
            return response()->json([
                'message' => 'This record belongs to another university.',
            ], 403);
        }

        return null;
    }

    /** The university a write should be attributed to, ignoring client input for tenants. */
    private function resolveUniversityId(Request $request): ?int
    {
        $user = $request->user();

        return $user && $user->isUniversityAdmin()
            ? $user->university_id
            : $request->integer('university_id');
    }

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

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('graduate_name', 'like', "%{$search}%")
                  ->orWhere('nrc_number', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('degree', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), self::MAX_PER_PAGE);
        $students = $query->orderByDesc('id')->paginate($perPage);

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

        $universityId = $this->resolveUniversityId($request);

        if ($deny = $this->denyIfForeign($request, (int) $universityId)) {
            return $deny;
        }

        $student = Student::create(array_merge(
            $request->only([
                'graduate_name', 'father_name', 'gender', 'date_of_birth', 'nrc_number',
                'student_id', 'degree', 'specialization', 'graduation_year', 'photo_url',
            ]),
            ['university_id' => $universityId],
        ));

        return response()->json($student->load('university'), 201);
    }

    public function show(Request $request, Student $student)
    {
        if ($deny = $this->denyIfForeign($request, $student->university_id)) {
            return $deny;
        }

        return response()->json($student->load('university'));
    }

    public function update(Request $request, Student $student)
    {
        if ($deny = $this->denyIfForeign($request, $student->university_id)) {
            return $deny;
        }

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

        // Never take university_id from the request — a tenant could otherwise
        // move a record into (or out of) another university.
        $student->update($request->only([
            'graduate_name', 'father_name', 'gender', 'date_of_birth', 'nrc_number',
            'student_id', 'degree', 'specialization', 'graduation_year', 'photo_url',
        ]));

        return response()->json($student->load('university'));
    }

    public function destroy(Request $request, Student $student)
    {
        if ($deny = $this->denyIfForeign($request, $student->university_id)) {
            return $deny;
        }

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
        $directory = public_path('uploads/student-photos');

        // On Linux hosting this directory often doesn't exist after a deploy,
        // or isn't writable by the web user. Report that clearly instead of
        // letting it surface as an opaque 500.
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return response()->json([
                'message' => 'Upload folder could not be created on the server. Create "public/uploads/student-photos" and make it writable (chmod 775).',
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
                'message' => 'The photo could not be saved on the server. Please check the upload folder permissions.',
            ], 500);
        }

        return response()->json([
            'url' => rtrim(config('app.url'), '/') . '/uploads/student-photos/' . $name,
        ]);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            // Capped: an unbounded array would hold a DB transaction open for
            // minutes and exhaust memory under concurrent uploads.
            'students' => 'required|array|max:2000',
            'students.*.graduate_name' => 'required|string|max:255',
            'students.*.father_name' => 'required|string|max:255',
            'students.*.gender' => 'required|in:Male,Female,Other',
            'students.*.date_of_birth' => 'required|date',
            'students.*.nrc_number' => 'required|string|max:100',
            'students.*.degree' => 'required|string|max:255',
            'students.*.graduation_year' => 'required|integer|min:1900|max:' . (date('Y') + 10),
        ]);

        $universityId = $this->resolveUniversityId($request);

        if ($deny = $this->denyIfForeign($request, (int) $universityId)) {
            return $deny;
        }

        $fields = [
            'graduate_name', 'father_name', 'gender', 'date_of_birth', 'nrc_number',
            'student_id', 'degree', 'specialization', 'graduation_year', 'photo_url',
        ];

        $inserted = 0;
        $errors = [];

        foreach ($request->students as $index => $studentData) {
            // Each row commits independently: one bad NRC should not silently
            // discard the whole file, and a single long transaction blocks
            // other writers.
            try {
                Student::create(array_merge(
                    array_intersect_key($studentData, array_flip($fields)),
                    ['university_id' => $universityId],
                ));
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'name' => $studentData['graduate_name'] ?? null,
                    // Don't echo raw SQL back to the client.
                    'error' => str_contains($e->getMessage(), 'Duplicate entry')
                        ? 'A record with this NRC number already exists.'
                        : 'Could not save this row. Please check the values.',
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk upload completed',
            'inserted' => $inserted,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 50),
        ]);
    }
}
