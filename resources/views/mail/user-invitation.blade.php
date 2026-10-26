<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ __('messages.userInvitationEmail.subject', ['app' => config('app.name')]) }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    {{-- Preheader: shown as the inbox preview line, hidden in the body --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        {{ __('messages.userInvitationEmail.preheader') }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                    {{-- Brand header: logo with a text alt so the email stays branded when images are blocked --}}
                    <tr>
                        <td align="center" style="padding:8px 4px 24px;">
                            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" width="140" style="display:block; width:140px; max-width:60%; height:auto; border:0;">
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

                                {{-- Heading + intro --}}
                                <tr>
                                    <td style="padding:32px 32px 8px;">
                                        <p style="margin:0 0 6px; font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#37b44a;">
                                            {{ __('messages.userInvitationEmail.eyebrow') }}
                                        </p>
                                        <h1 style="margin:0 0 16px; font-size:22px; font-weight:700; color:#575876;">
                                            {{ __('messages.userInvitationEmail.title') }}
                                        </h1>
                                        <p style="margin:0 0 8px; font-size:14px; line-height:22px; color:#374151;">
                                            {{ __('messages.userInvitationEmail.greeting', ['name' => $name]) }}
                                        </p>
                                        <p style="margin:0; font-size:14px; line-height:22px; color:#6b7280;">
                                            {{ __('messages.userInvitationEmail.intro', ['inviter' => $inviter, 'app' => config('app.name')]) }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Call to action --}}
                                <tr>
                                    <td style="padding:24px 32px 8px;" align="center">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="border-radius:8px; background-color:#37b44a;">
                                                    <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:8px;">
                                                        {{ __('messages.userInvitationEmail.button') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Expiry note --}}
                                <tr>
                                    <td style="padding:8px 32px 24px;" align="center">
                                        <p style="margin:0; font-size:12px; line-height:18px; color:#9ca3af;">
                                            {{ __('messages.userInvitationEmail.expiry', ['days' => $expiresInDays]) }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Fallback link --}}
                                <tr>
                                    <td style="padding:0 32px 32px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb;">
                                            <tr>
                                                <td style="padding-top:20px;">
                                                    <p style="margin:0 0 8px; font-size:12px; line-height:18px; color:#6b7280;">
                                                        {{ __('messages.userInvitationEmail.fallbackLabel') }}
                                                    </p>
                                                    <p style="margin:0; font-size:12px; line-height:18px; word-break:break-all;">
                                                        <a href="{{ $url }}" target="_blank" style="color:#37b44a; text-decoration:underline;">{{ $url }}</a>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 4px 0;" align="center">
                            <p style="margin:0 0 6px; font-size:11px; line-height:16px; color:#9ca3af;">
                                {{ __('messages.userInvitationEmail.security') }}
                            </p>
                            <p style="margin:0; font-size:11px; line-height:16px; color:#9ca3af;">
                                {{ __('messages.userInvitationEmail.footer', ['app' => config('app.name')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
