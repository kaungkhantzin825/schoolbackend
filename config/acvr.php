<?php

/*
|--------------------------------------------------------------------------
| ACVR platform settings
|--------------------------------------------------------------------------
|
| Branding and contact details that are not mail-transport configuration.
| Kept in config (not scattered as string literals) so the domain can change
| in one place when the platform moves to its production host.
|
*/

return [

    // Short brand name. Used as the display name on every outgoing email so
    // recipients see "ACVR <address>" instead of the framework default.
    'name' => env('ACVR_NAME', 'ACVR'),

    // Long name used in email footers and page subtitles.
    'long_name' => env('ACVR_LONG_NAME', 'Academic Credential Verification & Registry'),

    // Where the public contact form is delivered. A real mailbox on the
    // platform domain, not the SMTP account used to send.
    'support_email' => env('ACVR_SUPPORT_EMAIL', 'support@acvr.edu.mm'),

    // Public site, used in email links and the contact page.
    'website' => env('ACVR_WEBSITE', 'https://acvr.edu.mm'),

    // Extra frontend origins allowed to appear in emailed links, comma
    // separated — useful while the site lives on two domains during a move.
    // A request's Origin header is honoured ONLY if it is listed here:
    // trusting an arbitrary Origin would let an attacker request a reset for
    // someone else's account and have the token emailed to a link on their
    // own domain. FRONTEND_URL remains the default.
    'frontend_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ACVR_FRONTEND_URLS', ''))
    ))),

    // Resolve a country name for logged IPs (one cached lookup per address per
    // day, 2s timeout). Set to false on a server with no outbound internet —
    // the log then simply shows "Unknown" instead of waiting on DNS.
    'geo_lookup' => (bool) env('ACVR_GEO_LOOKUP', true),

];
