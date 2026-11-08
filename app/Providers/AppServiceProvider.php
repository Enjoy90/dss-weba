<?php

namespace App\Providers;

use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'production_orders' => ProductionOrder::class,
            'criteria' => Criterion::class,
        ]);

        Password::defaults(fn (): Password => Password::min(8));

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
