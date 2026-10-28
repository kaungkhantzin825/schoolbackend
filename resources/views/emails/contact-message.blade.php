<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ACVR contact form</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#163172;padding:22px 32px;">
                            <div style="font-size:20px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">ACVR</div>
                            <div style="font-size:11px;color:#c7d6ee;margin-top:2px;">Academic Credential Verification &amp; Registry</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 32px 8px;">
                            <h1 style="margin:0 0 6px;font-size:18px;color:#163172;">New message from the contact form</h1>
                            <p style="margin:0;font-size:13px;color:#64748b;">
                                Reply to this email to answer {{ $name }} directly.
                            </p>
                        </td>
                    </tr>

                    {{-- Sender details --}}
                    <tr>
                        <td style="padding:18px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;line-height:1.7;color:#334155;">
                                <tr>
                                    <td style="width:150px;color:#64748b;padding:4px 0;">Name</td>
                                    <td style="padding:4px 0;"><strong>{{ $name }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748b;padding:4px 0;">Email</td>
                                    <td style="padding:4px 0;">
                                        <a href="mailto:{{ $email }}" style="color:#1d4ed8;text-decoration:none;">{{ $email }}</a>
                                    </td>
                                </tr>
                                @if ($organization)
                                    <tr>
                                        <td style="color:#64748b;padding:4px 0;">Organization</td>
                                        <td style="padding:4px 0;">{{ $organization }}</td>
                                    </tr>
                                @endif
                                @if ($country)
                                    <tr>
                                        <td style="color:#64748b;padding:4px 0;">Country</td>
                                        <td style="padding:4px 0;">{{ $country }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="color:#64748b;padding:4px 0;">Subject</td>
                                    <td style="padding:4px 0;">{{ $subjectLine }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Message body --}}
                    <tr>
                        <td style="padding:22px 32px 0;">
                            <div style="border-left:3px solid #eda02c;background:#f8fafc;padding:14px 16px;border-radius:0 6px 6px 0;">
                                <p style="margin:0;font-size:14px;line-height:1.75;color:#334155;white-space:pre-line;">{{ $body }}</p>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 32px 30px;">
                            <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
                                <p style="margin:0;font-size:11px;line-height:1.7;color:#94a3b8;">
                                    Sent from the ACVR contact form.
                                    @if ($ip)
                                        Sender IP {{ $ip }}@if ($ipCountry) ({{ $ipCountry }})@endif.
                                    @endif
                                    This address is informational only.
                                </p>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
