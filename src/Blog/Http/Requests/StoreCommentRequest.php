<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
            'name' => [$guest ? 'required' : 'nullable', 'string', 'max:80'],
            'email' => [$guest ? 'required' : 'nullable', 'string', 'email', 'max:191'],
            'website' => ['nullable', 'string', 'max:255'],
            '_desk_ts' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __('desk-editor::blog.comments.body'),
            'name' => __('desk-editor::blog.comments.name'),
            'email' => __('desk-editor::blog.comments.email'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#comment-form';
    }
}
