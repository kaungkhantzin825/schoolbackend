<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
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
            'notes' => 'nullable|string|max:1000',
        ]);

        // Identify the verifier from their signed-in account rather than from
        // anything they type. The route requires authentication, so there is
        // always an actor — no more "Anonymous / Unknown" rows in the logs.
        $actor = $request->user();
        $organisation = RegistrationRequest::where('email', $actor->email)
            ->latest('id')
            ->first();

        // Search for student
        $student = Student::where('university_id', $request->university_id)
            ->where('graduate_name', 'like', '%' . $request->graduate_name . '%')
            ->where('father_name', 'like', '%' . $request->father_name . '%')
            ->where('degree', 'like', '%' . $request->degree . '%')
            ->where('graduation_year', $request->graduation_year)
            ->first();

        $slaDueAt = null;

        if ($student) {
            $result = 'verified';
            $status = 'success';
        } else {
            // Only the last 3 graduation years get an instant "not found" —
            // older/archived records go to manual registrar review instead
            // (mirrors each university's verification_notice wording).
            $instantFromYear = now()->year - 2;
            $isRecentYear = $request->graduation_year >= $instantFromYear;

            $result = 'not_found';
            $status = $isRecentYear ? 'failed' : 'pending';
            $slaDueAt = $isRecentYear ? null : now()->addDays(5);
        }

        // Log the verification attempt
        $log = VerificationLog::create([
            'university_id' => $request->university_id,
            'student_id' => $student?->id,
            'verifier_name' => $actor->name,
            'verifier_email' => $actor->email,
            'organization_type' => $organisation?->organization_type ?? '—',
            'organization_name' => $organisation?->organization_name ?? $actor->name,
            'searched_name' => $request->graduate_name,
            'searched_father_name' => $request->father_name,
            'searched_degree' => $request->degree,
            'searched_year' => $request->graduation_year,
            'result' => $result,
            'status' => $status,
            'sla_due_at' => $slaDueAt,
            'notes' => $request->notes,
        ]);

        if ($status === 'pending') {
            $log->update(['request_ref' => 'VR-' . str_pad((string) $log->id, 4, '0', STR_PAD_LEFT)]);
        }

        return response()->json([
            'verified' => (bool) $student,
            'result' => $result,
            'status' => $status,
            'pending' => $status === 'pending',
            'student' => $student ? $student->load('university') : null,
            'log_id' => $log->id,
            'request_ref' => $log->request_ref,
            'sla_due_at' => $log->sla_due_at,
        ]);
    }

    /**
     * Raise a verification request by uploading the certificate itself.
     *
     * This is the path for older, Myanmar-language degrees that cannot be
     * typed into the search form — the registrar reads the scan and decides.
     * It always lands as `pending`: there is nothing to match on until a
     * human opens the document, so it must never report an instant result.
     */
    public function submitWithDocument(Request $request)
    {
        $request->validate([
            'university_id' => 'required|exists:universities,id',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $actor = $request->user();
        $organisation = RegistrationRequest::where('email', $actor->email)
            ->latest('id')
            ->first();

        $file = $request->file('document');
        $directory = public_path('uploads/verification-documents');

        // Same guards as the photo upload: report an unusable upload folder
        // clearly instead of letting it surface as an opaque 500.
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return response()->json([
                'message' => 'Upload folder could not be created on the server. Create "public/uploads/verification-documents" and make it writable (chmod 775).',
            ], 500);
        }

        if (!is_writable($directory)) {
            return response()->json([
                'message' => 'Upload folder is not writable on the server. Run: chmod -R 775 public/uploads',
            ], 500);
        }

        // Extension is taken from the validated MIME type, not from whatever
        // the client called the file — the client-supplied name is untrusted.
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $name = 'vreq_' . uniqid() . '.' . $extension;

        try {
            $file->move($directory, $name);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The document could not be saved on the server. Please check the upload folder permissions.',
            ], 500);
        }

        $originalName = (string) $file->getClientOriginalName();

        // The queue's "Candidate Name" column is NOT NULL, and an upload-only
        // request has no typed name. The document's own filename is the most
        // meaningful label available — verifiers name scans after the graduate.
        $label = trim((string) pathinfo($originalName, PATHINFO_FILENAME));
        if ($label === '') {
            $label = 'Attached document';
        }

        $log = VerificationLog::create([
            'university_id' => $request->university_id,
            'student_id' => null,
            'verifier_name' => $actor->name,
            'verifier_email' => $actor->email,
            'organization_type' => $organisation?->organization_type ?? '—',
            'organization_name' => $organisation?->organization_name ?? $actor->name,
            'searched_name' => mb_substr($label, 0, 255),
            'searched_father_name' => null,
            'searched_degree' => null,
            'searched_year' => null,
            'result' => 'not_found',
            'status' => 'pending',
            'sla_due_at' => now()->addDays(5),
            'notes' => $request->notes,
            'document_url' => '/uploads/verification-documents/' . $name,
            'document_name' => mb_substr($originalName, 0, 255),
        ]);

        $log->update(['request_ref' => 'VR-' . str_pad((string) $log->id, 4, '0', STR_PAD_LEFT)]);

        return response()->json([
            'verified' => false,
            'result' => 'not_found',
            'status' => 'pending',
            'pending' => true,
            'student' => null,
            'log_id' => $log->id,
            'request_ref' => $log->request_ref,
            'sla_due_at' => $log->sla_due_at,
            'document_name' => $log->document_name,
        ], 201);
    }

    /**
     * Re-run a pending log's search against the current student database —
     * used by the "Review" action on the Verifier Dashboard's Pending
     * Requests tab, in case the university has since added the record.
     */
    public function recheck(Request $request, VerificationLog $verificationLog)
    {
        // Scope by caller: a verifier may only recheck their own requests, a
        // university admin only their own university's.
        $user = $request->user();
        if ($user && $user->isVerifier() && $verificationLog->verifier_email !== $user->email) {
            return response()->json(['message' => 'This request does not belong to your organisation.'], 403);
        }
        if ($user && $user->isUniversityAdmin() && $verificationLog->university_id !== $user->university_id) {
            return response()->json(['message' => 'This request belongs to another university.'], 403);
        }

        if ($verificationLog->status === 'pending') {
            $student = Student::where('university_id', $verificationLog->university_id)
                ->where('graduate_name', 'like', '%' . $verificationLog->searched_name . '%')
                ->where('father_name', 'like', '%' . $verificationLog->searched_father_name . '%')
                ->where('degree', 'like', '%' . $verificationLog->searched_degree . '%')
                ->where('graduation_year', $verificationLog->searched_year)
                ->first();

            if ($student) {
                $verificationLog->update([
                    'student_id' => $student->id,
                    'result' => 'verified',
                    'status' => 'success',
                    'sla_due_at' => null,
                ]);
            }
        }

        return response()->json($verificationLog->fresh()->load(['university', 'student']));
    }

    /**
     * University Registrar reviews and resolves a pending verification request.
     */
    public function resolve(Request $request, VerificationLog $verificationLog)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500',
            'archive_ref' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:100',
            'nrc_number' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:Male,Female,Other',
            // Only needed when the log carries no usable graduate details —
            // i.e. a request raised by uploading a certificate scan.
            'graduate_name' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'degree' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
        ]);

        // Only the owning university's registrar (or a super admin) may resolve
        // a request. Without this a verifier could approve their own pending
        // request and mint a "verified" credential for themselves.
        $user = $request->user();
        if (!$user || !($user->isSuperAdmin() || $user->isUniversityAdmin())) {
            return response()->json(['message' => 'Only university registrars may resolve verification requests.'], 403);
        }
        if ($user->isUniversityAdmin() && $verificationLog->university_id !== $user->university_id) {
            return response()->json(['message' => 'Unauthorized action for this university.'], 403);
        }

        if ($validated['action'] === 'approve') {
            // Match on every field the verifier searched on — matching by name
            // alone would attach the request to the wrong graduate whenever two
            // people share a name.
            $student = Student::where('university_id', $verificationLog->university_id)
                ->where('graduate_name', 'like', '%' . $verificationLog->searched_name . '%')
                ->where('father_name', 'like', '%' . $verificationLog->searched_father_name . '%')
                ->where('degree', 'like', '%' . $verificationLog->searched_degree . '%')
                ->where('graduation_year', $verificationLog->searched_year)
                ->first();

            if (!$student) {
                // Digitising an archived record: the registrar must supply the
                // real identity details. Never invent an NRC, date of birth or
                // gender — this record becomes an authoritative credential.
                //
                // A request raised by uploading a certificate carries NO usable
                // graduate details: its searched_name is just the uploaded
                // file's name, which is not a person. Every field must then come
                // from the registrar, who is reading them off the document.
                // (Without this the insert fails outright, because the students
                // table requires father_name, degree and graduation_year.)
                $fromLog = $verificationLog->document_url
                    ? []
                    : [
                        'graduate_name' => $verificationLog->searched_name,
                        'father_name' => $verificationLog->searched_father_name,
                        'degree' => $verificationLog->searched_degree,
                        'graduation_year' => $verificationLog->searched_year,
                    ];

                $candidate = [];
                $missing = [];

                foreach ([
                    'graduate_name', 'father_name', 'degree', 'graduation_year',
                    'nrc_number', 'date_of_birth', 'gender',
                ] as $field) {
                    $value = $validated[$field] ?? null;

                    if (empty($value)) {
                        $value = $fromLog[$field] ?? null;
                    }
                    if (empty($value)) {
                        $missing[] = $field;
                    }

                    $candidate[$field] = $value;
                }

                if ($missing) {
                    return response()->json([
                        'message' => 'This request has no graduate details on file. Enter the graduate\'s real '
                            . implode(', ', $missing)
                            . ' — exactly as they appear in the archive ledger — before approving.',
                        'errors' => array_fill_keys($missing, ['This field is required to create an archived graduate record.']),
                    ], 422);
                }

                if (Student::where('nrc_number', $candidate['nrc_number'])->exists()) {
                    return response()->json([
                        'message' => 'A graduate record with this NRC number already exists. Please open that record instead of creating a duplicate.',
                        'errors' => ['nrc_number' => ['This NRC number is already registered.']],
                    ], 422);
                }

                $student = Student::create([
                    'university_id' => $verificationLog->university_id,
                    'graduate_name' => $candidate['graduate_name'],
                    'father_name' => $candidate['father_name'],
                    'gender' => $candidate['gender'],
                    'date_of_birth' => $candidate['date_of_birth'],
                    'nrc_number' => $candidate['nrc_number'],
                    'student_id' => $validated['student_id'] ?: null,
                    'degree' => $candidate['degree'],
                    'specialization' => null,
                    'graduation_year' => $candidate['graduation_year'],
                ]);
            }

            $archiveNote = !empty($validated['archive_ref']) ? " [Archive Ref: {$validated['archive_ref']}]" : '';
            $finalNotes = ($validated['notes'] ?? 'Manually confirmed and approved by university registrar.') . $archiveNote;

            $verificationLog->update([
                'student_id' => $student->id,
                'status' => 'success',
                'result' => 'verified',
                'sla_due_at' => null,
                'notes' => $finalNotes,
            ]);
        } else {
            $finalNotes = $validated['notes'] ?? 'Record not found in university archives after manual review.';
            $verificationLog->update([
                'status' => 'failed',
                'result' => 'not_found',
                'sla_due_at' => null,
                'notes' => $finalNotes,
            ]);
        }

        return response()->json([
            'message' => $validated['action'] === 'approve'
                ? 'Verification request approved successfully. Record is now verified.'
                : 'Verification request rejected.',
            'log' => $verificationLog->fresh()->load(['university', 'student']),
        ]);
    }

    public function logs(Request $request)
    {
        $query = VerificationLog::with(['university', 'student']);

        // Filter by university if user is university admin
        if ($request->user() && $request->user()->isUniversityAdmin()) {
            $query->where('university_id', $request->user()->university_id);
        }

        // A verifier only ever sees requests submitted under their own email.
        // This must never be conditional: skipping it for an account with no
        // submissions yet would expose every organisation's searches (names,
        // NRC numbers, dates of birth) to a brand-new account.
        if ($request->user() && $request->user()->isVerifier()) {
            $query->where('verifier_email', $request->user()->email);
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

        if ($request->user() && $request->user()->isVerifier()) {
            $query->where('verifier_email', $request->user()->email);
        }

        $activities = $query->orderBy('created_at', 'desc')->limit(10)->get();

        return response()->json($activities);
    }
}
