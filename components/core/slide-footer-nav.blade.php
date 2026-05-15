{{--
  Blade Component: SlideFooterNav
  
  Installation:
  php artisan make:component SlideFooterNav
  
  Usage:
  <x-slide-footer-nav 
    :labels="['Home', 'Projects', 'Wallet', 'Learn', 'Profile']"
    :badges="[null, 2, null, 3, null]"
    :active="0"
  />
  
  Props:
  - labels: array (required) - Navigation item labels (5 items)
  - badges: array (optional) - Badge numbers for each item (5 items, null if no badge)
  - active: int (optional, default 0) - Index of active navigation item
  
  Data source: Application navigation config
  JSON fields: N/A (static navigation structure)
--}}

@php
  $icons = ['🏠', '🚀', '💎', '🧠', '👤'];
  $labels = $labels ?? ['Home', 'Projects', 'Wallet', 'Learn', 'Profile'];
  $badges = $badges ?? [null, null, null, null, null];
  $active = $active ?? 0;
@endphp

<nav class="slide-footer-nav">
  @foreach($labels as $index => $label)
    <div class="nav-item {{ $index === $active ? 'active' : '' }}">
      <span class="nav-icon">{{ $icons[$index] }}</span>
      <span class="nav-label">{{ $label }}</span>
      @if($badges[$index])
        <span class="nav-badge">{{ $badges[$index] }}</span>
      @endif
    </div>
  @endforeach
</nav>
