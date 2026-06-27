@php
    /** @var array<string,mixed> $config */
    $images = is_array($config['images'] ?? null) ? $config['images'] : [];
    $columns = (int) ($config['columns'] ?? 3);
    $gap = (int) ($config['gap'] ?? 12);
@endphp
<div class="wb-gallery" style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }}px;">
    @foreach ($images as $image)
        @php $url = is_string($image) ? $image : (is_array($image) ? (string) ($image['src'] ?? '') : ''); @endphp
        @if ($url !== '')
            <img src="{{ $url }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:.5rem;aspect-ratio:3/2;">
        @endif
    @endforeach
</div>
