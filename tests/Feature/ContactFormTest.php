<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The contact form writes nothing to the database — it validates and emails —
 * so these tests run without touching MySQL.
 */
class ContactFormTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Daw Aye Aye',
            'email' => 'ayeaye@example.com',
            'country' => 'Myanmar',
            'organization' => 'Example Recruitment',
            'subject' => 'Question about verification',
            'message' => 'We would like to know whether older certificates can be verified.',
            'not_robot' => true,
        ], $overrides);
    }

    public function test_a_valid_message_is_emailed_to_the_support_mailbox(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contact', $this->validPayload());

        $response->assertOk();

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo(config('acvr.support_email'))
                && $mail->hasReplyTo('ayeaye@example.com');
        });
    }

    public function test_the_support_address_defaults_to_the_acvr_domain(): void
    {
        $this->assertSame('support@acvr.edu.mm', config('acvr.support_email'));
    }

    public function test_the_robot_checkbox_must_be_ticked(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', $this->validPayload(['not_robot' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('not_robot');

        Mail::assertNothingSent();
    }

    public function test_a_filled_honeypot_is_silently_dropped(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', $this->validPayload(['website' => 'http://spam.example']))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_required_fields_are_validated(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', ['not_robot' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

        Mail::assertNothingSent();
    }

    public function test_a_mail_transport_failure_is_reported_honestly(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $response = $this->postJson('/api/contact', $this->validPayload());

        // Never a false "sent" — the page then shows the mailbox instead.
        $response->assertStatus(500);
        $this->assertStringContainsString(
            config('acvr.support_email'),
            $response->json('message')
        );
    }
}
