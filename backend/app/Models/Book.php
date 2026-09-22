<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'isbn',
        'category_id',
        'company_id',
        'total_copies',
        'available_copies',
        'math_embedding',
        'ai_embedding',
    ];

    /**
     * API хариунд орохгүй талбарууд.
     *
     * Embedding нь мянга мянган тооны массив — mobile/React-д хэрэггүй, хариуг
     * ном бүрд хэдэн арван KB-аар томруулна. $hidden нь зөвхөн toArray()/toJson()-д
     * нөлөөлнө; $book->math_embedding гэж кодоор уншихад хэвээр ирнэ.
     */
    protected $hidden = [
        'math_embedding',
        'ai_embedding',
    ];

    /**
     * JSON багана ↔ PHP массив автомат хөрвүүлэлт.
     *
     * Cast байхгүй бол embedding string болж ирнэ — cosine() алдаа
     * гаргахгүй ч буруу тоо буцаана.
     */
    protected function casts(): array
    {
        return [
            'math_embedding' => 'array',
            'ai_embedding'   => 'array',
        ];
    }

    /**
     * Embedding хийх текст.
     *
     * Backfill, store(), update() — гурвуулаа энэ нэг функцийг дуудна.
     * Өөр өөр газар өөр өөрөөр нийлүүлбэл вектор таарахгүй тул нэг эх сурвалж.
     * Дуудахаас өмнө authors, category-г with()-ээр load хийсэн байх ёстой (N+1).
     */
    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->title,
            $this->authors->pluck('name')->implode(' '),
            $this->category?->name,
        ]));
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function authors()
    {
        return $this->belongsToMany(Author::class, 'author_book', 'book_id', 'author_id');
    }

    /**
     * Номыг эзэмшигч компани.
     *
     * Company::books()-ийн урвуу тал. Ном бүр яг нэг компанид харьяалагддаг
     * бөгөөд хэрэглэгч зөвхөн өөрийн компанийн номыг харна (BookController).
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'book_id', 'id');
    }

    /**
     * Cloudinary дээрх нүүр зургийн хүргэлтийн URL.
     *
     * Өмнө нь энэ логик BookResource дотор байсан. Загвар руу зөөсөн шалтгаан:
     * одоо BookResource, BookDetailResource хоёулаа үүнийг дуудна — хоёр
     * газар давхардуулан бичихийн оронд нэг эх сурвалжтай байх нь зөв.
     *
     * Одоогоор бүх ном ижил зураг ашиглана. Ном тус бүрийн хавтас нэмэх үед
     * зөвхөн энэ методын дотор public_id-г сольвол хангалттай.
     *
     * URL доторх хувиргалтууд Cloudinary тал дээр ажиллана:
     *   w_400,h_560  — дэлгэрэнгүй хуудсын том хавтсанд хүрэлцэхүйц
     *   c_fill       — харьцааг хадгалж, хүрээг дүүргэж таслана
     *   f_auto       — хөтөч дэмждэг бол WebP/AVIF болгож хөнгөлнө
     *   q_auto       — чанарыг автоматаар тохируулж хэмжээг багасгана
     */
    public function coverUrl(): string
    {
        $cloud    = config('services.cloudinary.cloud_name');
        $publicId = config('services.cloudinary.default_cover');

        return "https://res.cloudinary.com/{$cloud}/image/upload"
            . '/w_400,h_560,c_fill,f_auto,q_auto'
            . "/{$publicId}";
    }
}
