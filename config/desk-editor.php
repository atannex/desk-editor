<?php

use Atannex\DeskEditor\Models\DeskArticle;

return [

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | "auto" follows the page (a `dark` class or data-theme attribute on <html>)
    | and falls back to the operating system preference. "light" and "dark"
    | force a theme. The accent colour accepts any CSS colour value.
    |
    */

    'theme' => env('DESK_EDITOR_THEME', 'auto'),

    'accent' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage format
    |--------------------------------------------------------------------------
    |
    | What the hidden input submits: "html" (default), "json" (ProseMirror
    | document) or "markdown".
    |
    */

    'format' => 'html',

    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    |
    | Named presets you can pick per editor with <x-desk-editor toolbar="minimal" />
    | or pass an array of items directly. "|" starts a new group and "->"
    | pushes the remaining groups to the right.
    |
    | Available items: undo, redo, blockStyle, fontFamily, fontSize, lineHeight,
    | bold, italic, underline, strike, code, subscript, superscript, textColor,
    | highlight, clearFormatting, align, alignLeft, alignCenter, alignRight,
    | alignJustify, indent, outdent, textDirection, bulletList, orderedList,
    | taskList, link, image, video, audio, attachment, table, embed, blockquote,
    | codeBlock, callout, details, math, horizontalRule, emoji, insert,
    | findReplace, source, toc, invisibles, focusMode, fullscreen, theme,
    | preview, export, shortcuts.
    |
    */

    'toolbar' => 'full',

    'toolbars' => [
        'full' => [
            'undo', 'redo', '|',
            'blockStyle', 'fontFamily', 'fontSize', '|',
            'bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', '|',
            'align', 'bulletList', 'orderedList', 'taskList', 'outdent', 'indent', '|',
            'link', 'image', 'video', 'table', 'embed', 'callout', 'math', 'insert', '->',
            'findReplace', 'source', 'toc', 'focusMode', 'fullscreen', 'theme', 'export', 'shortcuts',
        ],
        'standard' => [
            'undo', 'redo', '|', 'blockStyle', '|',
            'bold', 'italic', 'underline', 'strike', 'highlight', '|',
            'bulletList', 'orderedList', 'taskList', '|',
            'link', 'image', 'video', 'table', 'blockquote', 'codeBlock', '->',
            'fullscreen',
        ],
        'minimal' => [
            'bold', 'italic', 'underline', '|', 'bulletList', 'orderedList', '|', 'link',
        ],
        'comment' => [
            'bold', 'italic', 'strike', 'code', '|', 'bulletList', 'orderedList', '|', 'link', 'emoji',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'bubble_menu' => true,
        'slash_commands' => true,
        'drag_handle' => true,
        'typography' => true,
        'markdown_paste' => true,
        'statusbar' => true,
        'emoji_suggestions' => true,
        'sticky_toolbar' => true,
    ],

    'headings' => [1, 2, 3, 4, 5, 6],

    'min_height' => '18rem',

    'max_height' => null,

    'spellcheck' => true,

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Soft limits shown in the status bar (characters are enforced in the
    | editor). Use the DeskContentRule validation rule to enforce them on the
    | server too.
    |
    */

    'limit' => [
        'characters' => null,
        'words' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Autosave
    |--------------------------------------------------------------------------
    |
    | Keeps an unsaved draft in the browser's localStorage and offers to restore
    | it. The draft is cleared when the surrounding form is submitted.
    |
    */

    'autosave' => [
        'enabled' => true,
        'debounce' => 800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Images and attachments pasted, dropped or picked in the editor are sent to
    | the package's upload route and stored on this disk. Set "enabled" to false
    | to only allow images by URL.
    |
    */

    'uploads' => [
        'enabled' => true,
        'disk' => env('DESK_EDITOR_DISK', 'public'),
        'directory' => 'desk-editor/{Y}/{m}',
        'visibility' => 'public',
        'max_size' => 10240, // kilobytes, images & files
        'max_media_size' => 204800, // kilobytes, video & audio (also raise upload_max_filesize / post_max_size)
        'images' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], // SVG is excluded on purpose: it can carry scripts
        'videos' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/flac'],
        'files' => [
            'application/pdf',
            'application/zip',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
        'middleware' => ['web', 'auth', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mentions
    |--------------------------------------------------------------------------
    |
    | Point "resolver" at a class implementing
    | Atannex\DeskEditor\Contracts\MentionResolver to enable @mentions.
    |
    */

    'mentions' => [
        'resolver' => null,
        'trigger' => '@',
        'middleware' => ['web', 'auth', 'throttle:120,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        'new_tab' => true,
        'autolink' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts, sizes and colours
    |--------------------------------------------------------------------------
    */

    'fonts' => [
        ['label' => 'Sans Serif', 'value' => 'ui-sans-serif, system-ui, sans-serif'],
        ['label' => 'Serif', 'value' => 'ui-serif, Georgia, serif'],
        ['label' => 'Monospace', 'value' => 'ui-monospace, SFMono-Regular, monospace'],
        ['label' => 'Inter', 'value' => 'Inter, sans-serif'],
        ['label' => 'Georgia', 'value' => 'Georgia, serif'],
        ['label' => 'Arial', 'value' => 'Arial, sans-serif'],
        ['label' => 'Times New Roman', 'value' => '"Times New Roman", serif'],
        ['label' => 'Courier New', 'value' => '"Courier New", monospace'],
    ],

    'font_sizes' => ['12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px', '48px', '60px'],

    'colors' => [
        '#0f172a', '#475569', '#94a3b8', '#dc2626', '#ea580c', '#d97706', '#16a34a',
        '#0d9488', '#0284c7', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#ffffff',
    ],

    'highlights' => [
        '#fef08a', '#fed7aa', '#fecaca', '#fbcfe8', '#e9d5ff', '#c7d2fe', '#bae6fd', '#a7f3d0', '#d9f99d', '#e2e8f0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanitisation
    |--------------------------------------------------------------------------
    |
    | Content is cleaned on the server with symfony/html-sanitizer whenever you
    | use the AsDeskContent cast, the DeskEditor::sanitize() helper or the
    | <x-desk-editor::content> component. Only markup the editor produces is
    | kept. Embeds are limited to the hosts below.
    |
    */

    'sanitizer' => [
        'embed_hosts' => [
            'www.youtube-nocookie.com',
            'www.youtube.com',
            'player.vimeo.com',
            'www.loom.com',
            'www.dailymotion.com',
            'open.spotify.com',
            'w.soundcloud.com',
            'codepen.io',
            'codesandbox.io',
            'www.figma.com',
            'embed.figma.com',
            'www.google.com',
            'maps.google.com',
            'platform.twitter.com',
            'www.instagram.com',
            'www.tiktok.com',
        ],
        'allowed_link_schemes' => ['http', 'https', 'mailto', 'tel'],
        'allow_relative_links' => true,
        'allow_relative_media' => true,
        'force_https_media' => false,
        'max_input_length' => 2_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets & routes
    |--------------------------------------------------------------------------
    |
    | The compiled CSS/JS are served straight from the package (with long-lived
    | cache headers) unless you publish them with `php artisan desk-editor:install`
    | — published assets in public/vendor/desk-editor take precedence.
    |
    */

    'route_prefix' => 'desk-editor',

    // Automatically print the <link>/<script> tags the first time a component renders.
    // Disable it if you place @deskEditorStyles / @deskEditorScripts in your layout.
    'inject_assets' => true,

    'asset_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Article manager
    |--------------------------------------------------------------------------
    |
    | A complete writing workspace at /desk/articles: list, search and filter,
    | drafts, review, scheduling, publishing, cover images, tags, SEO fields,
    | revision history with one-click restore, previews and bulk actions.
    | Access is guarded by the "manage-desk-articles" gate (any signed-in user
    | by default — define the gate yourself to restrict it).
    |
    */

    'articles' => [
        'enabled' => true,
        'prefix' => 'desk/articles',
        'middleware' => ['web', 'auth', 'can:manage-desk-articles'],
        'model' => DeskArticle::class,
        'per_page' => 15,
        'revisions' => [
            'keep' => 50,
        ],
        'categories' => ['News', 'Product', 'Engineering', 'Design', 'Company', 'Guides'],
        'default_locale' => env('DESK_ARTICLES_LOCALE', 'en'),
        'locales' => ['en' => 'English'],
        // Abilities checked through Gate. Undefined gates fall back to "any signed-in user".
        'abilities' => ['view', 'create', 'update', 'publish', 'review', 'delete', 'manage-taxonomy', 'moderate-comments'],
        'require_review' => false,  // writers without "publish" must submit for review
        'lock_seconds' => 90,       // edit-lock heartbeat window
        'authors' => [
            'model' => null,        // defaults to the auth provider's user model
            'label' => 'name',
        ],
        'scout' => false,           // use Laravel Scout for admin search when installed
        // URL used for the "View live" button; {slug} is replaced. Null hides it.
        'public_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media library & processing
    |--------------------------------------------------------------------------
    |
    | Every upload is recorded in the desk_media table so it can be browsed,
    | reused, described and cleaned up. Images get responsive WebP variants;
    | videos get a poster frame and (optionally) an MP4/H.264 transcode via
    | ffmpeg. Unreferenced uploads are pruned by `desk-editor:prune-media`.
    |
    */

    'media' => [
        'library' => true,
        'prefix' => 'desk/media',      // admin media page URL
        'per_page' => 40,
        'middleware' => ['web', 'auth'],
        'image_driver' => env('DESK_EDITOR_IMAGE_DRIVER', 'auto'), // auto | imagick | gd | none
        'variants' => [480, 960, 1600, 2400],
        'variant_format' => 'webp',
        'variant_quality' => 82,
        'ffmpeg' => [
            'enabled' => env('DESK_EDITOR_FFMPEG', true),
            'binary' => env('DESK_EDITOR_FFMPEG_BINARY', 'ffmpeg'),
            'ffprobe' => env('DESK_EDITOR_FFPROBE_BINARY', 'ffprobe'),
            'transcode' => true,      // re-encode non-MP4 video to H.264/AAC MP4
            'max_height' => 1080,
            'timeout' => 3600,        // seconds per ffmpeg run
            'queue' => null,          // queue connection/name for processing, null = sync
        ],
        'prune' => [
            'after_hours' => 24,      // grace period before an unreferenced upload is deleted
            // Extra columns that may reference uploads: [Model::class => ['column', ...]]
            'references' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server drafts
    |--------------------------------------------------------------------------
    |
    | Editors with server autosave (`:autosave="['key' => 'post-1', 'server' => true]"`)
    | keep their working copy in the desk_drafts table per user, so it follows
    | the writer between devices.
    |
    */

    'drafts' => [
        'enabled' => true,
        'interval' => 4000, // ms between server saves while typing
        'middleware' => ['web', 'auth', 'throttle:240,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editorial comments & suggestions
    |--------------------------------------------------------------------------
    |
    | Inline comment threads anchored to text (`:comments="'post:1'"`) and a
    | suggestion mode that records insertions/deletions for review.
    |
    */

    'comments' => [
        'enabled' => true,
        'middleware' => ['web', 'auth', 'throttle:120,1'],
    ],

    'suggestions' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time collaboration
    |--------------------------------------------------------------------------
    |
    | Live co-editing with cursors and presence, synchronised over Laravel
    | broadcasting (Reverb or any Pusher-compatible server) on a presence
    | channel. Enable broadcasting in your app, then turn this on.
    |
    */

    'collaboration' => [
        'enabled' => env('DESK_EDITOR_COLLABORATION', false),
        'channel_prefix' => 'desk.document',
        'broadcaster' => [
            'key' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY')),
            'host' => env('REVERB_HOST', env('PUSHER_HOST')),
            'port' => env('REVERB_PORT', env('PUSHER_PORT', 443)),
            'scheme' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')),
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        ],
        // Called to authorise a user for a document key: fn ($user, string $document): bool
        'authorize' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public blog
    |--------------------------------------------------------------------------
    |
    | A complete, themeable reader-facing site for published articles: home
    | with featured story, category/tag/author/search pages, article pages with
    | table of contents, related posts, sharing, reader comments, RSS/Atom feed,
    | XML sitemap, Open Graph/Twitter cards and JSON-LD. Disabled by default so
    | it never collides with your own routes.
    |
    */

    'blog' => [
        'enabled' => env('DESK_BLOG_ENABLED', false),
        'prefix' => 'blog',
        'middleware' => ['web'],
        'name' => env('DESK_BLOG_NAME', env('APP_NAME', 'Blog')),
        'description' => 'Stories, guides and updates.',
        'per_page' => 12,
        'feed_items' => 30,
        'accent' => null,
        'comments' => [
            'enabled' => true,
            'moderation' => 'manual',   // manual | auto
            'guests' => true,
            'max_depth' => 3,
            'middleware' => ['web', 'throttle:10,1'],
        ],
        'analytics' => [
            'enabled' => true,
            'dedupe_minutes' => 30,
            'ignore_bots' => true,
        ],
    ],

];
