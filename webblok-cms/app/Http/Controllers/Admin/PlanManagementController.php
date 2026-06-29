<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Super-admin plan management: list, create and toggle subscription plans.
 */
final class PlanManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', [
            'plans' => Plan::query()->orderBy('price_monthly')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'max_pages' => ['required', 'integer', 'min:1'],
            'max_bloks' => ['required', 'integer', 'min:1'],
            'max_api_rpm' => ['required', 'integer', 'min:1'],
        ]);

        $displayName = is_string($data['display_name']) ? $data['display_name'] : 'Plan';
        $monthly = $this->numeric($data, 'price_monthly');

        Plan::query()->create([
            'id' => (string) Str::uuid(),
            'name' => Str::slug($displayName).'-'.Str::lower(Str::random(4)),
            'display_name' => $displayName,
            'max_pages' => (int) $this->numeric($data, 'max_pages'),
            'max_bloks' => (int) $this->numeric($data, 'max_bloks'),
            'max_locales' => 3,
            'max_media_mb' => 1024,
            'max_exports' => 10,
            'max_api_rpm' => (int) $this->numeric($data, 'max_api_rpm'),
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'features' => [],
            'is_active' => true,
        ]);

        return back()->with('status', 'Plan created.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function numeric(array $data, string $key): float
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function toggle(string $planId): RedirectResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($planId);
        $plan->forceFill(['is_active' => ! $plan->is_active])->save();

        return back()->with('status', 'Plan updated.');
    }
}
