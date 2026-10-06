<?php

namespace App\Services;

use App\Models\Department;

// Тухайн текстийг нь алдаатай байдлаас нь хувиргах 
// cln, tsalin, tsln = цлн гэх мэтээр хувиргаж тодорхойлох
class WordShiftService
{
    private array $keywords;
 
    // ElasticDepartmentService-ийн analyzer ч энэ дүрмийг ашиглана
    public const MAP = [
        'ц' => 'c', 'ч' => 'q', 'ш' => 'w', 'щ' => 'w', 'х' => 'h', 'ж' => 'j',
        'з' => 'z', 'с' => 's', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'п' => 'p', 'р' => 'r',
        'т' => 't', 'ф' => 'f',
        'ts' => 'c', 'ch' => 'q', '4' => 'q', 'sh' => 'w', 'kh' => 'h', 'x' => 'h',
    ];

    // [араг яс => ['цалин', ...]] — нэг араг яс олон үгтэй давхцаж болох тул жагсаалт
    private ?array $index = null;

    // null бол DB-ээс; тест дээр жагсаалтыг шууд дамжуулна
    public function __construct(?array $keywords = null)
    {
        $this->keywords = $keywords ?? array_merge(...array_values(Department::keywordMap()));
    }

    public function skeleton(string $word): string
    {
        $w = strtr(mb_strtolower($word), self::MAP);
        // Эгшиг, ь, ъ болон бусад бүх тэмдэгт хасагдаж зөвхөн гийгүүлэгч үлдэнэ
        $w = preg_replace('/[^bcdfghjklmnpqrstvwz]/', '', $w);

        // Давхардал: "tsalliin" → "cll" → "cl"
        return preg_replace('/(.)\1+/', '$1', $w);
    }

    // Нэг үгийг түлхүүр үг рүү хөрвүүлнэ: "tslin" → "цалин". Олдохгүй бол null.
    public function shift(string $word): ?string
    {
        $token = $this->skeleton($word);
        if (strlen($token) < 2) {
            return null;
        }

        $best = null;
        $bestPoints = 0;
        $bestLen = 0;

        foreach ($this->index() as $skel => $words) {
            $points = $this->match($token, (string) $skel);
            // Оноо тэнцвэл урт араг яс нь илүү тодорхой таарц
            if ($points > $bestPoints || ($points === $bestPoints && $points > 0 && strlen($skel) > $bestLen)) {
                [$best, $bestPoints, $bestLen] = [$words[0], $points, strlen($skel)];
            }
        }

        return $best;
    }

    // Текстээс танигдсан бүх үгийг буцаана: "tslin dtuu bn" → ['tslin' => 'цалин']
    public function shiftText(string $text): array
    {
        $found = [];
        foreach ($this->words($text) as $word) {
            if ($keyword = $this->shift($word)) {
                $found[$word] = $keyword;
            }
        }

        return $found;
    }

    public function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    // 2 = таарсан (яг эсвэл нөхцөлтэй хэлбэр), 1 = нэг үсгийн алдаа, 0 = таараагүй
    private function match(string $token, string $skel): int
    {
        // Богино араг яс (ql, rc) — зөвхөн яг таарц. "+1 үсэг" зөвшөөрөхөд
        // "уучлаарай" (qlr) → чөлөө (ql) болж байсан
        if (strlen($skel) < 3) {
            return $token === $skel ? 2 : 0;
        }

        if (str_starts_with($token, $skel)) {
            return 2;
        }

        // Нэг үсгийн алдааг зөвхөн 5+ үсэгтэй skeleton-д ("computer" cmptr → kmptr).
        // 4 үсэгтэй дээр "маргааш → маргаан", "хэрэгтэй → хүргэлт" гэх мэт хуурамч таарц гарч байсан.
        return strlen($token) >= 5 && strlen($skel) >= 5 && levenshtein($token, $skel) <= 1 ? 1 : 0;
    }

    private function index(): array
    {
        if ($this->index === null) {
            $this->index = [];
            foreach ($this->keywords as $word) {
                $this->index[$this->skeleton($word)][] = $word;
            }
        }

        return $this->index;
    }
}
