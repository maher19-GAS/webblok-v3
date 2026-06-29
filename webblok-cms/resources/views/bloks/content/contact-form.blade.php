@php
    /** @var array<string,mixed> $config */
    $heading = (string) ($config['heading'] ?? 'Get in touch');
    $fields = is_array($config['fields'] ?? null) ? $config['fields'] : ['name', 'email', 'message'];
    $consent = (bool) ($config['consent_required'] ?? true);
    $submitLabel = (string) ($config['submit_label'] ?? 'Send');
@endphp
<form class="wb-contact-form" method="post" action="#" style="max-width:560px;margin:0 auto;display:grid;gap:1rem;">
    @if ($heading !== '')<h2 style="margin:0;">{{ $heading }}</h2>@endif
    @if (in_array('name', $fields, true))
        <label style="display:grid;gap:.25rem;">Name<input type="text" name="name" style="padding:.6rem;border:1px solid #cbd5e1;border-radius:.5rem;"></label>
    @endif
    @if (in_array('email', $fields, true))
        <label style="display:grid;gap:.25rem;">Email<input type="email" name="email" style="padding:.6rem;border:1px solid #cbd5e1;border-radius:.5rem;"></label>
    @endif
    @if (in_array('phone', $fields, true))
        <label style="display:grid;gap:.25rem;">Phone<input type="tel" name="phone" style="padding:.6rem;border:1px solid #cbd5e1;border-radius:.5rem;"></label>
    @endif
    @if (in_array('message', $fields, true))
        <label style="display:grid;gap:.25rem;">Message<textarea name="message" rows="4" style="padding:.6rem;border:1px solid #cbd5e1;border-radius:.5rem;"></textarea></label>
    @endif
    @if ($consent)
        <label style="display:flex;gap:.5rem;align-items:center;font-size:.875rem;"><input type="checkbox" name="consent" required> I agree to be contacted.</label>
    @endif
    <button type="submit" style="background:var(--wb-primary,#4f46e5);color:#fff;padding:.75rem;border:0;border-radius:.5rem;font-weight:600;cursor:pointer;">{{ $submitLabel }}</button>
</form>
