<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    // 1. Ном бүрийг embedd хийж өгөгдлийн санд хадгалах 
    // 2. chatBot ийн бичсэн үгийг embedded болгож хувирган хайлт хийж үзэх.

    public function embed(string $text){
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.voyage.api_key'),
        ])->post('https://api.voyageai.com/v1/embeddings', [
            'model' => config('services.voyage.model'),
            'input' => $text,
            'input_type' => 'text',
        ]);

        if ($response->failed()) {
            throw new \Exception('Боломжгүй байна: ' . $response->body());
        }

        return $response->json()['data'][0]['embedding'];
    }

    // Векторын урт. N-gram бүр crc32 % DIM индекст унана.
    private const DIM = 4096;

    // Текстийг character 3-gram + hashing аргаар вектор болгоно (AI-гүй).
    public function embed_with_math(string $text): array
    {
        $text = mb_strtolower($text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $grams = [];
        foreach ($words as $word) {
            $padded = ' ' . $word . ' ';
            $len = mb_strlen($padded);
            for ($i = 0; $i <= $len - 3; $i++) {
                $grams[] = mb_substr($padded, $i, 3);
            }
        }

        $counts = array_count_values($grams);

        $vec = array_fill(0, self::DIM, 0.0);
        foreach ($counts as $gram => $n) {
            $idx = crc32($gram) % self::DIM;
            $vec[$idx] += $n;
        }

        return $vec;
    }

    public function cosine(array $a, array $b): float{
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += pow($a[$i], 2);
            $normB += pow($b[$i], 2);
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    public function search(string $query, int $limit = 5, ?int $companyId = null): Collection
    {
        $q = $this->embed_with_math($query);

        return Book::with('authors', 'category')
            ->whereNotNull('math_embedding')
            // null (admin) бол шүүлт алгасна, утгатай бол тухайн компанийн ном л
            ->when($companyId, fn ($b) => $b->where('company_id', $companyId))
            ->get()
            ->map(fn (Book $b) => [
                'book'  => $b,
                'score' => $this->cosine($q, $b->math_embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            // sortByDesc анхны индексийг хадгалдаг — JSON-д объект болж гарахаас сэргийлнэ
            ->values();
    }
}
