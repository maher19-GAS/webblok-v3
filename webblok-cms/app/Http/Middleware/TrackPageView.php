<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records a lightweight page-view row for analytics after a successful public
 * page render. Failures are swallowed so analytics never break the response.
 */
final class TrackPageView
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $pageId = $request->attributes->get('page_id');

            if (is_string($pageId) && $response->getStatusCode() < 400) {
                PageView::query()->create([
                    'page_id' => $pageId,
                    'locale' => App::getLocale(),
                    'referrer' => $request->headers->get('referer'),
                    'country_code' => $request->headers->get('cf-ipcountry'),
                    'viewed_at' => now()->toISOString(),
                ]);
            }
        } catch (Throwable) {
            // Analytics is best-effort; never surface tracking errors to visitors.
        }

        return $response;
    }
}
