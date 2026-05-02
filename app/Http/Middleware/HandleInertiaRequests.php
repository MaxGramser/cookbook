<?php

namespace App\Http\Middleware;

use App\Models\CookSession;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'activeCookSession' => fn () => $this->activeCookSession($request),
        ];
    }

    /**
     * @return array{id: int, recipe_title: string, started_at: string, paused_at: string|null}|null
     */
    private function activeCookSession(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $session = CookSession::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->with('recipe:id,title')
            ->latest('started_at')
            ->first();

        if ($session === null || $session->recipe === null) {
            return null;
        }

        return [
            'id' => $session->id,
            'recipe_title' => $session->recipe->title,
            'started_at' => $session->started_at?->toIso8601String() ?? '',
            'paused_at' => $session->paused_at?->toIso8601String(),
        ];
    }
}
