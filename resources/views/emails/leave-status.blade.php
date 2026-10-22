<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Axcess — Leave request {{ ucfirst($status) }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:#0f172a;padding:24px 32px;">
                            <div style="font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Axcess</div>
                            <div style="font-size:11px;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;margin-top:2px;">Staff Scheduling &amp; Workforce Management</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;font-size:14px;color:#64748b;">Hello {{ $leave->user->first_name }},</p>
                            <h1 style="margin:0 0 20px;font-size:22px;color:#0f172a;">Your leave request has been {{ $status }}.</h1>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                                <tr>
                                    <td style="padding:6px 12px;font-size:13px;color:#64748b;">Leave type</td>
                                    <td style="padding:6px 12px;font-size:13px;font-weight:600;color:#0f172a;">{{ $leave->leave_type_label }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 12px;font-size:13px;color:#64748b;">Dates</td>
                                    <td style="padding:6px 12px;font-size:13px;font-weight:600;color:#0f172a;">{{ $leave->date_range_label }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 12px;font-size:13px;color:#64748b;">Duration</td>
                                    <td style="padding:6px 12px;font-size:13px;font-weight:600;color:#0f172a;">{{ $leave->duration_label }}</td>
                                </tr>
                            </table>

                            @if ($note)
                                <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#334155;"><strong>Note from your manager:</strong><br>{{ $note }}</p>
                            @endif

                            <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">Please contact your manager if you have any questions.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;">
                            Axcess — Staff Scheduling &amp; Workforce Management · This is an automated message.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
