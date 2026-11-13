<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature;

use Atannex\DeskEditor\Casts\AsDeskContent;
use Atannex\DeskEditor\Events\DeskFileUploaded;
use Atannex\DeskEditor\Support\DeskContent;
use Atannex\DeskEditor\Tests\Fixtures\UserMentions;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class EndpointsTest extends TestCase
{
    public function test_assets_are_served_with_cache_headers(): void
    {
        $this->get('/desk-editor/assets/desk-editor.js?v=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

        $this->get('/desk-editor/assets/desk-editor.css')->assertOk();
        $this->get('/desk-editor/assets/fonts/KaTeX_Main-Regular.woff2')->assertOk();
    }

    public function test_code_split_chunks_are_served(): void
    {
        $chunks = glob(__DIR__.'/../../dist/chunks/*.js') ?: [];
        $this->assertNotEmpty($chunks);

        // Every chunk must be reachable, including names with dots (e.g. "mermaid.core-XXXX.js").
        foreach ($chunks as $chunk) {
            $this->get('/desk-editor/assets/chunks/'.basename($chunk))->assertOk();
        }
    }

    public function test_asset_route_rejects_anything_else(): void
    {
        $this->get('/desk-editor/assets/chunks/../../composer.json')->assertNotFound();
        $this->get('/desk-editor/assets/chunks/..%2F..%2Fcomposer.json')->assertNotFound();
        $this->get('/desk-editor/assets/../composer.json')->assertNotFound();
        $this->get('/desk-editor/assets/build.mjs')->assertNotFound();
    }

    public function test_uploads_require_authentication(): void
    {
        $this->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
    }

    public function test_images_are_stored_and_described(): void
    {
        Storage::fake('public');
        Event::fake([DeskFileUploaded::class]);

        $response = $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('Team Photo.png', 640, 480), 'kind' => 'image'])
            ->assertCreated()
            ->assertJson(['name' => 'Team Photo.png', 'mime' => 'image/png', 'kind' => 'image', 'width' => 640, 'height' => 480]);

        Storage::disk('public')->assertExists($response->json('path'));
        $this->assertMatchesRegularExpression('#^desk-editor/\d{4}/\d{2}/team-photo-[a-z0-9]{8}\.png$#', $response->json('path'));
        Event::assertDispatched(DeskFileUploaded::class);
    }

    public function test_videos_and_attachments_are_accepted(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'), 'kind' => 'video'])
            ->assertCreated()
            ->assertJsonPath('kind', 'video');

        $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')])
            ->assertCreated()
            ->assertJsonPath('kind', 'file');
    }

    public function test_disallowed_types_and_sizes_are_rejected(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'), 'kind' => 'file'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        config(['desk-editor.uploads.max_size' => 1]);
        $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('big.png')->size(50), 'kind' => 'image'])
            ->assertUnprocessable();
    }

    public function test_mentions_use_the_configured_resolver(): void
    {
        $this->actingAs($this->user())->getJson('/desk-editor/mentions?q=ada')->assertNotFound();

        config(['desk-editor.mentions.resolver' => UserMentions::class]);

        $this->actingAs($this->user())
            ->getJson('/desk-editor/mentions?q=ada')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Ada Lovelace');
    }

    public function test_cast_sanitises_on_write_and_hydrates_content(): void
    {
        $model = new class extends Model
        {
            protected $casts = ['body' => AsDeskContent::class];
        };

        $model->body = '<p onclick="x()">Hi <script>1</script></p>';

        $this->assertSame('<p>Hi </p>', $model->getAttributes()['body']);
        $this->assertInstanceOf(DeskContent::class, $model->body);
        $this->assertSame(1, $model->body->words());
    }
}
