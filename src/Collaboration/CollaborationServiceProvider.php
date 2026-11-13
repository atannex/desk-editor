<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Collaboration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the presence channel used for real-time co-editing.
 */
class CollaborationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('desk-editor.collaboration.enabled')) {
            return;
        }

        $prefix = (string) config('desk-editor.collaboration.channel_prefix', 'desk.document');

        Broadcast::channel($prefix.'.{document}', function ($user, string $document) {
            $authorize = config('desk-editor.collaboration.authorize');
            if (is_callable($authorize) && ! $authorize($user, $document)) {
                return false;
            }

            return static::member($user);
        });
    }

    /**
     * @return array{id: string, name: string, color: string}
     */
    public static function member(mixed $user): array
    {
        $id = $user instanceof Model ? (string) $user->getKey() : (string) ($user->id ?? '0');
        $name = $user instanceof Model ? (string) ($user->getAttribute('name') ?? $user->getAttribute('email') ?? 'User') : 'User';

        return ['id' => $id, 'name' => $name, 'color' => static::color($id.$name)];
    }

    public static function color(string $seed): string
    {
        $palette = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#3b82f6'];

        return $palette[abs(crc32($seed)) % count($palette)];
    }

    /**
     * Channel-safe form of a document key such as "article:12" → "article-12".
     */
    public static function channelKey(string $document): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '-', $document);
    }
}
