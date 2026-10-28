<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Public contact form.
 *
 * The message is delivered to the platform support mailbox (config/acvr.php,
 * `support@acvr.edu.mm`), with the sender set as Reply-To so a plain "Reply"
 * in the mail client answers the person who wrote in.
 */
class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot: a real browser never fills a field it cannot see. Checked
        // before validation so a bot gets no feedback about the schema.
        if ($request->filled('website')) {
            return response()->json([
                'message' => 'Your message could not be sent. Please try again.',
            ], 422);
        }

        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'email'        => 'required|email|max:190',
            'country'      => 'nullable|string|max:80',
            'organization' => 'nullable|string|max:190',
            'subject'      => 'required|string|max:150',
            'message'      => 'required|string|min:10|max:5000',
            // There is no captcha site key provisioned for this deployment, so
            // the checkbox is a speed bump rather than a real challenge. The
            // throttle:contact limiter and the honeypot do the actual work.
            'not_robot'    => 'accepted',
        ], [
            'not_robot.accepted' => 'Please confirm you are not a robot.',
            'message.min'        => 'Please give us a little more detail (at least 10 characters).',
        ]);

        $context = [
            'ip'      => $request->ip(),
            'country' => IpGeo::locate($request->ip(), $request)['country'],
        ];

        try {
            Mail::to(config('acvr.support_email'))
                ->send(new ContactMessageMail($data, $context));
        } catch (\Throwable $e) {
            // Same principle as the password reset: never claim delivery that
            // did not happen. The page falls back to showing the mailbox.
            report($e);

            return response()->json([
                'message' => 'We could not send your message right now. Please email '
                    . config('acvr.support_email') . ' directly.',
            ], 500);
        }

        return response()->json([
            'message' => 'Thank you — your message has been sent. We usually reply within 2 business days.',
        ]);
    }
}
