{{--
  Blade Component: ProgressCard
  
  Installation:
  php artisan make:component ProgressCard
  
  Usage:
  <x-progress-card 
    title="Enhancement Level"
    subtitle="Your Cognitive Evolution"
    progressLabel="Overall Progress"
    :value="67"
    fillClass="primary"
    milestone="Level 8 – Quantum Thinker"
  />
  
  Props:
  - title: string (required) - Card title
  - subtitle: string (required) - Card subtitle
  - progressLabel: string (required) - Progress bar label
  - value: int (required) - Progress percentage (0-100)
  - fillClass: string (optional) - CSS class for progress bar color
  - milestone: string (required) - Next milestone text
  
  Data source: slides/data/slide-{num}.{lang}.json
  JSON fields: progress, milestone
  
  JS Animation:
  Add: document.querySelector('[data-progress]').style.width = value + '%';
--}}

<div class="progress-card fade-in">
  <div class="progress-card-title">{{ $title }}</div>
  <div class="progress-card-subtitle">{{ $subtitle }}</div>
  
  <div class="progress-row">
    <div class="progress-row-header">
      <span class="progress-label">{{ $progressLabel }}</span>
      <span class="progress-value">{{ $value }}%</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill {{ $fillClass ?? '' }}" 
           data-progress="{{ $value }}" 
           style="width:0%"></div>
    </div>
  </div>
  
  <div style="margin-top:10px;font-size:12px;color:rgba(255,255,255,0.5)">
    🎯 {{ $milestone }}
  </div>
</div>
