@php
    /** @var array<string,mixed> $config */
    $code = (string) ($config['code'] ?? '');
    $ratio = (int) ($config['ratio'] ?? 0);
    // HTML embed is intentionally raw; the editor restricts who can author it
    // and the ArtifactValidator/sanitiser gates dangerous content upstream.
@endphp
@if ($ratio > 0)
    <div class="wb-embed" style="position:relative;width:100%;padding-top:{{ $ratio }}%;">
        <div style="position:absolute;inset:0;">{!! $code !!}</div>
    </div>
@else
    <div class="wb-embed">{!! $code !!}</div>
@endif
