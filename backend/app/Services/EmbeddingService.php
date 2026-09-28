<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    // 1. Ном бүрийг embedd хийж өгөгдлийн санд хадгалах 
    // 2. chatBot ийн бичсэн үгийг embedded болгож хувирган хайлт хийж үзэх.

    // $inputType: хадгалах номд 'document', хайлтын үгэнд 'query' (Voyage зөвхөн эдгээрийг хүлээн авна)
    public function embed(string $text, string $inputType = 'document'){
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.voyage.api_key'),
        ])->timeout(5)->post('https://api.voyageai.com/v1/embeddings', [
            'model' => config('services.voyage.model'),
            'input' => $text,
            'input_type' => $inputType,
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

        // Sparse вектор: 4096 тэгийн оронд зөвхөн тэг биш утга [индекс => тоо]
        $vec = [];
        foreach ($counts as $gram => $n) {
            $idx = crc32($gram) % self::DIM;
            $vec[$idx] = ($vec[$idx] ?? 0) + $n;
        }

        return $vec;
    }

    public function cosine(array $a, array $b): float{
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }

        $dot = 0.0;
        foreach ($a as $i => $x) {
            if (isset($b[$i])) {
                $dot += $x * $b[$i];
            }
        }

        $normA = $this->norm($a);
        $normB = $this->norm($b);

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dot / ($normA * $normB);
    }

    private function norm(array $v): float
    {
        $sum = 0.0;
        foreach ($v as $x) {
            $sum += $x * $x;
        }

        return sqrt($sum);
    }
}
