<?php

namespace App\Services;

class ToolDefinitions
{
    public static function all(): array
    {
        return [
            [
                'name'        => 'get_library_overview',
                'description' => 'Номын сангийн ерөнхий тойм НЭГ дуудлагаар: нийт ном, зохиолч, ангилал, байгууллагын тоо, нийт/боломжит хувь, ангилал тус бүрийн номын тоо. "Юу юу байна", "ерөнхийдөө", "хэдэн ном байна" гэх мэт ерөнхий асуултад эхлээд үүнийг ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_books',
                'description' => 'Номын сангийн бүх номын жагсаалт, үлдэгдлийн хамт. Ямар ном байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_category',
                'description' => 'Номын ангиллуудын жагсаалт (Fiction, Science, Technology г.м.). Ямар ангилал байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_authors',
                'description' => 'Номын сангийн зохиолчдын жагсаалт. Ямар зохиолчийн ном байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_company',
                'description' => 'Системд бүртгэлтэй байгууллага (компани)-уудын жагсаалт. Ямар байгууллагууд байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'search_books',
                'description' => 'Номыг нэр, сэдэв, түлхүүр үгээр хайна (утгын ойролцоогоор). Хэрэглэгч тодорхой ном, сэдэв хайж байвал ашигла. Жишээ: "програмчлалын ном", "Harry Potter".',
                'input_schema' => self::withInput([
                    'query' => ['type' => 'string', 'description' => 'Хайх үг — заавал АНГЛИ хэлээр (номын өгөгдөл англиар байгаа). Хэрэглэгч монголоор бичсэн бол англи руу орчуулж өг. Жишээ: "шинжлэх ухаан" → "science".'],
                    'limit' => ['type' => 'integer', 'description' => 'Хэдэн үр дүн буцаах (1-10, анхдагч 5)'],
                ], ['query']),
            ],
            [
                'name'        => 'get_book_details',
                'description' => 'Нэг номын дэлгэрэнгүй: зохиолч, ангилал, байгууллага, нийт болон боломжит хувь. Тодорхой нэг номын талаар асуухад ашигла.',
                'input_schema' => self::withInput([
                    'title' => ['type' => 'string', 'description' => 'Номын нэр (бүтэн эсвэл хэсэг)'],
                ], ['title']),
            ],
            [
                'name'        => 'get_books_by_author',
                'description' => 'Тухайн зохиолчийн бичсэн номуудыг үлдэгдлийн хамт буцаана. "X-ийн ном байгаа юу?" гэх мэт асуултад ашигла.',
                'input_schema' => self::withInput([
                    'author' => ['type' => 'string', 'description' => 'Зохиолчийн нэр (бүтэн эсвэл хэсэг)'],
                ], ['author']),
            ],
            [
                'name'        => 'get_books_by_category',
                'description' => 'Тухайн ангиллын номуудыг үлдэгдлийн хамт буцаана. "Шинжлэх ухааны ном юу байна?" гэх мэт асуултад ашигла.',
                'input_schema' => self::withInput([
                    'category' => ['type' => 'string', 'description' => 'Ангиллын нэр (жишээ: Fiction, Science)'],
                ], ['category']),
            ],
            [
                'name'        => 'get_available_books',
                'description' => 'Одоо зээлж авах боломжтой (үлдэгдэлтэй) номуудын жагсаалт. "Одоо юу авч болох вэ?" гэх мэт асуултад ашигла.',
                'input_schema' => self::withInput([
                    'limit' => ['type' => 'integer', 'description' => 'Хэдэн ном буцаах (1-20, анхдагч 10)'],
                ]),
            ],
            [
                'name'        => 'get_popular_books',
                'description' => 'Хамгийн их зээлэгдсэн номууд (зээлийн тоогоор эрэмбэлсэн). "Алдартай", "их уншдаг", "санал болгох" ном асуухад ашигла.',
                'input_schema' => self::withInput([
                    'limit' => ['type' => 'integer', 'description' => 'Хэдэн ном буцаах (1-10, анхдагч 5)'],
                ]),
            ],
            [
                'name'        => 'get_loans',
                'description' => 'Нэвтэрсэн хэрэглэгчийн ӨӨРИЙН зээлсэн номууд: номын нэр, зээлсэн огноо, буцаах огноо, хугацаа хэтэрсэн эсэх. "Миний зээлсэн ном", "би юу авсан бэ", "хэзээ буцаах вэ" гэх мэт асуултад ашигла.',
                'input_schema' => self::withInput([
                    'status' => [
                        'type'        => 'string',
                        'enum'        => ['active', 'overdue', 'returned', 'all'],
                        'description' => 'active = одоо гартаа байгаа (анхдагч), overdue = хугацаа хэтэрсэн, returned = буцаасан, all = бүгд',
                    ],
                ]),
            ],
        ];
    }

    private static function noInput(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
        ];
    }

    private static function withInput(array $properties, array $required = []): array
    {
        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
