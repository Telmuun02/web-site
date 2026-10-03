<?php

namespace App\Models;

use App\Services\WordShiftService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Department extends Model
{
    // Хадгалах/устгах бүрт Meilisearch-ийн "departments" индекс автоматаар шинэчлэгдэнэ
    use Searchable;

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

    // Meilisearch-д юу орохыг заана. Хайлт зөвхөн skeletons талбараар явна:
    // ["цалин", "суутгал"] → ["cln", "stgl"]
    public function toSearchableArray(): array
    {
        // Зөвхөн skeleton() хэрэгтэй — хоосон жагсаалт өгч DB уншихаас сэргийлнэ
        $shift = new WordShiftService([]);

        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'skeletons' => array_values(array_unique(array_map([$shift, 'skeleton'], $this->keywords))),
        ];
    }

    // ['Санхүү' => ['цалин', ...], 'Хүний нөөц' => [...]]
    public static function keywordMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::all()
            ->mapWithKeys(fn (Department $d) => [$d->name => $d->keywords])
            ->all());
    }
}
