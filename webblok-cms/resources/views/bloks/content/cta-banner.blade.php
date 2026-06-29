@php
    /** @var array<string,mixed> $config */
    $title = (string) ($config['title'] ?? '');
    $text = (string) ($config['text'] ?? '');
    $buttonLabel = (string) ($config['button_label'] ?? '');
    $link = is_array($config['button_link'] ?? null) ? $config['button_link'] : [];
    $url = (string) ($link['url'] ?? '#');
    $target = (string) ($link['target'] ?? '_self');
    $bg = (string) ($config['bg'] ?? '#4f46e5');
    $fg = (string) ($config['fg'] ?? '#ffffff');
@endphp
<div class="wb-cta-banner" style="background:{{ $bg }};color:{{ $fg }};padding:3rem 1.5rem;border-radius:.75rem;text-align:center;">
    @if ($title !== '')<h2 style="margin:0 0 .5rem;" data-field="title">{{ $title }}</h2>@endif
    @if ($text !== '')<p style="margin:0 0 1.5rem;opacity:.9;" data-field="text">{{ $text }}</p>@endif
    @if ($buttonLabel !== '')
        <a href="{{ $url }}" target="{{ $target }}" style="display:inline-block;background:{{ $fg }};color:{{ $bg }};padding:.75rem 1.75rem;border-radius:.5rem;font-weight:600;text-decoration:none;">{{ $buttonLabel }}</a>
    @endif
</div>
