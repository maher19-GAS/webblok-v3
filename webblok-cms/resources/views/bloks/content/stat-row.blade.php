@php
    /** @var array<string,mixed> $config */
    $title = (string) ($config['title'] ?? '');
    $stats = is_array($config['stats'] ?? null) ? $config['stats'] : [];
    $bg = (string) ($config['bg'] ?? '#f1f5f9');
    $columns = (int) ($config['columns'] ?? 3);
@endphp
<div class="wb-stat-row" style="background:{{ $bg }};padding:3rem 1.5rem;border-radius:.75rem;">
    @if ($title !== '')
        <h2 style="text-align:center;margin:0 0 2rem;" data-field="title">{{ $title }}</h2>
    @endif
    <div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;text-align:center;">
        @foreach ($stats as $stat)
            @php
                $stat = is_array($stat) ? $stat : [];
                $value = (string) ($stat['value'] ?? '');
                $label = (string) ($stat['label'] ?? '');
            @endphp
            <div class="wb-stat">
                <div style="font-size:2.25rem;font-weight:800;color:var(--wb-primary,#4f46e5);">{{ $value }}</div>
                <div style="opacity:.7;">{{ $label }}</div>
            </div>
        @endforeach
    </div>
</div>
