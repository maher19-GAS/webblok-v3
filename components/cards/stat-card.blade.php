{{--
  Blade Component: StatCard
  
  Installation:
  php artisan make:component StatCard
  
  Usage:
  <x-stat-card 
    icon="💰"
    label="CTC Balance"
    :value="12450"
    trend="+12%"
    trendClass="positive"
    trendIcon="↑"
  />
  
  Props:
  - icon: string (required) - Emoji icon
  - label: string (required) - Stat label
  - value: mixed (required) - Stat value (number or formatted string)
  - trend: string (optional) - Trend percentage or text
  - trendClass: string (optional) - CSS class: 'positive', 'negative', or 'neutral'
  - trendIcon: string (optional) - Trend icon (↑, ↓, →)
  
  Data source: slides/data/slide-{num}.{lang}.json
  JSON fields: stats.{field}
--}}

<div class="stat-card fade-in">
  <div class="stat-icon">{{ $icon }}</div>
  <div class="stat-label">{{ $label }}</div>
  <div class="stat-value text-mono">{{ $value }}</div>
  @if(isset($trend))
    <div class="stat-trend {{ $trendClass ?? '' }}">
      <span>{{ $trendIcon ?? '' }}</span>
      <span>{{ $trend }}</span>
    </div>
  @endif
</div>
