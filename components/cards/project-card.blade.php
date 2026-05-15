{{--
  Blade Component: ProjectCard
  
  Installation:
  php artisan make:component ProjectCard
  
  Usage:
  <x-project-card 
    title="DVM Cloud Architecture"
    :team="12"
    deadline="45 days"
    :match="94"
    :tags="['Security', 'Cloud', 'AI']"
    :progress="32"
    ctc="2,500 CTC/mo"
    cta="Apply Now"
  />
  
  Props:
  - title: string (required) - Project title
  - team: int (required) - Number of team members
  - deadline: string (required) - Project deadline
  - match: int (required) - Match percentage (0-100)
  - tags: array (required) - Array of tag strings
  - progress: int (required) - Progress percentage (0-100)
  - ctc: string (required) - CTC reward amount
  - cta: string (required) - Call-to-action button text
  
  Data source: slides/data/slide-{num}.{lang}.json
  JSON fields: projects[].{title, team, deadline, match, tags, progress, ctc}
--}}

<div class="project-card fade-in">
  <div class="project-card-top">
    <div>
      <div class="project-card-title">{{ $title }}</div>
      <div class="project-card-meta" style="margin-top:4px;margin-bottom:0">
        <span>👥 {{ $team }} members</span>
        <span>⏰ {{ $deadline }}</span>
      </div>
    </div>
    <div class="project-match">{{ $match }}%</div>
  </div>
  
  <!-- Tags -->
  <div class="filter-bar" style="margin-bottom:10px;flex-wrap:wrap">
    @foreach($tags as $tag)
      <span class="chip">{{ $tag }}</span>
    @endforeach
  </div>
  
  <!-- Progress -->
  <div style="margin-bottom:12px">
    <div class="progress-row-header" style="margin-bottom:4px">
      <span style="font-size:11px;color:rgba(255,255,255,0.5)">Progress</span>
      <span class="progress-value">{{ $progress }}%</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill" 
           data-progress="{{ $progress }}" 
           style="width:0%"></div>
    </div>
  </div>
  
  <!-- Footer -->
  <div style="display:flex;align-items:center;justify-content:space-between">
    <span class="project-ctc">{{ $ctc }}</span>
    <button class="btn btn-primary btn-sm">{{ $cta }}</button>
  </div>
</div>
