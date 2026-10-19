<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your ACVR password</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#163172;padding:22px 32px;">
                            <div style="font-size:20px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">ACVR</div>
                            <div style="font-size:11px;color:#c7d6ee;margin-top:2px;">Academic Credential Verification &amp; Registry</div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 14px;font-size:19px;color:#163172;">Reset your password</h1>

                            <p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#334155;">
                                Hello {{ $name }},
                            </p>

                            <p style="margin:0 0 24px;font-size:14px;line-height:1.65;color:#334155;">
                                We received a request to reset the password for your ACVR account.
                                Click the button below to choose a new one.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:6px;background:#eda02c;">
                                        <a href="{{ $resetUrl }}"
                                           style="display:inline-block;padding:13px 30px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#64748b;">
                                This link expires in {{ $expiresIn }} minutes and can only be used once.
                            </p>

                            <p style="margin:0 0 24px;font-size:12px;line-height:1.6;color:#64748b;">
                                If the button does not work, copy and paste this address into your browser:
                            </p>

                            <p style="margin:0 0 24px;font-size:11px;line-height:1.6;color:#2563eb;word-break:break-all;">
                                {{ $resetUrl }}
                            </p>

                            <div style="border-top:1px solid #e2e8f0;padding-top:18px;">
                                <p style="margin:0;font-size:12px;line-height:1.65;color:#64748b;">
                                    If you did not request a password reset, you can ignore this email —
                                    your password will stay unchanged. If you are concerned about your
                                    account, please contact your institution's administrator.
                                </p>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
