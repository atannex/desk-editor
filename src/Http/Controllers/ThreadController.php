<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Models\DeskThread;
use Atannex\DeskEditor\Models\DeskThreadMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Editorial comment threads anchored to text in a document (e.g. "article:12").
 */
class ThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $document = (string) $request->validate(['document' => ['required', 'string', 'max:191']])['document'];

        $threads = DeskThread::query()
            ->where('document', $document)
            ->with(['messages.user', 'user', 'resolver'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $threads->map(fn (DeskThread $t) => $this->present($t, $request))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document' => ['required', 'string', 'max:191'],
            'anchor' => ['required', 'string', 'max:64', 'regex:/^[\w-]+$/', 'unique:desk_threads,anchor'],
            'quote' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread = new DeskThread(['document' => $data['document'], 'anchor' => $data['anchor'], 'quote' => isset($data['quote']) ? Str::limit($data['quote'], 500) : null]);
        $this->stamp($thread, $request, 'user');
        $thread->save();
        $this->addMessage($thread, $request, $data['body']);

        return response()->json(['data' => $this->present($thread->fresh(['messages.user', 'user', 'resolver']) ?? $thread, $request)], 201);
    }

    public function reply(Request $request, DeskThread $thread): JsonResponse
    {
        $body = (string) $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        $this->addMessage($thread, $request, $body);

        return response()->json(['data' => $this->present($thread->fresh(['messages.user', 'user', 'resolver']) ?? $thread, $request)]);
    }

    public function resolve(Request $request, DeskThread $thread): JsonResponse
    {
        $resolved = $request->boolean('resolved', true);
        $thread->resolved_at = $resolved ? now() : null;
        if ($resolved) {
            $this->stamp($thread, $request, 'resolver');
        } else {
            $thread->resolver()->dissociate();
        }
        $thread->save();

        return response()->json(['data' => $this->present($thread->fresh(['messages.user', 'user', 'resolver']) ?? $thread, $request)]);
    }

    public function destroy(Request $request, DeskThread $thread): Response
    {
        abort_unless($this->owns($thread->user_type, $thread->user_id, $request), 403);
        // Explicit for databases without enforced foreign keys.
        $thread->messages()->delete();
        $thread->delete();

        return response()->noContent();
    }

    public function destroyMessage(Request $request, DeskThreadMessage $message): Response
    {
        abort_unless($this->owns($message->user_type, $message->user_id, $request), 403);
        $message->delete();

        return response()->noContent();
    }

    private function addMessage(DeskThread $thread, Request $request, string $body): void
    {
        $message = new DeskThreadMessage(['body' => trim($body)]);
        $message->thread()->associate($thread);
        $this->stamp($message, $request, 'user');
        $message->save();
        $thread->touch();
    }

    private function stamp(Model $model, Request $request, string $relation): void
    {
        $user = $request->user();
        if ($user instanceof Model) {
            $model->setAttribute("{$relation}_type", $user->getMorphClass());
            $model->setAttribute("{$relation}_id", $user->getKey());
        }
    }

    private function owns(mixed $type, mixed $id, Request $request): bool
    {
        $user = $request->user();

        return $user instanceof Model && $type === $user->getMorphClass() && (string) $id === (string) $user->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DeskThread $thread, Request $request): array
    {
        $name = fn (?Model $user) => $user ? (string) ($user->getAttribute('name') ?? $user->getAttribute('email') ?? 'User') : __('desk-editor::editor.someone');

        return [
            'id' => $thread->id,
            'anchor' => $thread->anchor,
            'quote' => $thread->quote,
            'resolved' => $thread->resolved_at !== null,
            'resolved_at' => $thread->resolved_at?->toIso8601String(),
            'resolved_by' => $thread->resolved_at ? $name($thread->resolver) : null,
            'author' => $name($thread->user),
            'can_delete' => $this->owns($thread->getAttribute('user_type'), $thread->getAttribute('user_id'), $request),
            'created_at' => $thread->created_at?->toIso8601String(),
            'messages' => $thread->messages->map(fn (DeskThreadMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'author' => $name($m->user),
                'mine' => $this->owns($m->user_type, $m->user_id, $request),
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
