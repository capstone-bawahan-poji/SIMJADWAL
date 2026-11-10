{{ __('Class Schedule :period', ['period' => $periodLabel]) }}
{{ __(':courses courses', ['courses' => $courseCount]) }} · {{ $totalSks }} SKS

{{ __('Below is the schedule of the classes you picked.') }}

@foreach ($lines as $line)
{{ $line['day'] }}, {{ $line['time'] }}
{{ $line['name'] }}
{{ $line['code'] }} · {{ $line['sks'] }} SKS · {{ __('Class') }} {{ $line['class_label'] }} · {{ __('Room') }} {{ $line['room'] }}

@endforeach
{{ __('Attachments') }}: {{ collect($formats)->map(fn ($f) => strtoupper($f))->implode(' · ') }}

{{ __('This schedule is the version published by the faculty when the email was sent. If it changes, send a new request from the class selection page.') }}

--
SIMJADWAL · Institut Teknologi Kalimantan
{{ __('Sent automatically because a schedule was requested for this address. Ignore it if you did not request it. Replies are not read.') }}
