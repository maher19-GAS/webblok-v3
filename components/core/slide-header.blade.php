{{--
  Blade Component: SlideHeader
  
  Installation:
  php artisan make:component SlideHeader
  
  Usage:
  <x-slide-header :title="$slide->title" :subtitle="$slide->subtitle" :icon="$slide->icon" />
  
  Props:
  - title: string (required) - Main heading
  - subtitle: string (required) - Subheading text
  - icon: string (required) - Emoji or icon character
  
  Data source: slides/data/slide-{num}.{lang}.json
  JSON fields: title, subtitle, icon
--}}

<div class="slide-header fade-in">
  <div class="slide-header-top">
    <div>
      <h1>{{ $title }}</h1>
      <h2>{{ $subtitle }}</h2>
    </div>
    <div class="header-icon-btn">{{ $icon }}</div>
  </div>
</div>
