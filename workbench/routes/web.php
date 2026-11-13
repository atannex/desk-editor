<?php

// Local development only: signs in a demo user so the article manager can be explored.

use Atannex\DeskEditor\Blog\Models\DeskArticleView;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('web')->group(function () {
    Route::get('/workbench/login', function () {
        $person = fn (string $name, string $email) => User::query()->where('email', $email)->first()
            ?? tap((new User)->forceFill(['name' => $name, 'email' => $email, 'password' => bcrypt(str()->random(32))]))->save();

        $user = $person('Demo Writer', 'demo@example.com');
        $grace = $person('Grace Hopper', 'grace@example.com');
        $person('Ada Lovelace', 'ada@example.com');
        Auth::login(match (request('as')) {
            'grace' => $grace,
            default => $user,
        });

        if (DeskArticle::query()->doesntExist()) {
            $colors = ['Product' => '#6366f1', 'Design' => '#ec4899', 'Engineering' => '#10b981', 'Company' => '#f59e0b', 'Guides' => '#3b82f6'];
            foreach (array_keys($colors) as $order => $name) {
                DeskCategory::findOrCreateByName($name)?->forceFill(['color' => $colors[$name], 'sort_order' => $order])->save();
            }

            $samples = [
                ['Introducing Desk Editor', 'published', 'Product', true, 'A premium writing experience for Laravel teams.'],
                ['Designing a calm dark mode', 'published', 'Design', false, 'Contrast, depth and colour in low light.'],
                ['A field guide to editorial workflows', 'published', 'Guides', false, 'Drafts, reviews and scheduled launches without the chaos.'],
                ['How we made uploads feel instant', 'published', 'Engineering', false, 'Local previews, background processing and responsive images.'],
                ['Shipping weekly without breaking things', 'review', 'Engineering', false, 'How small batches keep quality high.'],
                ['Q4 roadmap', 'scheduled', 'Company', false, 'What we are building next.'],
                ['Notes on typography', 'draft', 'Design', false, null],
                ['Migrating from v1', 'archived', 'Guides', false, null],
            ];
            foreach ($samples as $i => [$title, $status, $category, $featured, $excerpt]) {
                $article = DeskArticle::query()->create([
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'status' => $status,
                    'category_id' => DeskCategory::findOrCreateByName($category)?->id,
                    'featured' => $featured,
                    'published_at' => $status === 'scheduled' ? now()->addDays(5) : ($status === 'published' ? now()->subDays(12 - $i * 3) : null),
                    'cover_image' => workbench_cover($title, $i),
                    'cover_alt' => $title,
                    'body' => workbench_body($i),
                ]);
                $article->author()->associate($i % 2 ? $grace : $user)->saveQuietly();
                $article->syncTagNames(['laravel', strtolower($category)]);
                $article->logActivity('created');
                if ($status === 'review') {
                    $article->logActivity('submitted', null, ['from' => 'draft', 'to' => 'review']);
                }
            }
        }

        if (class_exists(DeskComment::class) && DeskComment::query()->doesntExist()) {
            workbench_engagement();
        }

        return redirect('/desk/articles');
    });

    Route::get('/login', fn () => redirect('/workbench/login'))->name('login');

    // Broadcasting auth for the collaboration presence channel.
    Broadcast::routes(['middleware' => ['web']]);
});

/** Generates a gradient cover image on the public disk (no network needed). */
function workbench_cover(string $title, int $seed): string
{
    $path = 'workbench/cover-'.$seed.'.jpg';
    $disk = Storage::disk('public');
    if (! $disk->exists($path)) {
        $palettes = [[99, 102, 241, 236, 72, 153], [16, 185, 129, 59, 130, 246], [245, 158, 11, 239, 68, 68], [139, 92, 246, 6, 182, 212], [20, 20, 30, 99, 102, 241], [236, 72, 153, 245, 158, 11]];
        [$r1, $g1, $b1, $r2, $g2, $b2] = $palettes[$seed % count($palettes)];
        $w = 1600;
        $h = 900;
        $img = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x++) {
            $t = $x / $w;
            imageline($img, $x, 0, $x, $h, imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
        for ($i = 0; $i < 9; $i++) {
            $alpha = imagecolorallocatealpha($img, 255, 255, 255, 110);
            imagefilledellipse($img, ($seed * 211 + $i * 367) % $w, ($seed * 97 + $i * 241) % $h, 220 + $i * 40, 220 + $i * 40, $alpha);
        }
        ob_start();
        imagejpeg($img, null, 88);
        $disk->put($path, (string) ob_get_clean());
        imagedestroy($img);
    }

    return $disk->url($path);
}

function workbench_body(int $i): string
{
    $p = fn (string $text) => "<p>{$text}</p>";
    $lorem = 'Great writing tools disappear while you work. Every block, shortcut and panel in Desk is designed to keep you in flow, from the first rough draft to the published story.';

    return implode('', [
        '<h2 id="why-it-matters">Why it matters</h2>',
        $p($lorem.' Teams move faster when drafts, reviews and publishing live in one place<sup data-type="footnote" data-content="Based on interviews with twelve editorial teams in 2026."></sup>.'),
        '<div data-callout="tip" class="de-callout"><p>Press <strong>Ctrl + /</strong> at any time to see every shortcut.</p></div>',
        '<h2 id="how-it-works">How it works</h2>',
        '<div data-type="columns" data-layout="1-1" class="de-columns"><div data-type="column" class="de-column"><h3 id="write">Write</h3><p>Type <code>/</code> for blocks: tables, galleries, diagrams, charts and more.</p></div><div data-type="column" class="de-column"><h3 id="review">Review</h3><p>Comment on any sentence, suggest edits and resolve threads together.</p></div></div>',
        $p(str_repeat($lorem.' ', 3 + $i)),
        '<h3 id="by-the-numbers">By the numbers</h3>',
        '<figure data-type="chart" data-chart=\'{"type":"bar","title":"Weekly active writers","labels":["Jan","Feb","Mar","Apr","May","Jun"],"datasets":[{"label":"2025","values":[120,180,210,260,300,340]},{"label":"2026","values":[220,260,330,410,470,560]}]}\' class="de-chart"><table class="de-chart-data"><tbody><tr><th>Jan</th><td>120</td></tr></tbody></table><figcaption>Weekly active writers</figcaption></figure>',
        '<blockquote><p>The best editor is the one you forget you are using.</p></blockquote>',
        '<h2 id="whats-next">What is next</h2>',
        $p($lorem),
        '<div data-type="button" data-align="center" class="de-cta-wrap"><a href="https://example.com" class="de-cta" data-variant="primary">Start writing</a></div>',
    ]);
}

/** Reader comments and 60 days of page views for the published articles. */
function workbench_engagement(): void
{
    $articles = DeskArticle::query()->published()->get();
    $names = ['Maya', 'Leo', 'Priya', 'Tom', 'Sofia', 'Kwame'];
    $bodies = ['This is exactly the guide I needed. Thank you!', 'Love the dark mode details — the contrast choices are spot on.', 'Could you share more about the review workflow?', 'Bookmarked. Sharing this with my team today.', 'Great read, the charts really help.'];

    foreach ($articles as $index => $article) {
        foreach (range(0, 2 + $index % 3) as $n) {
            $comment = DeskComment::query()->forceCreate([
                'article_id' => $article->id,
                'guest_name' => $names[($index + $n) % count($names)],
                'guest_email' => 'reader'.$n.'@example.com',
                'body' => $bodies[($index + $n) % count($bodies)],
                'status' => $n === 2 ? 'pending' : 'approved',
                'created_at' => now()->subHours(($index + 1) * ($n + 3)),
                'updated_at' => now()->subHours(($index + 1) * ($n + 3)),
            ]);
        }
        if (isset($comment)) {
            DeskComment::query()->forceCreate([
                'article_id' => $article->id,
                'parent_id' => $comment->id,
                'depth' => 1,
                'guest_name' => 'Desk Team',
                'guest_email' => 'team@example.com',
                'is_staff' => true,
                'body' => 'Thanks for reading! More on this soon.',
                'status' => 'approved',
            ]);
        }
        DeskComment::syncCount($article->id);

        $total = 0;
        foreach (range(0, 59) as $day) {
            $views = (int) round((40 + 30 * sin(($day + $index * 7) / 6) + (60 - $day) * 1.5) * (1 + $index * 0.25));
            $total += $views;
            DeskArticleView::query()->forceCreate([
                'article_id' => $article->id,
                'date' => now()->subDays($day)->toDateString(),
                'views' => $views,
                'visitors' => (int) round($views * 0.7),
            ]);
        }
        DeskArticle::query()->whereKey($article->id)->update(['views_count' => $total]);
    }
}
