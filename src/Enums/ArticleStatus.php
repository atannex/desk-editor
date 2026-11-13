<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('desk-editor::articles.status.'.$this->value);
    }

    /**
     * Tailwind classes for the status badge (compiled into the package stylesheet).
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-zinc-100 text-zinc-700 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-400/20',
            self::Review => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20',
            self::Scheduled => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
            self::Published => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20',
            self::Archived => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Draft => 'bg-zinc-400',
            self::Review => 'bg-amber-500',
            self::Scheduled => 'bg-sky-500',
            self::Published => 'bg-emerald-500',
            self::Archived => 'bg-rose-500',
        };
    }
}
