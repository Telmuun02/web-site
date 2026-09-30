<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Collection;

// Номын хайлт. Вектор үүсгэх, харьцуулах математик нь EmbeddingService-д,
// аль номыг, аль компанид, хэдийг буцаах гэх мэт номын логик энд.
class BookSearchService
{
    // Үүнээс доош оноотой ном хайлтад гарахгүй. Туршилтаар хамааралтай ном
    // ~0.34+, хамааралгүй ном (санамсаргүй n-gram давхцал) ~0.13-аас доош.
    private const MIN_SCORE = 0.2;

    public function __construct(private EmbeddingService $embeddings)
    {
    }

    public function search(string $query, int $limit = 5, ?int $companyId = null): Collection
    {
        $q = $this->embeddings->embed_with_math($query);

        return Book::with('authors', 'category')
            ->whereNotNull('math_embedding')
            // null (admin) бол шүүлт алгасна, утгатай бол тухайн компанийн ном л
            ->when($companyId, fn ($b) => $b->where('company_id', $companyId))
            ->get()
            ->map(fn (Book $b) => [
                'book'  => $b,
                'score' => $this->embeddings->cosine($q, $b->math_embedding),
            ])
            // Босгогүй бол оноо 0 байсан ч top N гарч, хамааралгүй ном харагдана
            ->filter(fn ($r) => $r['score'] >= self::MIN_SCORE)
            ->sortByDesc('score')
            ->take($limit)
            // sortByDesc анхны индексийг хадгалдаг — JSON-д объект болж гарахаас сэргийлнэ
            ->values();
    }
}
