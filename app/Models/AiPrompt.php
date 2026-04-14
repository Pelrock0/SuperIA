<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiPrompt extends Model
{
    use CrudTrait;
    protected $fillable = ['slug', 'name', 'description', 'content', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getContent(string $slug, string $fallback): string
    {
        return Cache::remember("ai_prompt:{$slug}", 300, function () use ($slug, $fallback) {
            $prompt = self::where('slug', $slug)->where('is_active', true)->first();

            return $prompt?->content ?? $fallback;
        });
    }

    public static function clearCache(string $slug): void
    {
        Cache::forget("ai_prompt:{$slug}");
    }

    protected static function booted(): void
    {
        static::saved(fn (self $prompt) => self::clearCache($prompt->slug));
        static::deleted(fn (self $prompt) => self::clearCache($prompt->slug));
    }
}
