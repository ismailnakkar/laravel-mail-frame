@use('Illuminate\Contracts\Support\Htmlable')
@use('MailFrame\Message')
@php
    $preheader ??= null;
    $heading ??= null;
    $footnote ??= null;
    $footer ??= null;

    $site = config('app.name');
    $logo = config('mail-frame.logo');
    $logoWidth = config('mail-frame.logo_width');
    $colors = config('mail-frame.colors');
    $lang = str_replace('_', '-', app()->getLocale());
    $rtl = locale_is_right_to_left($lang);
    $dir = $rtl ? 'rtl' : 'ltr';
    // Outlook reads neither align="start" nor text-align:start.
    [$start, $end] = $rtl ? ['right', 'left'] : ['left', 'right'];
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    [$fsi, $pdi] = $rtl ? ["\u{2068}", "\u{2069}"] : ['', ''];
    $toneLine = "border-top:3px solid {$tone->accent()}; border-radius:13px 13px 0 0;";
    $lines = static fn (mixed $value): string => $value instanceof Htmlable ? $fsi.$value->toHtml().$pdi : nl2br(Message::isolate(trim(e($value))));
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    {{-- Stops Apple Mail and the Gmail apps repainting the card dark; Outlook ignores it. --}}
    <meta name="color-scheme" content="light only" />
    <meta name="supported-color-schemes" content="light" />
    <title>{{ $subject ?: $site }}</title>
    <style type="text/css">
        :root { color-scheme: light; supported-color-schemes: light; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background: {{ $colors['page'] }}; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: {{ $colors['link'] }}; }
        .mail-body > :last-child { margin-bottom: 0 !important; }
        @media only screen and (max-width: 480px) {
            .mail-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .mail-head { padding-top: 24px !important; }
            .mail-body { padding-bottom: 24px !important; }
        }
    </style>
</head>
<body dir="{{ $dir }}" style="margin:0; padding:0; width:100%; background:{{ $colors['page'] }};">
@if ($preheader)
    <div style="display:none; mso-hide:all; font-size:1px; color:{{ $colors['page'] }}; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">{{ $preheader }}</div>
@endif
{{-- lang and dir again on the outer table: Gmail drops the attributes of <html> and <body>. --}}
<table role="presentation" lang="{{ $lang }}" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $dir }}" style="background:{{ $colors['page'] }};">
    <tr>
        <td align="center" style="padding:32px 12px 36px;">
            {{-- Outlook ignores max-width: this table fixes the column at 600px. --}}
            <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">
                <tr>
                    <td align="{{ $start }}" style="padding:0 4px 18px; font-family:{!! $font !!}; font-size:17px; font-weight:700; color:{{ $colors['heading'] }};">
                        @if ($logo)
                            <img src="{{ asset($logo) }}" alt="{{ $site }}" width="{{ $logoWidth }}" style="display:block; width:{{ $logoWidth }}px; height:auto; border:0;" />
                        @else
                            {{ $site }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff; border:1px solid {{ $colors['rule'] }}; border-radius:14px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            {{-- A cell border: a 3px row would scale the radius down. --}}
                            @if ($heading)
                                <tr>
                                    <td class="mail-pad mail-head" style="{{ $toneLine }} padding:30px 32px 0;"><h1 style="margin:0; font-family:{!! $font !!}; font-size:20px; line-height:1.35; font-weight:700; letter-spacing:-0.01em; color:{{ $colors['heading'] }};">{{ $heading }}</h1></td>
                                </tr>
                            @endif
                            <tr>
                                <td class="mail-pad mail-body" style="{{ $heading ? 'padding:14px' : "{$toneLine} padding:30px" }} 32px 30px; font-family:{!! $font !!}; font-size:15px; line-height:1.65; color:{{ $colors['text'] }};">
                                    @if ($greeting)
                                        <p style="margin:0 0 16px;">{{ $greeting }}</p>
                                    @endif

                                    @foreach ($blocks as $block)
                                        @switch($block['type'])
                                            @case('line')
                                                <p style="margin:0 0 16px;">{{ $block['text'] }}</p>
                                                @break
                                            @case('bullets')
                                                <ul style="margin:0 0 16px; margin-{{ $start }}:20px; padding:0;">
                                                    @foreach ($block['items'] as $item)
                                                        <li style="margin:0 0 6px;">{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                                @break
                                            @case('facts')
                                                {{-- Inline: a global border-collapse loses the card's rounded corners. --}}
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px; border-collapse:collapse;">
                                                    @foreach ($block['rows'] as $row)
                                                        @php $rule = "border-top:1px solid {$colors['rule']};".($loop->last ? " border-bottom:1px solid {$colors['rule']};" : ''); @endphp
                                                        <tr><th scope="row" align="{{ $start }}" style="width:40%; padding:9px 0; font-family:{!! $font !!}; font-size:15px; line-height:1.65; {{ $rule }} font-weight:400; text-align:{{ $start }}; color:{{ $colors['muted'] }};">{{ $row['label'] }}</th><td align="{{ $end }}" style="padding:9px 0; font-family:{!! $font !!}; font-size:15px; line-height:1.65; {{ $rule }} font-weight:600; text-align:{{ $end }}; color:{{ $colors['heading'] }}; font-variant-numeric:tabular-nums;">{!! $lines($row['value']) !!}</td></tr>
                                                    @endforeach
                                                </table>
                                                @break
                                            @case('panel')
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                                                    <tr><td style="padding:14px 18px; font-family:{!! $font !!}; background:{{ $block['tone']->tint() }}; border:1px solid {{ $block['tone']->border() }}; border-radius:10px; font-size:15px; line-height:1.55; color:{{ $colors['heading'] }};">{!! $lines($block['text']) !!}</td></tr>
                                                </table>
                                                @break
                                            @case('action')
                                                @php $accent = ($block['tone'] ?? $tone)->accent(); @endphp
                                                {{-- Link padding is the hit area; mso-padding-alt keeps it on the cell for Outlook. The bare URL covers clients that strip the link. --}}
                                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 12px;">
                                                    <tr><td align="center" bgcolor="{{ $accent }}" style="mso-padding-alt:14px 22px; border-radius:10px; background:{{ $accent }};"><a href="{{ $block['url'] }}" style="display:inline-block; padding:14px 22px; mso-padding-alt:0; font-family:{!! $font !!}; font-size:15px; font-weight:600; line-height:1; color:#ffffff; text-decoration:none;">{{ $block['text'] }}</a></td></tr>
                                                </table>
                                                {{-- Outside the link: a marked label would differ from its URL, and the text part would print both. --}}
                                                <p style="margin:0 0 22px; font-size:13px; line-height:1.5; color:{{ $colors['muted'] }}; word-break:break-all;">{{ $fsi }}<a href="{{ $block['url'] }}" style="color:{{ $colors['muted'] }};">{{ $block['url'] }}</a>{{ $pdi }}</p>
                                                @break
                                        @endswitch
                                    @endforeach

                                    @if ($salutation)
                                        <p style="margin:0 0 16px;">{{ $salutation }}<br />{{ $signature ?? $site }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if ($footnote || $footer)
                    <tr>
                        <td style="padding:22px 8px 0; font-family:{!! $font !!}; font-size:12.5px; line-height:1.6; color:{{ $colors['muted'] }};">
                            @if ($footnote)
                                <div style="margin:0 0 8px;">{{ $footnote }}</div>
                            @endif
                            @if ($footer)
                                {{ $footer['text'] }}<br />
                                <a href="{{ $footer['url'] }}" style="color:{{ $colors['muted'] }};">{{ $site }}</a>
                            @endif
                        </td>
                    </tr>
                @endif
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
