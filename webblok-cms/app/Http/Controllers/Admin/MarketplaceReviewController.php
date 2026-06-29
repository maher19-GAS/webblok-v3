<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\MarketplaceItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Super-admin marketplace review queue: approve (activate), reject (deactivate)
 * and feature/unfeature themes and templates — including community submissions.
 */
final class MarketplaceReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.marketplace', [
            'pending' => MarketplaceItem::query()
                ->where('source', 'community')
                ->where('is_active', false)
                ->orderByDesc('created_at')
                ->get(),
            'live' => MarketplaceItem::query()
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function approve(string $itemId): RedirectResponse
    {
        MarketplaceItem::query()->findOrFail($itemId)
            ->forceFill(['is_active' => true])->save();

        return back()->with('status', 'Item approved and published.');
    }

    public function reject(string $itemId): RedirectResponse
    {
        MarketplaceItem::query()->findOrFail($itemId)
            ->forceFill(['is_active' => false])->save();

        return back()->with('status', 'Item rejected.');
    }

    public function toggleFeatured(string $itemId): RedirectResponse
    {
        /** @var MarketplaceItem $item */
        $item = MarketplaceItem::query()->findOrFail($itemId);
        $item->forceFill(['is_featured' => ! $item->is_featured])->save();

        return back()->with('status', 'Feature flag updated.');
    }
}
