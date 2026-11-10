@php
    $embed = fn (string $file) => isset($message) ? $message->embed(resource_path("images/mail/{$file}")) : '';
    $attachmentNotes = ['pdf' => 'PDF ('.__('for printing').')', 'xlsx' => 'XLSX ('.__('for editing').')', 'ics' => 'ICS ('.__('for calendar import').')'];
    $attached = collect($formats)->map(fn ($format) => $attachmentNotes[$format] ?? strtoupper($format))->implode(' · ');
    $font = 'Arial,Helvetica,sans-serif';
    $head = "padding:0 8px 8px 0;border-bottom:1px solid #dddddd;font-family:{$font};font-size:12px;font-weight:normal;color:#666666;text-align:left;vertical-align:bottom;";
    $cell = "padding:10px 8px 10px 0;border-bottom:1px solid #dddddd;font-family:{$font};font-size:14px;line-height:20px;color:#1a1a1a;text-align:left;vertical-align:top;";
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('Class Schedule :period', ['period' => $periodLabel]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ __(':courses courses', ['courses' => $courseCount]) }} · {{ $totalSks }} SKS
    </div>
    <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;">
        <tr>
            <td style="padding:24px 16px 0 16px;">
                <img src="{{ $embed('logo-itk.png') }}" alt="Institut Teknologi Kalimantan" height="40" style="display:block;border:0;height:40px;width:auto;">
            </td>
        </tr>
        <tr>
            <td style="padding:28px 16px 0 16px;font-family:{{ $font }};">
                <h1 style="margin:0;font-family:{{ $font }};font-size:20px;line-height:26px;font-weight:bold;color:#0f2a4a;">{{ __('Class Schedule :period', ['period' => $periodLabel]) }}</h1>
                <p style="margin:4px 0 0 0;font-size:14px;line-height:20px;color:#666666;">{{ __(':courses courses', ['courses' => $courseCount]) }} · {{ $totalSks }} SKS</p>
                <p style="margin:16px 0 0 0;font-size:14px;line-height:20px;color:#1a1a1a;">{{ __('Below is the schedule of the classes you picked.') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 16px 0 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <th width="15%" style="{{ $head }}">{{ __('Day') }}</th>
                        <th width="22%" style="{{ $head }}">{{ __('Time') }}</th>
                        <th style="{{ $head }}">{{ __('Course') }}</th>
                        <th width="10%" style="{{ $head }}">{{ __('Class') }}</th>
                        <th width="14%" style="{{ $head }}padding-right:0;">{{ __('Room') }}</th>
                    </tr>
                    @foreach ($lines as $line)
                        <tr>
                            <td style="{{ $cell }}">{{ $line['day'] }}</td>
                            <td style="{{ $cell }}">{{ $line['time'] }}</td>
                            <td style="{{ $cell }}">
                                {{ $line['name'] }}<br>
                                <span style="font-size:14px;color:#666666;">{{ $line['code'] }} · {{ $line['sks'] }} SKS</span>
                            </td>
                            <td style="{{ $cell }}">{{ $line['class_label'] }}</td>
                            <td style="{{ $cell }}padding-right:0;">{{ $line['room'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 16px 0 16px;font-family:{{ $font }};font-size:14px;line-height:20px;color:#1a1a1a;">
                {{ __('Attachments') }}: {{ $attached }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px 0 16px;font-family:{{ $font }};font-size:14px;line-height:20px;color:#666666;">
                {{ __('This schedule is the version published by the faculty when the email was sent. If it changes, send a new request from the class selection page.') }}
            </td>
        </tr>
        <tr>
            <td style="padding:28px 16px 24px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="border-top:1px solid #dddddd;padding:12px 0 0 0;font-family:{{ $font }};font-size:12px;line-height:18px;color:#666666;">
                            SIMJADWAL · Institut Teknologi Kalimantan<br>
                            {{ __('Sent automatically because a schedule was requested for this address. Ignore it if you did not request it. Replies are not read.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <!--[if mso]></td></tr></table><![endif]-->
</body>
</html>
