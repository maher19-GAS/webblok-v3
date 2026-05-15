{{--
  Blade Component: IconButton
  
  Installation:
  php artisan make:component IconButton
  
  Usage:
  <x-icon-button 
    icon="⚙️"
    tooltip="Settings"
    label="Settings"
  />
  
  Props:
  - icon: string (required) - Icon character or emoji
  - tooltip: string (optional) - Tooltip text on hover
  - label: string (required) - Accessible label for screen readers
  
  Data source: N/A (generic UI component)
--}}

<button class="icon-btn" 
        data-tooltip="{{ $tooltip ?? '' }}" 
        aria-label="{{ $label }}">
  {{ $icon }}
</button>
