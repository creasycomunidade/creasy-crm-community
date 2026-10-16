<?php
namespace App\Modules\AI;
use Illuminate\Support\ServiceProvider;
class AIServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        // Existing Community AI migrations remain in database/community-schema/AI.
    }
}
