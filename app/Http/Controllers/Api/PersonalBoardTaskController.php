<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalBoardTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalBoardTaskController extends Controller
{
    private const STATUSES = ['backlog', 'todo', 'doing', 'waiting', 'done'];

    public function index(Request $request): JsonResponse
    {
        $items = PersonalBoardTask::query()->where('user_id', $request->user()->id)->orderBy('position')->latest('updated_at')->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $task = PersonalBoardTask::query()->create($data + [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'position' => PersonalBoardTask::query()->where('user_id', $request->user()->id)->where('status', $data['status'] ?? 'backlog')->max('position') + 1,
        ]);

        return response()->json(['data' => $task], 201);
    }

    public function update(Request $request, PersonalBoardTask $personalBoardTask): JsonResponse
    {
        $this->authorizeOwner($request, $personalBoardTask);
        $personalBoardTask->update($this->validated($request, true));

        return response()->json(['data' => $personalBoardTask->fresh()]);
    }

    public function destroy(Request $request, PersonalBoardTask $personalBoardTask): JsonResponse
    {
        $this->authorizeOwner($request, $personalBoardTask);
        $personalBoardTask->delete();

        return response()->json(['message' => 'Tarefa removida.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : ['required'];

        return $request->validate([
            'title' => [...$sometimes, 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => [...$sometimes, Rule::in(self::STATUSES)],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'due_date' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    private function authorizeOwner(Request $request, PersonalBoardTask $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 404);
    }
}
