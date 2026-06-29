@php
    /** @var array<string,mixed> $config */
    $bg = (string) ($config['bg'] ?? '#ffffff');
    $padding = (int) ($config['padding'] ?? 48);
    $maxWidth = (int) ($config['max_width'] ?? 1100);
    $fullBleed = (bool) ($config['full_bleed'] ?? false);
    $childrenHtml = (string) ($config['__children_html'] ?? '');
@endphp
<div class="wb-section-blok" style="background:{{ $bg }};padding:{{ $padding }}px 1.5rem;">
    <div style="max-width:{{ $fullBleed ? 'none' : $maxWidth . 'px' }};margin:0 auto;">
        {!! $childrenHtml !!}
    </div>
</div>
