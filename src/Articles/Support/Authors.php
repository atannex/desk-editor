<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves the people articles can be attributed to.
 */
class Authors
{
    /**
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $model = config('desk-editor.articles.authors.model');

        if (! is_string($model) || $model === '') {
            $guard = (string) config('auth.defaults.guard', 'web');
            $provider = config("auth.guards.{$guard}.provider", 'users');
            $model = config("auth.providers.{$provider}.model");
        }

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    public function label(): string
    {
        return (string) config('desk-editor.articles.authors.label', 'name') ?: 'name';
    }

    public function find(mixed $id): ?Model
    {
        $model = $this->model();
        if ($model === null || $id === null || $id === '') {
            return null;
        }

        return $model::query()->find($id);
    }

    /**
     * @return list<array{id: string, name: string, initials: string}>
     */
    public function search(?string $term = null, int $limit = 50): array
    {
        $model = $this->model();
        if ($model === null) {
            return [];
        }

        $label = $this->label();
        $term = trim((string) $term);

        try {
            return $model::query()
                ->when($term !== '', function (Builder $query) use ($term, $label): void {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                    $query->where(function (Builder $q) use ($like, $label): void {
                        $q->where($label, 'like', $like);
                        if ($label !== 'email' && $this->hasEmail($q->getModel())) {
                            $q->orWhere('email', 'like', $like);
                        }
                    });
                })
                ->orderBy($label)
                ->limit($limit)
                ->get()
                ->map(fn (Model $user) => $this->present($user))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{id: string, name: string, initials: string}
     */
    public function present(Model $user): array
    {
        $name = $this->nameOf($user);

        return ['id' => (string) $user->getKey(), 'name' => $name, 'initials' => self::initials($name)];
    }

    public function nameOf(?Model $user): string
    {
        if ($user === null) {
            return '';
        }

        $value = $user->getAttribute($this->label()) ?? $user->getAttribute('name') ?? $user->getAttribute('email');

        return is_scalar($value) ? (string) $value : '';
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/[\s._@-]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= Str::upper(Str::substr($word, 0, 1));
        }

        return $initials ?: '?';
    }

    /**
     * Deterministic avatar tint for a name.
     */
    public static function tone(?string $name): string
    {
        $tones = [
            'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
            'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
            'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
            'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
            'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-200',
            'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-200',
        ];

        return $tones[crc32((string) $name) % count($tones)];
    }

    private function hasEmail(Model $model): bool
    {
        try {
            return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'email');
        } catch (Throwable) {
            return false;
        }
    }
}
