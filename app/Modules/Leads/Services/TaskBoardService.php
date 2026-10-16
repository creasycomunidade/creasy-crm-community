<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Models\TaskBoard;
use Illuminate\Support\Facades\DB;

class TaskBoardService
{
    private const DEFAULT_COLUMNS = [
        ['name' => 'A fazer', 'color' => '#8b5cf6'],
        ['name' => 'Em andamento', 'color' => '#3b82f6'],
        ['name' => 'Aguardando', 'color' => '#f59e0b'],
        ['name' => 'Concluído', 'color' => '#22c55e'],
    ];

    public function defaultFor(int $workspaceId): TaskBoard
    {
        return DB::transaction(function () use ($workspaceId): TaskBoard {
            $board = TaskBoard::firstOrCreate(
                ['workspace_id' => $workspaceId, 'is_default' => true],
                ['name' => 'Tarefas', 'position' => 0],
            );

            $this->ensureColumns($board);

            return $board->fresh('columns');
        });
    }

    public function create(int $workspaceId, string $name): TaskBoard
    {
        return DB::transaction(function () use ($workspaceId, $name): TaskBoard {
            $board = TaskBoard::create([
                'workspace_id' => $workspaceId,
                'name' => $name,
                'is_default' => false,
                'position' => (int) TaskBoard::where('workspace_id', $workspaceId)->max('position') + 1,
            ]);
            $this->ensureColumns($board);

            return $board->fresh('columns');
        });
    }

    private function ensureColumns(TaskBoard $board): void
    {
        if ($board->columns()->exists()) {
            return;
        }

        foreach (self::DEFAULT_COLUMNS as $position => $column) {
            $board->columns()->create($column + [
                'workspace_id' => $board->workspace_id,
                'position' => $position,
            ]);
        }
    }
}
