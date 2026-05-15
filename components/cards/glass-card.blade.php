{{--
  Blade Component: GlassCard
  
  Installation:
  php artisan make:component GlassCard
  
  Usage:
  <x-glass-card title="Card Title" icon="🎯">
    <p>Card content goes here...</p>
  </x-glass-card>
  
  Props:
  - title: string (required) - Card header title
  - icon: string (optional) - Emoji or icon character
  - $slot: mixed (required) - Card body content
  
  Data source: Flexible - can be used for any card-based UI
  JSON fields: N/A (generic component)
--}}

<div class="glass-card fade-in">
  <div class="glass-card-header">
    <span class="glass-card-title">{{ $title }}</span>
    @if(isset($icon))
      <span class="text-muted" style="font-size:18px">{{ $icon }}</span>
    @endif
  </div>
  <div class="glass-card-body">
    {{ $slot }}
  </div>
</div>
