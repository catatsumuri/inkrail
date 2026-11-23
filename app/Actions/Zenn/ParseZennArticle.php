<?php

namespace App\Actions\Zenn;

use App\Exceptions\ZennImportException;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** @phpstan-type ParsedArticle array{title: string, content: string, is_published: bool, sync_id: ?string, frontmatter: array<string, mixed>} */
class ParseZennArticle
{
    /** @return ParsedArticle */
    public function __invoke(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        if (! mb_check_encoding($markdown, 'UTF-8') || preg_match('/\A(?:\xEF\xBB\xBF)?---\n(.*?)\n---(?:\n|\z)(.*)\z/s', $markdown, $matches) !== 1) {
            throw new ZennImportException('The article has no valid frontmatter.');
        }

        try {
            $frontmatter = Yaml::parse($matches[1], Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_DATETIME);
        } catch (ParseException) {
            throw new ZennImportException('The article frontmatter is invalid.');
        }

        if (! is_array($frontmatter)) {
            throw new ZennImportException('The article frontmatter must be a mapping.');
        }

        foreach ($frontmatter as $key => $value) {
            if (! is_string($key) || preg_match('/\A[a-zA-Z_][a-zA-Z0-9_-]*\z/', $key) !== 1) {
                throw new ZennImportException('The article frontmatter contains an invalid key.');
            }
            $frontmatter[$key] = $this->normalizeDates($value);
        }

        try {
            json_encode($frontmatter, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ZennImportException('The article frontmatter contains unsupported values.');
        }

        $validator = Validator::make($frontmatter, [
            'title' => ['required', 'string', 'max:255'],
            'published' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_bool($value)) {
                    $fail('The publication state must be a boolean.');
                }
            }],
            'inkrail_id' => ['sometimes', 'required', 'uuid'],
            'emoji' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:tech,idea'],
            'topics' => ['sometimes', 'array', 'max:5'],
            'topics.*' => ['string'],
        ]);

        if ($validator->fails()) {
            throw new ZennImportException('The article frontmatter fields are invalid.');
        }

        return [
            'title' => $frontmatter['title'],
            'content' => str_starts_with($matches[2], "\n") ? substr($matches[2], 1) : $matches[2],
            'is_published' => $frontmatter['published'],
            'sync_id' => isset($frontmatter['inkrail_id']) ? Str::lower($frontmatter['inkrail_id']) : null,
            'frontmatter' => $frontmatter,
        ];
    }

    private function normalizeDates(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_array($value) ? array_map($this->normalizeDates(...), $value) : $value;
    }
}
