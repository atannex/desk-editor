<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Support;

/**
 * Word-level diff engine (Myers' O(ND) algorithm) for comparing revisions.
 *
 * Bodies are first flattened to one line of text per block element (paragraph,
 * heading, list item, …) and diffed line by line; lines that changed are then
 * paired and diffed word by word, so edits stay readable even in long articles.
 */
class Differ
{
    public const EQUAL = 'equal';

    public const INSERT = 'insert';

    public const DELETE = 'delete';

    public const CHANGE = 'change';

    /** Above this many edits the diff falls back to "replace everything". */
    private const MAX_EDITS = 4000;

    private const INS_CLASS = 'rounded-[3px] bg-emerald-100 px-0.5 text-emerald-900 no-underline dark:bg-emerald-500/20 dark:text-emerald-200';

    private const DEL_CLASS = 'rounded-[3px] bg-rose-100 px-0.5 text-rose-900 line-through decoration-rose-400/70 dark:bg-rose-500/20 dark:text-rose-200 dark:decoration-rose-300/60';

    /**
     * Diff two token lists.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: string, 1: string}> [type, token] in order
     */
    public function diff(array $old, array $new): array
    {
        // Common prefix / suffix are cheap to strip and make Myers much faster.
        $prefix = 0;
        $n = count($old);
        $m = count($new);
        while ($prefix < $n && $prefix < $m && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $n - $prefix && $suffix < $m - $prefix && $old[$n - 1 - $suffix] === $new[$m - 1 - $suffix]) {
            $suffix++;
        }

        $ops = [];
        for ($i = 0; $i < $prefix; $i++) {
            $ops[] = [self::EQUAL, $old[$i]];
        }

        $middle = $this->myers(
            array_slice($old, $prefix, $n - $prefix - $suffix),
            array_slice($new, $prefix, $m - $prefix - $suffix),
        );
        array_push($ops, ...$middle);

        for ($i = $n - $suffix; $i < $n; $i++) {
            $ops[] = [self::EQUAL, $old[$i]];
        }

        return $ops;
    }

    /**
     * Word diff of two plain strings.
     *
     * @return array{segments: list<array{0: string, 1: string}>, added: int, removed: int}
     */
    public function words(string $old, string $new): array
    {
        $segments = $this->cleanup($this->group($this->diff($this->tokenize($old), $this->tokenize($new))));

        $added = $removed = 0;
        foreach ($segments as [$type, $text]) {
            if ($type === self::INSERT) {
                $added += $this->countWords($text);
            } elseif ($type === self::DELETE) {
                $removed += $this->countWords($text);
            }
        }

        return ['segments' => $segments, 'added' => $added, 'removed' => $removed];
    }

    /**
     * Compare two HTML documents block by block.
     *
     * @return array{rows: list<array{type: string, old: string|null, new: string|null, inline: string}>, added: int, removed: int}
     */
    public function html(?string $old, ?string $new): array
    {
        return $this->lines($this->blocks((string) $old), $this->blocks((string) $new));
    }

    /**
     * Compare two plain strings as a single row.
     *
     * @return array{rows: list<array{type: string, old: string|null, new: string|null, inline: string}>, added: int, removed: int}
     */
    public function text(?string $old, ?string $new): array
    {
        $old = trim((string) $old);
        $new = trim((string) $new);

        return $this->lines($old === '' ? [] : [$old], $new === '' ? [] : [$new]);
    }

    /**
     * Line-by-line comparison with word-level detail inside changed lines.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return array{rows: list<array{type: string, old: string|null, new: string|null, inline: string}>, added: int, removed: int}
     */
    public function lines(array $old, array $new): array
    {
        $rows = [];
        $added = $removed = 0;

        foreach ($this->hunks($this->diff($old, $new)) as $hunk) {
            if ($hunk['equal'] !== null) {
                $html = e($hunk['equal']);
                $rows[] = ['type' => self::EQUAL, 'old' => $html, 'new' => $html, 'inline' => $html];

                continue;
            }

            $deleted = $hunk['deleted'];
            $inserted = $hunk['inserted'];
            $pairs = min(count($deleted), count($inserted));

            for ($i = 0; $i < $pairs; $i++) {
                $words = $this->words($deleted[$i], $inserted[$i]);
                $added += $words['added'];
                $removed += $words['removed'];
                $rows[] = [
                    'type' => self::CHANGE,
                    'old' => $this->render($words['segments'], [self::EQUAL, self::DELETE]),
                    'new' => $this->render($words['segments'], [self::EQUAL, self::INSERT]),
                    'inline' => $this->render($words['segments'], [self::EQUAL, self::DELETE, self::INSERT]),
                ];
            }
            foreach (array_slice($deleted, $pairs) as $line) {
                $removed += $this->countWords($line);
                $html = $this->wrap(self::DELETE, $line);
                $rows[] = ['type' => self::DELETE, 'old' => $html, 'new' => null, 'inline' => $html];
            }
            foreach (array_slice($inserted, $pairs) as $line) {
                $added += $this->countWords($line);
                $html = $this->wrap(self::INSERT, $line);
                $rows[] = ['type' => self::INSERT, 'old' => null, 'new' => $html, 'inline' => $html];
            }
        }

        return ['rows' => $rows, 'added' => $added, 'removed' => $removed];
    }

    /**
     * Split diff ops into equal lines and runs of deleted/inserted lines.
     *
     * @param  list<array{0: string, 1: string}>  $ops
     * @return list<array{equal: string|null, deleted: list<string>, inserted: list<string>}>
     */
    private function hunks(array $ops): array
    {
        $hunks = [];
        $current = null;

        foreach ($ops as [$type, $line]) {
            if ($type === self::EQUAL) {
                if ($current !== null) {
                    $hunks[] = $current;
                    $current = null;
                }
                $hunks[] = ['equal' => $line, 'deleted' => [], 'inserted' => []];

                continue;
            }

            $current ??= ['equal' => null, 'deleted' => [], 'inserted' => []];
            if ($type === self::DELETE) {
                $current['deleted'][] = $line;
            } else {
                $current['inserted'][] = $line;
            }
        }

        if ($current !== null) {
            $hunks[] = $current;
        }

        return $hunks;
    }

    /**
     * Flatten HTML to one line of text per block element.
     *
     * @return list<string>
     */
    public function blocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $html = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $html = (string) preg_replace_callback('~<img\b[^>]*>~i', function (array $m): string {
            preg_match('~\balt\s*=\s*"([^"]*)"~i', $m[0], $alt);
            preg_match('~\bsrc\s*=\s*"([^"]*)"~i', $m[0], $src);
            $label = trim(html_entity_decode($alt[1] ?? '', ENT_QUOTES | ENT_HTML5)) ?: basename((string) parse_url($src[1] ?? '', PHP_URL_PATH));

            return "\n[".__('desk-editor::articles.diff.image').($label !== '' ? ': '.$label : '')."]\n";
        }, $html);
        $html = (string) preg_replace_callback('~<h([1-6])\b[^>]*>~i', fn (array $m): string => "\n".str_repeat('#', (int) $m[1]).' ', $html);
        $html = (string) preg_replace('~<li\b[^>]*>~i', "\n• ", $html);
        $html = (string) preg_replace('~<(br|hr)\b[^>]*/?>~i', "\n", $html);
        $html = (string) preg_replace('~</?(p|div|blockquote|pre|ul|ol|li|h[1-6]|table|thead|tbody|tfoot|tr|td|th|figure|figcaption|section|article|aside|header|footer|details|summary|iframe|video|audio|dl|dt|dd)\b[^>]*>~i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line));
            if ($line !== '' && $line !== '•') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Split text into word, whitespace and punctuation tokens (lossless).
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all("/\s+|[\p{L}\p{N}\p{M}_'’]+|[^\s\p{L}\p{N}]/u", $text, $matches);

        return $matches[0];
    }

    public function countWords(string $text): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}]+/u', $text);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $segments
     * @param  list<string>  $types
     */
    public function render(array $segments, array $types): string
    {
        $html = '';
        foreach ($segments as [$type, $text]) {
            if (in_array($type, $types, true)) {
                $html .= $type === self::EQUAL ? e($text) : $this->wrap($type, $text);
            }
        }

        return $html;
    }

    private function wrap(string $type, string $text): string
    {
        return $type === self::INSERT
            ? '<ins class="'.self::INS_CLASS.'">'.e($text).'</ins>'
            : '<del class="'.self::DEL_CLASS.'">'.e($text).'</del>';
    }

    /**
     * Merge consecutive tokens of the same type.
     *
     * @param  list<array{0: string, 1: string}>  $ops
     * @return list<array{0: string, 1: string}>
     */
    private function group(array $ops): array
    {
        $segments = [];
        foreach ($ops as [$type, $token]) {
            $last = array_key_last($segments);
            if ($last !== null && $segments[$last][0] === $type) {
                $segments[$last][1] .= $token;
            } else {
                $segments[] = [$type, $token];
            }
        }

        return $segments;
    }

    /**
     * Readability pass: absorb whitespace-only "equal" gaps between edits and
     * emit each run of edits as one deletion followed by one insertion.
     *
     * @param  list<array{0: string, 1: string}>  $segments
     * @return list<array{0: string, 1: string}>
     */
    private function cleanup(array $segments): array
    {
        $out = [];
        $pending = ['del' => '', 'ins' => ''];
        $count = count($segments);

        foreach ($segments as $i => [$type, $text]) {
            $editing = $pending['del'] !== '' || $pending['ins'] !== '';

            if ($type === self::DELETE) {
                $pending['del'] .= $text;
            } elseif ($type === self::INSERT) {
                $pending['ins'] .= $text;
            } elseif ($editing && trim($text) === '' && $i + 1 < $count && $segments[$i + 1][0] !== self::EQUAL) {
                $pending['del'] .= $text;
                $pending['ins'] .= $text;
            } else {
                array_push($out, ...$this->emit($pending));
                $pending = ['del' => '', 'ins' => ''];
                $out[] = [self::EQUAL, $text];
            }
        }

        array_push($out, ...$this->emit($pending));

        return $out;
    }

    /**
     * @param  array{del: string, ins: string}  $pending
     * @return list<array{0: string, 1: string}>
     */
    private function emit(array $pending): array
    {
        $out = [];
        if ($pending['del'] !== '') {
            $out[] = [self::DELETE, $pending['del']];
        }
        if ($pending['ins'] !== '') {
            $out[] = [self::INSERT, $pending['ins']];
        }

        return $out;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: string}>
     */
    private function myers(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0 || $m === 0) {
            return [...array_map(fn (string $t) => [self::DELETE, $t], $a), ...array_map(fn (string $t) => [self::INSERT, $t], $b)];
        }

        $max = $n + $m;
        $v = [1 => 0];
        $trace = [];

        for ($d = 0; $d <= $max; $d++) {
            if ($d > self::MAX_EDITS) {
                return [...array_map(fn (string $t) => [self::DELETE, $t], $a), ...array_map(fn (string $t) => [self::INSERT, $t], $b)];
            }

            $trace[] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && ($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1))) {
                    $x = $v[$k + 1] ?? 0;
                } else {
                    $x = ($v[$k - 1] ?? 0) + 1;
                }
                $y = $x - $k;
                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }
                $v[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    return $this->backtrack($trace, $a, $b);
                }
            }
        }

        return []; // unreachable
    }

    /**
     * @param  list<array<int, int>>  $trace
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: string}>
     */
    private function backtrack(array $trace, array $a, array $b): array
    {
        $x = count($a);
        $y = count($b);
        $ops = [];

        for ($d = count($trace) - 1; $d >= 0; $d--) {
            $v = $trace[$d];
            $k = $x - $y;

            if ($k === -$d || ($k !== $d && ($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1))) {
                $prevK = $k + 1;
            } else {
                $prevK = $k - 1;
            }
            $prevX = $v[$prevK] ?? 0;
            $prevY = $prevX - $prevK;

            while ($x > $prevX && $y > $prevY) {
                $ops[] = [self::EQUAL, $a[$x - 1]];
                $x--;
                $y--;
            }

            if ($d > 0) {
                if ($x === $prevX) {
                    $ops[] = [self::INSERT, $b[$y - 1]];
                } else {
                    $ops[] = [self::DELETE, $a[$x - 1]];
                }
            }

            $x = $prevX;
            $y = $prevY;
        }

        return array_reverse($ops);
    }
}
