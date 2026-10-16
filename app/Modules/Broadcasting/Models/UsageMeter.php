<?php

namespace App\Modules\Broadcasting\Models;

use Illuminate\Database\Eloquent\Model;

class UsageMeter extends Model
{
    protected $table = 'usage_meters';

    protected $fillable = ['workspace_id', 'metric', 'period', 'value'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'period' => 'integer'];
    }

    public static function track(int $workspaceId, string $metric, int $by = 1): void
    {
        $period = (int) now()->format('Ym');
        static::updateOrCreate(
            ['workspace_id' => $workspaceId, 'metric' => $metric, 'period' => $period],
            ['value' => 0]
        );
        static::where('workspace_id', $workspaceId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->increment('value', $by);
    }

    public static function current(int $workspaceId, string $metric): int
    {
        $period = (int) now()->format('Ym');

        return (int) static::where('workspace_id', $workspaceId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('value');
    }

    public static function currentForWorkspaces(array $workspaceIds, string $metric): int
    {
        if ($workspaceIds === []) {
            return 0;
        }

        $period = (int) now()->format('Ym');

        return (int) static::whereIn('workspace_id', $workspaceIds)
            ->where('metric', $metric)
            ->where('period', $period)
            ->sum('value');
    }
}
