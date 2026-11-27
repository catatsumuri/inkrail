<?php

namespace App\Http\Requests;

use App\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->filled('article_id')) {
            $article = Article::findOrFail($this->integer('article_id'));

            return $this->user()?->can('update', $article) === true && ! $article->isSandbox();
        }

        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }
}
