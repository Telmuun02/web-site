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
