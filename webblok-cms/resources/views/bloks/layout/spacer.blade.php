@php
    /** @var array<string,mixed> $config */
    $height = (int) ($config['height'] ?? 48);
@endphp
<div class="wb-spacer" style="height:{{ $height }}px;" aria-hidden="true"></div>
