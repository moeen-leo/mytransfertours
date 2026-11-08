<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    /** Replaces {{placeholders}} in subject and body. Values are escaped for HTML. */
    public function render(array $data): array
    {
        $replace = fn (string $text, bool $escape) => preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            fn ($m) => $escape ? e((string) data_get($data, $m[1], '')) : (string) data_get($data, $m[1], ''),
            $text
        );

        return [
            'subject' => $replace($this->subject, false),
            'body' => $replace($this->body, true),
        ];
    }
}
