<?php

namespace Tests\Feature;

use App\Models\University;
use App\Models\User;
use App\Models\VerificationLog;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The verifier's "Rejected Requests" list.
 *
 * A rejected request and an instant "not found" search both end up with
 * status = failed. They must not be conflated: only the former ever went to a
 * registrar, and only it has a rejection reason worth showing.
 *
 * No RefreshDatabase — this runs against the development database. Each test
 * creates its own accounts and logs and removes them again.
 */
class VerifierRejectedListTest extends TestCase
{
    private User $verifier;
    private User $otherVerifier;
    private ?User $registrar = null;
    private University $university;
    private array $logIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->university = University::query()->firstOrFail();

        $this->verifier = User::create([
            'name' => 'Rejected List Verifier',
            'email' => 'rejlist-' . uniqid() . '@example.test',
            'password' => Hash::make('password123'),
            'role' => 'verifier',
        ]);

        $this->otherVerifier = User::create([
            'name' => 'Other Verifier',
            'email' => 'rejlist-other-' . uniqid() . '@example.test',
            'password' => Hash::make('password123'),
            'role' => 'verifier',
        ]);
    }

    protected function tearDown(): void
    {
        VerificationLog::whereIn('id', $this->logIds)->delete();
        User::whereIn('id', array_filter([
            $this->verifier->id,
            $this->otherVerifier->id,
            $this->registrar?->id,
        ]))->delete();

        parent::tearDown();
    }

    private function makeLog(array $overrides = []): VerificationLog
    {
        $log = VerificationLog::create(array_merge([
            'university_id' => $this->university->id,
            'verifier_name' => $this->verifier->name,
            'verifier_email' => $this->verifier->email,
            'organization_type' => 'Employer',
            'organization_name' => 'Test Org',
            'searched_name' => 'Test Candidate',
            'searched_father_name' => 'Test Father',
            'searched_degree' => 'BSc',
            'searched_year' => 2001,
            'result' => 'not_found',
            'status' => 'failed',
            'notes' => null,
        ], $overrides));

        $this->logIds[] = $log->id;

        return $log;
    }

    public function test_list_returns_only_registrar_rejections(): void
    {
        $rejected = $this->makeLog([
            'request_ref' => 'VR-9001',
            'notes' => 'Record not found in the 2001 convocation register.',
        ]);

        // Still awaiting review — must not appear.
        $this->makeLog([
            'request_ref' => 'VR-9002',
            'status' => 'pending',
            'result' => 'not_found',
            'sla_due_at' => now()->addDays(5),
        ]);

        // Instant "not found" from a recent graduate: never queued, no ref.
        $this->makeLog([
            'request_ref' => null,
            'status' => 'failed',
            'notes' => 'No record matched the supplied details.',
        ]);

        $response = $this->actingAs($this->verifier, 'sanctum')
            ->getJson('/api/verification-logs?rejected=1');

        $response->assertOk()->assertJsonCount(1, 'data');

        $row = $response->json('data.0');
        $this->assertSame($rejected->id, $row['id']);
        $this->assertSame('VR-9001', $row['request_ref']);
        $this->assertSame(
            'Record not found in the 2001 convocation register.',
            $row['notes'],
            'The registrar\'s reason must be returned to the verifier.'
        );
        $this->assertNotNull($row['updated_at']);
    }

    public function test_list_is_scoped_to_the_signed_in_verifier(): void
    {
        $this->makeLog([
            'request_ref' => 'VR-9003',
            'verifier_email' => $this->otherVerifier->email,
            'notes' => 'Someone else\'s rejection.',
        ]);

        $this->actingAs($this->verifier, 'sanctum')
            ->getJson('/api/verification-logs?rejected=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_missing_reason_is_returned_as_null(): void
    {
        $this->makeLog([
            'request_ref' => 'VR-9004',
            'notes' => null,
        ]);

        $response = $this->actingAs($this->verifier, 'sanctum')
            ->getJson('/api/verification-logs?rejected=1');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertNull($response->json('data.0.notes'));
    }

    /**
     * The registrar's reason must not destroy what the verifier asked.
     */
    public function test_rejection_keeps_the_verifier_note_and_stores_the_reason_separately(): void
    {
        $log = $this->makeLog([
            'request_ref' => 'VR-9005',
            'status' => 'pending',
            'sla_due_at' => now()->addDays(5),
            'notes' => 'Please check the 1999 convocation register for this candidate.',
        ]);

        $this->registrar = User::create([
            'name' => 'Test Registrar',
            'email' => 'rejlist-reg-' . uniqid() . '@example.test',
            'password' => Hash::make('password123'),
            'role' => 'university_admin',
            'university_id' => $this->university->id,
        ]);

        $this->actingAs($this->registrar, 'sanctum')
            ->postJson("/api/verification-logs/{$log->id}/resolve", [
                'action' => 'reject',
                'notes' => 'No entry for this name in the 1999 register.',
            ])
            ->assertOk();

        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame(
            'Please check the 1999 convocation register for this candidate.',
            $log->notes,
            'The verifier\'s original note must survive the rejection.'
        );
        $this->assertSame(
            'No entry for this name in the 1999 register.',
            $log->resolution_notes
        );

        // And the verifier sees both, side by side, from the rejected list.
        $row = $this->actingAs($this->verifier, 'sanctum')
            ->getJson('/api/verification-logs?rejected=1')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(
            'Please check the 1999 convocation register for this candidate.',
            $row['notes']
        );
        $this->assertSame('No entry for this name in the 1999 register.', $row['resolution_notes']);
    }
}
