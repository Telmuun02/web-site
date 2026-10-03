<?php

namespace App\Services;

use App\Models\Department;

// Хэрэглэгчийн текстээс аль хэлтэс рүү хандаж байгааг тодорхойлно.
// Үг таних ажлыг WordShiftService хийнэ ("tslin" → "цалин"), энд зөвхөн
// танигдсан түлхүүр үгсийг хэлтэс рүү буулгаж оноо тоолно.
class DepartmentService
{
    // ['Санхүү' => ['цалин', ...], ...] — departments хүснэгтээс
    private array $departments;

    // null бол DB-ээс; тест дээр шууд дамжуулна
    public function __construct(private WordShiftService $words, ?array $departments = null)
    {
        $this->departments = $departments ?? Department::keywordMap();
    }

    // "tslin dtuu bn" → ['department' => 'Санхүү', 'scores' => [...], 'keywords' => ['tslin' => 'цалин']]
    public function detect(string $text): array
    {
        $keywords = $this->words->shiftText($text);
        $scores   = array_fill_keys(array_keys($this->departments), 0);

        foreach ($keywords as $keyword) {
            if ($dept = $this->departmentOf($keyword)) {
                $scores[$dept]++;
            }
        }

        arsort($scores);
        [$top, $second] = array_pad(array_values($scores), 2, 0);

        return [
            // Юу ч таараагүй эсвэл тэнцсэн бол null — хэрэглэгчээс товчлуураар асуух
            'department' => $top > 0 && $top > $second ? array_key_first($scores) : null,
            'scores'     => $scores,
            'keywords'   => $keywords,
        ];
    }

    // detect()-ийн Scout/Meilisearch хувилбар. Meilisearch аль skeleton аль хэлтэст
    // байгааг олно, оноог энд тоолно: үг бүр хамгийн урт таарсан skeleton-ийнхоо
    // хэлтэст 1 оноо өгнө ("халагдсан" → hlgd (Хүний нөөц), hlg (хаалга) биш).
    public function detectWithScout(string $text): array
    {
        $byWord = $this->words->searchTermsByWord($text);
        $terms  = array_values(array_unique(array_merge([], ...array_values($byWord))));
        if ($terms === []) {
            return ['department' => null, 'scores' => []];
        }

        $hits = Department::search('')->whereIn('skeletons', $terms)->raw()['hits'] ?? [];

        // [skeleton => хэлтэс] — зөвхөн query-д орсон skeleton-ууд
        $owner = [];
        foreach ($hits as $hit) {
            foreach (array_intersect($hit['skeletons'], $terms) as $skel) {
                $owner[$skel] = $hit['name'];
            }
        }

        $scores = [];
        foreach ($byWord as $wordTerms) {
            foreach ($wordTerms as $term) {          // уртаас богино руу
                if (isset($owner[$term])) {
                    $scores[$owner[$term]] = ($scores[$owner[$term]] ?? 0) + 1;
                    break;
                }
            }
        }

        arsort($scores);
        [$top, $second] = array_pad(array_values($scores), 2, 0);

        return [
            // detect()-тэй ижил: тэнцсэн эсвэл юу ч таараагүй бол null
            'department' => $top > 0 && $top > $second ? array_key_first($scores) : null,
            'scores'     => $scores,
        ];
    }

    public function departmentOf(string $keyword): ?string
    {
        foreach ($this->departments as $dept => $keywords) {
            if (in_array($keyword, $keywords, true)) {
                return $dept;
            }
        }

        return null;
    }
}
