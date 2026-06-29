@php
    /** @var array<string,mixed> $config */
    $heading = (string) ($config['heading'] ?? '');
    $features = is_array($config['features'] ?? null) ? $config['features'] : [];
    $layout = (string) ($config['layout'] ?? 'grid');
    $columns = (int) ($config['columns'] ?? 3);
    $template = $layout === 'list' ? '1fr' : "repeat({$columns},1fr)";
@endphp
<div class="wb-feature-grid" style="padding:2rem 0;">
    @if ($heading !== '')
        <h2 style="text-align:center;margin:0 0 2rem;" data-field="heading">{{ $heading }}</h2>
    @endif
    <div style="display:grid;grid-template-columns:{{ $template }};gap:1.5rem;">
        @foreach ($features as $feature)
            @php
                $feature = is_array($feature) ? $feature : [];
                $title = (string) ($feature['title'] ?? '');
                $body = (string) ($feature['body'] ?? '');
            @endphp
            <div class="wb-feature" style="padding:1.5rem;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;">
                <h3 style="margin:0 0 .5rem;font-size:1.15rem;">{{ $title }}</h3>
                <p style="margin:0;opacity:.75;">{{ $body }}</p>
            </div>
        @endforeach
    </div>
</div>
