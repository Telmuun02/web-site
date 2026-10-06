<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Department extends Model
{
    // Чатын мессеж бүрт DB уншихгүйн тулд cache-лэнэ
    public const CACHE_KEY = 'departments:keywords';

    protected $fillable = [
        'name',
        'keywords',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];

    // Хэлтэс нэмэх/засах/устгах бүрт cache шинэчлэгдэнэ
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    // ['Санхүү' => ['цалин', ...], 'Хүний нөөц' => [...]]
    public static function keywordMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::all()
            ->mapWithKeys(fn (Department $d) => [$d->name => $d->keywords])
            ->all());
    }
}
