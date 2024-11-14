<?php

namespace Miotoloji\AutoCrud;

use Illuminate\Support\ServiceProvider;
use Miotoloji\AutoCrud\Console\Commands\Module\Create;
use Illuminate\Support\Facades\Route;

class AutoCrudServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Create::class
            ]);
        }
        foreach (glob(base_path('app/*/routes/api.php')) as $filename) {
              Route::prefix('api')->group($filename);
        }
    }
}
