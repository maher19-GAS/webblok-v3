@php
    /** @var array<string,mixed> $config */
    $ratio = (string) ($config['ratio'] ?? '1-1');
    $gap = (int) ($config['gap'] ?? 24);
    $stack = (bool) ($config['stack_on_mobile'] ?? true);
    $childrenHtml = (string) ($config['__children_html'] ?? '');
    $template = match ($ratio) {
        '1-2' => '1fr 2fr',
        '2-1' => '2fr 1fr',
        default => '1fr 1fr',
    };
@endphp
<div class="wb-columns {{ $stack ? 'wb-columns--stack' : '' }}" style="display:grid;grid-template-columns:{{ $template }};gap:{{ $gap }}px;">
    {!! $childrenHtml !!}
</div>
