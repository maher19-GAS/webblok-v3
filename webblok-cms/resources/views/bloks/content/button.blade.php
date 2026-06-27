@php
    /** @var array<string,mixed> $config */
    $label = (string) ($config['label'] ?? 'Button');
    $link = is_array($config['link'] ?? null) ? $config['link'] : [];
    $url = (string) ($link['url'] ?? '#');
    $target = (string) ($link['target'] ?? '_self');
    $variant = (string) ($config['variant'] ?? 'primary');
    $size = (string) ($config['size'] ?? 'md');
    $fullWidth = (bool) ($config['full_width'] ?? false);

    $pad = match ($size) { 'sm' => '.4rem .9rem', 'lg' => '.9rem 2rem', default => '.65rem 1.4rem' };
    $styles = match ($variant) {
        'secondary' => 'background:#e2e8f0;color:#0f172a;',
        'outline' => 'background:transparent;color:var(--wb-primary,#4f46e5);border:2px solid var(--wb-primary,#4f46e5);',
        default => 'background:var(--wb-primary,#4f46e5);color:#fff;',
    };
@endphp
<div class="wb-button-wrap" style="{{ $fullWidth ? '' : 'text-align:left;' }}">
    <a href="{{ $url }}" target="{{ $target }}" class="wb-btn wb-btn--{{ $variant }}" style="display:{{ $fullWidth ? 'block' : 'inline-block' }};text-align:center;padding:{{ $pad }};border-radius:.5rem;font-weight:600;text-decoration:none;{{ $styles }}">{{ $label }}</a>
</div>
