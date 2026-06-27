@php
    /** @var array<string,mixed> $config */
    $title = (string) ($config['title'] ?? '');
    $subtitle = (string) ($config['subtitle'] ?? '');
    $align = (string) ($config['align'] ?? 'center');
    $bg = (string) ($config['bg'] ?? '#0f172a');
    $fg = (string) ($config['fg'] ?? '#f8fafc');
    $ctaLabel = (string) ($config['cta_label'] ?? '');
    $ctaLink = is_array($config['cta_link'] ?? null) ? $config['cta_link'] : [];
    $ctaUrl = (string) ($ctaLink['url'] ?? '#');
    $ctaTarget = (string) ($ctaLink['target'] ?? '_self');
    $minHeight = (int) ($config['min_height'] ?? 60);
@endphp
<div class="wb-hero" style="background:{{ $bg }};color:{{ $fg }};text-align:{{ $align }};min-height:{{ $minHeight }}vh;display:flex;flex-direction:column;justify-content:center;padding:4rem 1.5rem;">
    @if ($title !== '')
        <h1 class="wb-hero__title" data-field="title" style="font-size:clamp(2rem,5vw,3.5rem);margin:0 0 .75rem;font-weight:800;">{{ $title }}</h1>
    @endif
    @if ($subtitle !== '')
        <p class="wb-hero__subtitle" data-field="subtitle" style="font-size:1.25rem;opacity:.9;margin:0 auto 1.5rem;max-width:680px;">{{ $subtitle }}</p>
    @endif
    @if ($ctaLabel !== '')
        <p style="margin:0;"><a href="{{ $ctaUrl }}" target="{{ $ctaTarget }}" class="wb-btn wb-btn--primary" style="display:inline-block;background:{{ $fg }};color:{{ $bg }};padding:.75rem 1.75rem;border-radius:.5rem;font-weight:600;text-decoration:none;">{{ $ctaLabel }}</a></p>
    @endif
</div>
