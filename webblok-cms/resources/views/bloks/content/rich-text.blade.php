@php
    /** @var array<string,mixed> $config */
    $body = (string) ($config['body'] ?? '');
    $maxWidth = (int) ($config['max_width'] ?? 720);
    // Body is sanitised at write-time by the Form Wizard / richtext sanitiser.
@endphp
<div class="wb-richtext" data-field="body" style="max-width:{{ $maxWidth }}px;margin:0 auto;line-height:1.7;">
    {!! $body !!}
</div>
