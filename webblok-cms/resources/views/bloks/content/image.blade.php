@php
    /** @var array<string,mixed> $config */
    $src = (string) ($config['src'] ?? '');
    $alt = (string) ($config['alt'] ?? '');
    $caption = (string) ($config['caption'] ?? '');
    $link = is_array($config['link'] ?? null) ? $config['link'] : [];
    $url = (string) ($link['url'] ?? '');
    $target = (string) ($link['target'] ?? '_self');
    $rounded = (bool) ($config['rounded'] ?? true);
    $radius = $rounded ? '.75rem' : '0';
@endphp
<figure class="wb-image" style="margin:0;text-align:center;">
    @if ($url !== '')<a href="{{ $url }}" target="{{ $target }}">@endif
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" style="max-width:100%;height:auto;border-radius:{{ $radius }};">
    @if ($url !== '')</a>@endif
    @if ($caption !== '')
        <figcaption style="font-size:.875rem;opacity:.7;margin-top:.5rem;">{{ $caption }}</figcaption>
    @endif
</figure>
