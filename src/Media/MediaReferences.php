<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds where a media item is referenced: article bodies & covers, server
 * drafts, revisions and any extra columns from media.prune.references.
 * Matching is a LIKE on the stored path without extension, so absolute,
 * relative and CDN URLs — and the item's variants/poster — all count.
 */
class MediaReferences
{
    /** @var list<array{type: string, table: string|null, model: class-string<Model>|null, columns: list<string>, title: list<string>, url: (callable(object): ?string)|null}>|null */
    private ?array $sources = null;

    /**
     * Where the item (or one of its children) is used.
     *
     * @return list<array{type: string, id: int|string, title: string, url: string|null}>
     */
    public function usage(DeskMedia $media, int $limit = 50): array
    {
        $needles = $this->needles($media);
        $usage = [];
        $seen = [];

        foreach ($this->sources() as $source) {
            foreach ($this->query($source, $needles)->limit($limit)->get() as $row) {
                $title = '';

                foreach ($source['title'] as $column) {
                    if (filled($row->{$column} ?? null)) {
                        $title = (string) $row->{$column};
                        break;
                    }
                }

                $id = $row->{$source['type'] === 'revision' ? 'desk_article_id' : 'id'} ?? $row->id;
                $key = ($source['type'] === 'revision' ? 'article' : $source['type']).':'.$id;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $usage[] = [
                    'type' => $source['type'],
                    'id' => is_numeric($id) ? (int) $id : (string) $id,
                    'title' => Str::limit($title ?: Str::headline($source['type']).' #'.$id, 120),
                    'url' => $source['url'] ? ($source['url'])($row) : null,
                ];

                if (count($usage) >= $limit) {
                    return $usage;
                }
            }
        }

        return $usage;
    }

    public function isReferenced(DeskMedia $media): bool
    {
        $needles = $this->needles($media);

        foreach ($this->sources() as $source) {
            if ($this->query($source, $needles)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function needles(DeskMedia $media): array
    {
        $needles = [$media->referenceNeedle()];

        foreach ($media->children()->get() as $child) {
            $needles[] = $child->referenceNeedle();
        }

        return array_values(array_unique(array_filter($needles)));
    }

    /**
     * @param  array{type: string, table: string|null, model: class-string<Model>|null, columns: list<string>, title: list<string>, url: (callable(object): ?string)|null}  $source
     * @param  list<string>  $needles
     */
    private function query(array $source, array $needles): Builder
    {
        $query = $source['model'] !== null
            ? $source['model']::query()->withoutGlobalScopes()->toBase()
            : DB::table((string) $source['table']);

        $grammar = $query->getGrammar();

        return $query->where(function ($where) use ($source, $needles, $grammar): void {
            foreach ($source['columns'] as $column) {
                foreach ($needles as $needle) {
                    $where->orWhereRaw($grammar->wrap($column)." LIKE ? ESCAPE '!'", ['%'.addcslashes($needle, '!%_').'%']);
                }
            }
        });
    }

    /**
     * @return list<array{type: string, table: string|null, model: class-string<Model>|null, columns: list<string>, title: list<string>, url: (callable(object): ?string)|null}>
     */
    public function sources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];
        $articleModel = config('desk-editor.articles.model', DeskArticle::class);
        $articleTable = is_string($articleModel) && is_subclass_of($articleModel, Model::class) ? (new $articleModel)->getTable() : 'desk_articles';
        $editUrl = fn (int|string $id): ?string => Route::has('desk-editor.articles.edit') ? route('desk-editor.articles.edit', $id) : null;

        if ($this->hasColumns($articleTable, ['body', 'cover_image'])) {
            $sources[] = ['type' => 'article', 'table' => $articleTable, 'model' => null, 'columns' => ['body', 'cover_image'], 'title' => ['title'], 'url' => fn (object $row) => $editUrl($row->id)];
        }

        if ($this->hasColumns('desk_article_revisions', ['body', 'desk_article_id'])) {
            $sources[] = ['type' => 'revision', 'table' => 'desk_article_revisions', 'model' => null, 'columns' => ['body'], 'title' => ['title'], 'url' => fn (object $row) => $editUrl($row->desk_article_id)];
        }

        if ($this->hasColumns('desk_drafts', ['content'])) {
            $sources[] = ['type' => 'draft', 'table' => 'desk_drafts', 'model' => null, 'columns' => ['content'], 'title' => ['key'], 'url' => null];
        }

        foreach ((array) config('desk-editor.media.prune.references', []) as $model => $columns) {
            if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
                continue;
            }

            $table = (new $model)->getTable();
            $columns = array_values(array_filter((array) $columns, 'is_string'));

            if ($columns !== [] && $this->hasColumns($table, $columns)) {
                $sources[] = ['type' => Str::snake(class_basename($model)), 'table' => $table, 'model' => $model, 'columns' => $columns, 'title' => ['title', 'name', 'subject', 'key'], 'url' => null];
            }
        }

        return $this->sources = $sources;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumns($table, $columns);
        } catch (Throwable) {
            return false;
        }
    }
}
