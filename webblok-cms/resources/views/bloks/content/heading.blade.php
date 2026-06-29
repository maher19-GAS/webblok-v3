@php
    /** @var array<string,mixed> $config */
    $text = (string) ($config['text'] ?? '');
    $level = (string) ($config['level'] ?? 'h2');
    $level = in_array($level, ['h1', 'h2', 'h3', 'h4'], true) ? $level : 'h2';
    $align = (string) ($config['align'] ?? 'left');
    $color = (string) ($config['color'] ?? '#0f172a');
@endphp
<{{ $level }} class="wb-heading" data-field="text" style="text-align:{{ $align }};color:{{ $color }};margin:0 0 .5rem;">{{ $text }}</{{ $level }}>
