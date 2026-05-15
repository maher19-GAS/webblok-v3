{{--
  Blade Component: SlideContainer
  
  Installation:
  php artisan make:component SlideContainer
  
  Usage:
  <x-slide-container>
    <!-- Slide content goes here -->
  </x-slide-container>
  
  Props:
  - $slot: mixed (required) - Slide body content
  
  Features:
  - Background gradient layer
  - Skeleton loading state (shown by default)
  - Real content area (hidden until loaded)
  - Footer navigation placeholder
  
  JS Integration:
  Toggle visibility: document.getElementById('skeleton').style.display = 'none';
  Show content: document.getElementById('content').style.display = 'flex';
--}}

<div class="slide-container">
  <div class="slide-bg"></div>
  
  <!-- Skeleton Loading State -->
  <div id="skeleton" class="slide-inner">
    <div class="skeleton-layout">
      <div class="skeleton skeleton-title" style="width:60%"></div>
      <div class="skeleton skeleton-text" style="width:40%"></div>
      <div class="skeleton skeleton-card" style="height:100px;border-radius:16px"></div>
      <div class="skeleton-cards-grid">
        <div class="skeleton skeleton-card" style="height:80px;border-radius:12px"></div>
        <div class="skeleton skeleton-card" style="height:80px;border-radius:12px"></div>
      </div>
      <div class="skeleton skeleton-card" style="height:120px;border-radius:16px"></div>
    </div>
  </div>
  
  <!-- Real Content (hidden by default) -->
  <div id="content" class="slide-content" style="display:none;flex-direction:column">
    {{ $slot }}
  </div>
  
  <!-- Footer Navigation Placeholder -->
  <div id="footer-nav"></div>
</div>
