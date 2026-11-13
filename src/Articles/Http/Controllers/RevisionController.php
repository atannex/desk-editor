<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Articles\Support\Differ;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskArticleRevision;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Side-by-side / inline comparison of two revisions (or a revision and the current version).
 */
class RevisionController extends Controller
{
    use ResolvesArticles;

    public function compare(Request $request, Differ $differ, DeskArticle|string $article): View
    {
        $article = $this->find($article);
        $this->authorizeAbility('view', $article);

        $revisions = $article->revisions()->with('author')->get();

        $to = $this->version($article, $revisions, $request->query('to', 'current'));
        $fromKey = $request->query('from');
        if ($fromKey === null || $fromKey === '') {
            // Default: the revision before the target. When comparing against the current
            // version, skip the latest snapshot if it is identical to what is live now.
            if ($to['revision'] !== null) {
                $index = $revisions->search(fn (DeskArticleRevision $r) => $r->is($to['revision']));
                $previous = $revisions->get(is_int($index) ? $index + 1 : 0);
            } else {
                $latest = $revisions->first();
                $same = $latest !== null && $latest->title === $to['title'] && (string) $latest->excerpt === (string) $to['excerpt'] && (string) $latest->body === (string) $to['body'];
                $previous = $same ? ($revisions->get(1) ?? $latest) : $latest;
            }
            $fromKey = $previous?->getKey() ?? 'current';
        }
        $from = $this->version($article, $revisions, $fromKey);

        $sections = [
            'title' => $differ->text($from['title'], $to['title']),
            'excerpt' => $differ->text($from['excerpt'], $to['excerpt']),
            'body' => $differ->html($from['body'], $to['body']),
        ];

        return view('desk-editor::articles.revisions.compare', [
            'article' => $article,
            'revisions' => $revisions,
            'from' => $from,
            'to' => $to,
            'sections' => $sections,
            'added' => array_sum(array_column($sections, 'added')),
            'removed' => array_sum(array_column($sections, 'removed')),
            'mode' => $request->query('mode') === 'split' ? 'split' : 'inline',
        ]);
    }

    /**
     * @param  Collection<int, DeskArticleRevision>  $revisions
     * @return array{key: string, revision: DeskArticleRevision|null, title: string, excerpt: string|null, body: string|null, date: Carbon|null, words: int, label: string}
     */
    private function version(DeskArticle $article, $revisions, mixed $key): array
    {
        if ($key !== 'current' && $key !== null && $key !== '') {
            /** @var DeskArticleRevision|null $revision */
            $revision = $revisions->first(fn (DeskArticleRevision $r) => (string) $r->getKey() === (string) $key);
            abort_if($revision === null, 404);

            return [
                'key' => (string) $revision->getKey(),
                'revision' => $revision,
                'title' => $revision->title,
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'date' => $revision->created_at,
                'words' => $revision->word_count,
                'label' => $revision->note ?: (string) $revision->created_at?->locale(app()->getLocale())->isoFormat('LLL'),
            ];
        }

        return [
            'key' => 'current',
            'revision' => null,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body?->html(),
            'date' => $article->updated_at,
            'words' => $article->word_count,
            'label' => __('desk-editor::articles.diff.current'),
        ];
    }
}
