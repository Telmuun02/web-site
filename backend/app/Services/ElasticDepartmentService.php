<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ElasticDepartmentService
{
    private WordShiftService $words;

    // Тест өөр индекс ашиглана — хөгжүүлэлтийн "departments"-ийг устгахгүйн тулд
    public function __construct(private string $index = 'departments')
    {
        // Зөвхөн words() хэрэгтэй — хоосон жагсаалт өгч DB уншихаас сэргийлнэ
        $this->words = new WordShiftService([]);
    }

    // ES асаалттай эсэх: GET /
    public function ping(): bool
    {
        try {
            return $this->http()->get('/')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // Индексийг устгаад шинээр үүсгэнэ (mapping-ийг дараа нь өөрчлөх боломжгүй).
    public function createIndex(): void
    {
        $this->deleteIndex();

        $this->http(30)->put($this->index, [
            'settings' => ['analysis' => $this->analysis()],
            'mappings' => ['properties' => [
                // Хадгалсан query өөрөө
                'query' => ['type' => 'percolator'],

                'token' => [
                    'type'            => 'text',
                    'analyzer'        => 'mn_skeleton_prefix',
                    'search_analyzer' => 'mn_skeleton',
                    // 5+ үсэгтэй skeleton-д 1 алдаа зөвшөөрөхөд зориулсан
                    'fields' => ['long' => ['type' => 'text', 'analyzer' => 'mn_skeleton_long']],
                ],

                // Таарсан үед буцаах мэдээлэл
                'department' => ['type' => 'keyword'],
                'keyword'    => ['type' => 'keyword'],
                'length'     => ['type' => 'integer'], // skeleton-ий урт
            ]],
        ])->throw();
    }

    // Байхгүй бол 404 — тоохгүй
    public function deleteIndex(): void
    {
        $this->http(30)->delete($this->index);
    }

    // departments хүснэгтийн түлхүүр үг бүрийг query болгон индекслэнэ (_bulk).
    // Буцаах утга: индекслэсэн query-ийн тоо.
    // $departments null бол DB-ээс; тест дээр шууд дамжуулна
    public function indexDepartments(?array $departments = null): int
    {
        $lines = [];
        foreach ($departments ?? Department::keywordMap() as $department => $keywords) {
            foreach ($keywords as $keyword) {
                $skeleton = $this->analyze($keyword);
                if ($skeleton === '') {
                    continue;
                }

                // Ижил хэлтэст ижил skeleton давхардвал нэг л query үлдэнэ
                $lines[] = json_encode(['index' => ['_index' => $this->index, '_id' => md5("$department|$skeleton")]]);
                $lines[] = json_encode([
                    'query'      => $this->queryFor($keyword),
                    'department' => $department,
                    'keyword'    => $keyword,
                    'length'     => strlen($skeleton),
                ], JSON_UNESCAPED_UNICODE);
            }
        }

        if ($lines === []) {
            return 0;
        }

        // _bulk нь NDJSON (мөр бүр нэг JSON) хүлээнэ, төгсгөлд заавал шинэ мөр
        $response = $this->http(30)
            ->withBody(implode("\n", $lines) . "\n", 'application/x-ndjson')
            ->post('_bulk?refresh=true')
            ->throw();

        if ($response->json('errors')) {
            throw new \RuntimeException('Elasticsearch bulk алдаа: ' . $response->body());
        }

        return count($lines) / 2;
    }

    // "tslin dtuu bn" → [
    //     'department' => 'Санхүү',
    //     'scores'     => ['Санхүү' => 1],
    //     'words'      => ['tslin' => 'цалин'],   // WordShiftService::shiftText()-тэй ижил
    // ]
    public function detect(string $text): array
    {
        $words = $this->words->words($text);
        if ($words === []) {
            return ['department' => null, 'scores' => [], 'words' => []];
        }

        // Үг бүр тусдаа document — хариунд аль үг (slot) таарсныг мэдэхийн тулд.
        // Үгийг түүхийгээр нь илгээнэ, skeleton-ийг analyzer хийнэ.
        $hits = $this->http()->post($this->index . '/_search', [
            'size'    => 100,
            '_source' => ['department', 'keyword', 'length'],
            'query'   => ['percolate' => [
                'field'     => 'query',
                'documents' => array_map(fn ($word) => ['token' => $word], $words),
            ]],
        ])->throw()->json('hits.hits', []);

        // [slot => хамгийн урт skeleton-той таарц] — урт нь илүү тодорхой
        // ("халагдсан" → халагд (Хүний нөөц), хаалга биш)
        $best = [];
        foreach ($hits as $hit) {
            foreach ($hit['fields']['_percolator_document_slot'] ?? [] as $slot) {
                if (! isset($best[$slot]) || $hit['_source']['length'] > $best[$slot]['length']) {
                    $best[$slot] = $hit['_source'];
                }
            }
        }

        // Үг бүр өөрийн хэлтэст 1 оноо
        $scores    = [];
        $corrected = [];
        foreach ($best as $slot => $match) {
            $scores[$match['department']] = ($scores[$match['department']] ?? 0) + 1;
            $corrected[$words[$slot]]     = $match['keyword'];
        }

        arsort($scores);
        [$top, $second] = array_pad(array_values($scores), 2, 0);

        return [
            // detect()-тэй ижил: тэнцсэн эсвэл юу ч таараагүй бол null
            'department' => $top > 0 && $top > $second ? array_key_first($scores) : null,
            'scores'     => $scores,
            'words'      => $corrected,
        ];
    }

    
    private function analysis(): array
    {
        $analyzer = fn (array $ending) => [
            'type'        => 'custom',
            'char_filter' => ['mn_map'],
            'tokenizer'   => 'mn_words',
            'filter'      => ['lowercase', 'mn_consonants', 'mn_dedupe', ...$ending],
        ];

        return [
            'char_filter' => [
                'mn_map' => ['type' => 'mapping', 'mappings' => $this->charMappings()],
            ],
            'tokenizer' => [
                'mn_words' => ['type' => 'pattern', 'pattern' => '[^\p{L}\p{N}]+'],
            ],
            'filter' => [
                'mn_consonants' => ['type' => 'pattern_replace', 'pattern' => '[^bcdfghjklmnpqrstvwz]', 'replacement' => ''],
                'mn_dedupe'     => ['type' => 'pattern_replace', 'pattern' => '(.)\\1+', 'replacement' => '$1'],
                'mn_min2'       => ['type' => 'length', 'min' => 2],
                'mn_min5'       => ['type' => 'length', 'min' => 5],
                'mn_prefix'     => ['type' => 'edge_ngram', 'min_gram' => 3, 'max_gram' => 30, 'preserve_original' => true],
            ],
            'analyzer' => [
                'mn_skeleton'        => $analyzer(['mn_min2']),
                'mn_skeleton_prefix' => $analyzer(['mn_min2', 'mn_prefix']),
                'mn_skeleton_long'   => $analyzer(['mn_min5']),
            ],
        ];
    }

    // WordShiftService::MAP → ES-ийн "from => to" дүрмүүд.
    // char_filter нь lowercase-ээс ӨМНӨ ажилладаг тул том үсгийн хувилбаруудыг ч нэмнэ:
    // 'ts' => 'c'  →  "ts => c", "tS => c", "Ts => c", "TS => c"
    private function charMappings(): array
    {
        $rules = [];
        foreach (WordShiftService::MAP as $from => $to) {
            foreach ($this->caseVariants($from) as $variant) {
                $rules[$variant] = "$variant => $to"; // түлхүүрээр давхардлыг хасна ("4"-ийн том үсэг байхгүй)
            }
        }

        return array_values($rules);
    }

    // "ts" → ["ts", "tS", "Ts", "TS"]
    private function caseVariants(string $text): array
    {
        $variants = [''];
        foreach (mb_str_split($text) as $char) {
            $next = [];
            foreach ($variants as $variant) {
                $next[] = $variant . mb_strtolower($char);
                $next[] = $variant . mb_strtoupper($char);
            }
            $variants = $next;
        }

        return $variants;
    }


    private function queryFor(string $keyword): array
    {
        return ['bool' => ['should' => [
            ['match' => ['token' => $keyword]],
            ['match' => ['token.long' => [
                'query'                => $keyword,
                'fuzziness'            => 1,
                'fuzzy_transpositions' => false,
            ]]],
        ]]];
    }

    // "цалин" → "cln" — индексийн mn_skeleton analyzer-ээр (ES өөрөө тооцно)
    private function analyze(string $text): string
    {
        return $this->http()->post($this->index . '/_analyze', [
            'analyzer' => 'mn_skeleton',
            'text'     => $text,
        ])->throw()->json('tokens.0.token', '');
    }

    // $timeout: мессеж шалгахад богино (чат гацахгүй), индекс үүсгэх/хадгалахад урт
    private function http(int $timeout = 3): PendingRequest
    {
        return Http::baseUrl(config('services.elastic.host') ?? 'http://localhost:9200')
            ->acceptJson()
            ->timeout($timeout);
    }
}
