<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Console;

use Atannex\DeskEditor\Media\MediaReferences;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes uploads that were never used (or no longer are) once the grace
 * period has passed. Children (e.g. custom posters) follow their parent.
 */
class PruneMediaCommand extends Command
{
    protected $signature = 'desk-editor:prune-media
        {--dry-run : List what would be deleted without deleting anything}
        {--hours= : Grace period in hours (defaults to media.prune.after_hours)}';

    protected $description = 'Delete unreferenced desk editor uploads older than the grace period';

    public function handle(MediaReferences $references): int
    {
        if (! Schema::hasTable((new DeskMedia)->getTable())) {
            $this->components->warn('The desk_media table does not exist yet. Run your migrations first.');

            return self::SUCCESS;
        }

        $hours = $this->option('hours');
        $hours = is_numeric($hours) ? (float) $hours : (float) config('desk-editor.media.prune.after_hours', 24);
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes((int) round(max(0, $hours) * 60));

        $rows = [];
        $freed = 0;
        $kept = 0;

        // Only top-level items are candidates; children are judged with their parent.
        DeskMedia::query()
            ->whereNull('parent_id')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($references, $dryRun, &$rows, &$freed, &$kept): void {
                foreach ($chunk as $media) {
                    /** @var DeskMedia $media */
                    if ($references->isReferenced($media)) {
                        $kept++;

                        continue;
                    }

                    $size = (int) $media->size + (int) $media->children()->sum('size');
                    $rows[] = [$media->id, mb_strimwidth($media->name, 0, 48, '…'), $media->kind, DeskMedia::humanSize($size), $media->created_at?->toDateTimeString() ?? '—'];
                    $freed += $size;

                    if (! $dryRun) {
                        $media->delete();
                    }
                }
            });

        if ($rows === []) {
            $this->components->info("Nothing to prune ({$kept} item(s) in use).");

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Kind', 'Size', 'Uploaded'], $rows);

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->components->info(sprintf('%s %d unreferenced item(s), %s freed; %d in use.', $verb, count($rows), DeskMedia::humanSize($freed), $kept));

        return self::SUCCESS;
    }
}
