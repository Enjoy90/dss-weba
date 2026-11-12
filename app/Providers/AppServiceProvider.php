<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
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
            'evaluations' => Evaluation::class,
            'assessments' => Assessment::class,
        ]);

        Password::defaults(fn (): Password => Password::min(8));

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                ActivityLog::record('auth.login', null, null, null, $event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                ActivityLog::record('auth.logout', null, null, null, $event->user);
            }
        });
    }
}
