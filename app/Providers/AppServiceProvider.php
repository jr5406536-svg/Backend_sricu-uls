<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Keyword;
use App\Models\License;
use App\Policies\CatalogPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([Author::class, Category::class, Collection::class, Keyword::class, License::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
    }
}
