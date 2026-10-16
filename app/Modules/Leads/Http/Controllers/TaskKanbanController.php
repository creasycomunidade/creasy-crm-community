<?php

namespace App\Modules\Leads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Leads\Models\TaskBoard;
use App\Modules\Leads\Models\TaskCard;
use App\Modules\Leads\Models\TaskChecklistItem;
use App\Modules\Leads\Models\TaskColumn;
use App\Modules\Leads\Services\TaskBoardService;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskKanbanController extends Controller
{
    public function __construct(
        private readonly TaskBoardService $boards,
        private readonly StorageManager $storage,
    ) {}

    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $default = $this->boards->defaultFor($workspaceId);
        $board = $request->filled('board')
            ? TaskBoard::where('workspace_id', $workspaceId)->where('uuid', $request->string('board')->toString())->firstOrFail()
            : $default;
        $board->load([
            'columns.tasks' => fn ($query) => $query->with([
                'checklist',
                'assignedUser:id,name',
                'creator:id,name',
                'sourceConversation.contact:id,first_name,last_name,phone_e164',
            ]),
        ]);

        return Inertia::render('CRM/Tasks/Index', [
            'board' => $board,
            'boards' => TaskBoard::where('workspace_id', $workspaceId)->orderBy('position')->orderBy('name')->get(['uuid', 'name', 'is_default', 'position']),
            'teamMembers' => User::forWorkspace($workspaceId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeFromMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorise($request, $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);

        $board = $this->boards->defaultFor($conversation->workspace_id);
        $data = $this->validateTask($request, $board);
        $contact = $conversation->contact;
        $fallback = trim((string) $message->body);
        $fallback = $fallback !== '' ? mb_substr($fallback, 0, 120) : 'Tarefa da conversa';

        $task = $this->createTask(
            $request,
            $board,
            $data,
            $data['title'] ?: $fallback,
            $conversation->id,
            $message->id,
        );

        return response()->json([
            'message' => 'Mensagem transformada em tarefa.',
            'task' => $this->loadTask($task),
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $board = $this->boardForRequest($request, $workspaceId);
        $data = $this->validateTask($request, $board);
        $task = $this->createTask($request, $board, $data, $data['title'] ?: 'Nova tarefa');

        return response()->json(['task' => $this->loadTask($task)], 201);
    }

    public function update(Request $request, TaskCard $task): JsonResponse
    {
        $this->authorise($request, $task);
        $board = TaskBoard::where('workspace_id', $task->workspace_id)->findOrFail($task->board_id);
        $data = $this->validateTask($request, $board, $task);
        $column = TaskColumn::where('board_id', $board->id)->findOrFail($data['column_id']);
        $this->validateAssignee($board->workspace_id, $data['assigned_user_id'] ?? null);
        $attachments = array_merge($task->attachments ?? [], $this->storeImages($request));

        DB::transaction(function () use ($task, $data, $column, $attachments): void {
            $task->update([
                'column_id' => $column->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'due_at' => $data['due_at'] ?? null,
                'attachments' => $attachments,
            ]);
            $this->replaceChecklist($task, $data['checklist'] ?? []);
        });

        return response()->json(['task' => $this->loadTask($task->fresh())]);
    }

    public function move(Request $request, TaskCard $task): JsonResponse
    {
        $this->authorise($request, $task);
        $data = $request->validate(['column_id' => ['required', 'integer']]);
        $column = TaskColumn::where('workspace_id', $task->workspace_id)
            ->where('board_id', $task->board_id)
            ->findOrFail($data['column_id']);
        $task->update([
            'column_id' => $column->id,
            'position' => (int) TaskCard::where('column_id', $column->id)->max('position') + 1,
        ]);

        return response()->json(['task' => $task->fresh()]);
    }

    public function toggleChecklist(Request $request, TaskCard $task, TaskChecklistItem $item): JsonResponse
    {
        $this->authorise($request, $task);
        abort_unless((int) $item->task_id === (int) $task->id, 404);
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $item->update(['completed' => $data['completed']]);

        return response()->json(['item' => $item->fresh()]);
    }

    public function destroy(Request $request, TaskCard $task): JsonResponse
    {
        $this->authorise($request, $task);
        $task->checklist()->delete();
        $task->delete();

        return response()->json([], 204);
    }

    public function storeColumn(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $board = $this->boardForRequest($request, $workspaceId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $column = $board->columns()->create($data + [
            'workspace_id' => $workspaceId,
            'position' => (int) $board->columns()->max('position') + 1,
        ]);

        return response()->json(['column' => $column], 201);
    }

    public function storeBoard(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('task_boards', 'name')->where('workspace_id', $workspaceId)],
        ]);

        return response()->json(['board' => $this->boards->create($workspaceId, trim($data['name']))], 201);
    }

    public function updateBoard(Request $request, TaskBoard $board): JsonResponse
    {
        $this->authorise($request, $board);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('task_boards', 'name')->where('workspace_id', $board->workspace_id)->ignore($board->id)],
        ]);
        $board->update(['name' => trim($data['name'])]);

        return response()->json(['board' => $board->fresh()]);
    }

    public function destroyBoard(Request $request, TaskBoard $board): JsonResponse
    {
        $this->authorise($request, $board);
        abort_if($board->is_default, 422, 'O grupo principal não pode ser excluído.');

        DB::transaction(function () use ($board): void {
            $taskIds = TaskCard::where('board_id', $board->id)->pluck('id');
            TaskChecklistItem::whereIn('task_id', $taskIds)->delete();
            TaskCard::where('board_id', $board->id)->delete();
            $board->columns()->delete();
            $board->delete();
        });

        return response()->json([], 204);
    }

    public function updateColumn(Request $request, TaskColumn $column): JsonResponse
    {
        $this->authorise($request, $column);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $column->update($data);

        return response()->json(['column' => $column->fresh()]);
    }

    private function validateTask(Request $request, TaskBoard $board, ?TaskCard $task = null): array
    {
        return $request->validate([
            'column_id' => [
                'required', 'integer',
                Rule::exists('task_columns', 'id')->where('board_id', $board->id),
            ],
            'title' => [$task ? 'required' : 'nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'assigned_user_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*' => ['required', 'string', 'max:255'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    private function createTask(
        Request $request,
        TaskBoard $board,
        array $data,
        string $title,
        ?int $conversationId = null,
        ?int $messageId = null,
    ): TaskCard {
        $column = TaskColumn::where('board_id', $board->id)->findOrFail($data['column_id']);
        $this->validateAssignee($board->workspace_id, $data['assigned_user_id'] ?? null);

        return DB::transaction(function () use ($request, $board, $column, $data, $title, $conversationId, $messageId): TaskCard {
            $task = TaskCard::create([
                'workspace_id' => $board->workspace_id,
                'board_id' => $board->id,
                'column_id' => $column->id,
                'source_message_id' => $messageId,
                'source_conversation_id' => $conversationId,
                'created_by' => $request->user()->id,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'due_at' => $data['due_at'] ?? null,
                'attachments' => $this->storeImages($request),
                'position' => (int) TaskCard::where('column_id', $column->id)->max('position') + 1,
            ]);
            $this->replaceChecklist($task, $data['checklist'] ?? []);

            return $task;
        });
    }

    private function replaceChecklist(TaskCard $task, array $items): void
    {
        $task->checklist()->delete();
        foreach (array_values(array_filter(array_map('trim', $items))) as $position => $title) {
            $task->checklist()->create(compact('title', 'position'));
        }
    }

    private function storeImages(Request $request): array
    {
        $result = [];
        foreach ($request->file('images', []) as $file) {
            $path = $this->storage->prefixedPath('task-attachments/'.$file->hashName());
            $this->storage->disk()->putFileAs(dirname($path), $file, basename($path));
            $result[] = [
                'path' => $path,
                'url' => $this->storage->disk()->url($path),
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $result;
    }

    private function loadTask(TaskCard $task): TaskCard
    {
        return $task->load([
            'checklist',
            'assignedUser:id,name',
            'creator:id,name',
            'sourceConversation.contact:id,first_name,last_name,phone_e164',
        ]);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function boardForRequest(Request $request, int $workspaceId): TaskBoard
    {
        if (! $request->filled('board_uuid')) {
            return $this->boards->defaultFor($workspaceId);
        }

        return TaskBoard::where('workspace_id', $workspaceId)
            ->where('uuid', $request->string('board_uuid')->toString())
            ->firstOrFail();
    }

    private function authorise(Request $request, object $model): void
    {
        abort_unless((int) $model->workspace_id === $this->workspaceId($request), 403);
    }

    private function validateAssignee(int $workspaceId, ?int $userId): void
    {
        if ($userId !== null) {
            abort_unless(User::forWorkspace($workspaceId)->whereKey($userId)->exists(), 422, 'Responsável inválido.');
        }
    }
}
