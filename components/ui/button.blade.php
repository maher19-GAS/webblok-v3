{{--
  Blade Component: Button
  
  Installation:
  php artisan make:component Button
  
  Usage:
  <x-button 
    label="Apply Now"
    variant="btn-primary"
    size="btn-sm"
    icon="🚀"
    extra="custom-class"
  />
  
  Props:
  - label: string (required) - Button text
  - variant: string (optional, default 'btn-primary') - Button style variant
    Options: btn-primary, btn-secondary, btn-ghost, btn-outline
  - size: string (optional, default '') - Button size
    Options: btn-sm, btn-md, btn-lg
  - icon: string (optional) - Leading icon
  - extra: string (optional) - Additional CSS classes
  
  Data source: N/A (generic UI component)
--}}

<button class="btn {{ $variant ?? 'btn-primary' }} {{ $size ?? '' }} {{ $extra ?? '' }}">
  @if(isset($icon))
    {{ $icon }}
  @endif
  {{ $label }}
</button>
