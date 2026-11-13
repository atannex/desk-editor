<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Unit;

use Atannex\DeskEditor\Rules\DeskContentRule;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class DeskContentRuleTest extends TestCase
{
    private function passes(mixed $value, DeskContentRule $rule): bool
    {
        return Validator::make(['body' => $value], ['body' => [$rule]])->passes();
    }

    public function test_required_ignores_empty_markup(): void
    {
        $this->assertFalse($this->passes('<p></p>', DeskContentRule::make()->required()));
        $this->assertTrue($this->passes('<p>Hi</p>', DeskContentRule::make()->required()));
    }

    public function test_word_and_character_limits(): void
    {
        $html = '<p>one two three four five</p>';

        $this->assertFalse($this->passes($html, DeskContentRule::make()->minWords(6)));
        $this->assertTrue($this->passes($html, DeskContentRule::make()->minWords(5)->maxWords(5)));
        $this->assertFalse($this->passes($html, DeskContentRule::make()->maxCharacters(10)));
        $this->assertFalse($this->passes('<img src="/a"><img src="/b">', DeskContentRule::make()->maxImages(1)));
    }

    public function test_messages_are_translated(): void
    {
        $validator = Validator::make(['body' => '<p>hi</p>'], ['body' => [DeskContentRule::make()->minWords(3)]]);

        $this->assertSame('The body must contain at least 3 words.', $validator->errors()->first('body'));
    }
}
