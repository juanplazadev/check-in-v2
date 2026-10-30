<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    {{-- Preheader: shown as the inbox preview line, hidden in the body --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                    {{-- Brand header: logo with a text alt so the email stays branded when images are blocked --}}
                    <tr>
                        <td style="padding:0 4px 16px;">
                            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" width="140" style="display:block; width:140px; max-width:60%; height:auto; border:0;">
                        </td>
                    </tr>

                    {{-- Page title --}}
                    <tr>
                        <td style="padding:0 4px 20px;">
                            <p style="margin:0 0 4px; font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#37b44a;">
                                {{ $eyebrow }}
                            </p>
                            <h1 style="margin:0 0 8px; font-size:22px; font-weight:700; color:#575876;">
                                {{ $title }}
                            </h1>
                            <p style="margin:0; font-size:14px; line-height:20px; color:#6b7280;">
                                {{ $intro }}
                            </p>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

                                {{-- Green accent bar --}}
                                <tr>
                                    <td style="height:4px; background-color:#37b44a; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>

                                {{-- Reference --}}
                                <tr>
                                    <td style="padding:24px 24px 16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:40px; vertical-align:top;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td align="center" valign="middle" style="width:32px; height:32px; background-color:#37b44a; border-radius:16px; color:#ffffff; font-size:18px; font-weight:700; line-height:32px;">
                                                                &#10003;
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="vertical-align:top; padding-left:4px;">
                                                    <p style="margin:0 0 2px; font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:#6b7280;">
                                                        {{ $referenceLabel }}
                                                    </p>
                                                    <p style="margin:0; font-size:24px; font-weight:700; letter-spacing:2px; color:#111827;">
                                                        {{ $referenceNumber }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Summary rows --}}
                                <tr>
                                    <td style="padding:0 24px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:8px; background-color:#f9fafb;">
                                            @foreach ($rows as $row)
                                                <tr>
                                                    <td style="padding:12px 16px; {{ ! $loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                                                        <span style="display:block; font-size:12px; font-weight:500; color:#6b7280;">{{ $row['label'] }}</span>
                                                        <span style="display:block; margin-top:2px; font-size:14px; font-weight:600; color:#111827;">{{ $row['value'] }}</span>
                                                        @isset($row['sub'])
                                                            <span style="display:block; margin-top:2px; font-size:12px; color:#6b7280;">{{ $row['sub'] }}</span>
                                                        @endisset
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 4px 0;" align="center">
                            <p style="margin:0; font-size:11px; color:#9ca3af;">
                                {{ __('messages.mailFooter.automated', ['app' => config('app.name')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
