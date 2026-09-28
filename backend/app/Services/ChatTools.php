<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Company;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class ChatTools
{
    private const BOOK_COLUMNS = ['id', 'title', 'category_id', 'company_id', 'total_copies', 'available_copies'];

    // Чатлаж буй хэрэглэгч. Зочин бол null.
    public function __construct(private ?User $user = null)
    {
    }

    public function getBooks(): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select('title', 'available_copies')
            ->limit(20)
            ->get();
    }

    public function getCategory()
    {
        return DB::table('categories')
            ->select('name')
            ->limit(20)
            ->get();
    }

    public function getAuthors()
    {
        return DB::table('authors')
            ->select('name')
            ->limit(20)
            ->get();
    }

    // Энгийн хэрэглэгч зөвхөн өөрийн компанийг, admin бүгдийг харна
    public function getCompany(): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return Company::select('name')
            ->when($this->companyId(), fn ($q, $id) => $q->where('id', $id))
            ->limit(20)
            ->get();
    }

    // Мөр биш, зөвхөн тоо — нэг дуудлагаар бүх тойм, хамгийн бага өгөгдөл
    public function getLibraryOverview(): array
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return [
            'books'             => $this->books()->count(),
            'authors'           => Author::count(),
            'categories'        => Category::count(),
            'total_copies'      => (int) $this->books()->sum('total_copies'),
            'available_copies'  => (int) $this->books()->sum('available_copies'),
            'books_by_category' => Category::withCount(['books' => fn ($q) => $this->scopeCompany($q)])
                ->pluck('books_count', 'name'),
        ];
    }

    public function searchBooks(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        $limit = $this->limit($input, 5, 10);

        return app(BookSearchService::class)
            ->search($input['query'] ?? '', $limit, $this->companyId())
            ->map(fn (array $r) => $this->summary($r['book']) + [
                'score' => round($r['score'], 2),
            ]);
    }

    public function getBookDetails(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select(self::BOOK_COLUMNS)
            ->with('authors', 'category', 'company')
            ->where('title', 'like', '%' . ($input['title'] ?? '') . '%')
            ->limit(3)
            ->get()
            ->map(fn (Book $b) => [
                'title'            => $b->title,
                'authors'          => $b->authors->pluck('name'),
                'category'         => $b->category?->name,
                'company'          => $b->company?->name,
                'total_copies'     => $b->total_copies,
                'available_copies' => $b->available_copies,
            ]);
    }

    public function getBooksByAuthor(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select(self::BOOK_COLUMNS)
            ->with('authors')
            ->whereHas('authors', fn ($q) => $q->where('name', 'like', '%' . ($input['author'] ?? '') . '%'))
            ->limit(20)
            ->get()
            ->map(fn (Book $b) => $this->summary($b));
    }

    public function getBooksByCategory(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select(self::BOOK_COLUMNS)
            ->with('authors')
            ->whereHas('category', fn ($q) => $q->where('name', 'like', '%' . ($input['category'] ?? '') . '%'))
            ->limit(20)
            ->get()
            ->map(fn (Book $b) => $this->summary($b));
    }

    public function getAvailableBooks(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select(self::BOOK_COLUMNS)
            ->with('authors')
            ->where('available_copies', '>', 0)
            ->orderByDesc('available_copies')
            ->limit($this->limit($input, 10, 20))
            ->get()
            ->map(fn (Book $b) => $this->summary($b));
    }

    public function getPopularBooks(array $input): array|Collection
    {
        if ($error = $this->loginRequired()) {
            return $error;
        }

        return $this->books()
            ->select(self::BOOK_COLUMNS)
            ->with('authors')
            ->withCount('loans')
            ->orderByDesc('loans_count')
            ->limit($this->limit($input, 5, 10))
            ->get()
            ->map(fn (Book $b) => $this->summary($b) + [
                'times_borrowed' => $b->loans_count,
            ]);
    }

    // Нэвтэрсэн хэрэглэгчийн өөрийн зээл
    public function getLoans(array $input): array|Collection
    {
        if (! $this->user) {
            return ['error' => 'Хэрэглэгч нэвтрээгүй байна. Зээлээ харахын тулд нэвтэрнэ үү.'];
        }

        $today = now()->toDateString();

        return Loan::with('book:id,title')
            ->where('user_id', $this->user->id)
            ->when($input['status'] ?? 'active', fn ($q, $status) => match ($status) {
                'overdue'  => $q->whereNull('return_date')->where('due_date', '<', $today),
                'returned' => $q->whereNotNull('return_date'),
                'all'      => $q,
                default    => $q->whereNull('return_date'),
            })
            ->orderByDesc('loan_date')
            ->limit(20)
            ->get()
            ->map(fn (Loan $l) => [
                'title'       => $l->book?->title,
                'loan_date'   => $l->loan_date,
                'due_date'    => $l->due_date,
                'return_date' => $l->return_date,
                'overdue'     => $l->return_date === null && $l->due_date < $today,
            ]);
    }

    // BookController::search()-тэй ижил дүрэм: admin бол null (бүх компани),
    // энгийн хэрэглэгч бол өөрийн company_id
    private function companyId(): ?int
    {
        return $this->user?->role === 'admin' ? null : $this->user?->company_id;
    }

    // Бүх номын query эндээс эхэлнэ — компанийн шүүлтийг мартахаас сэргийлнэ
    private function books(): Builder
    {
        return $this->scopeCompany(Book::query());
    }

    private function scopeCompany(Builder $query): Builder
    {
        return $query->when($this->companyId(), fn ($q, $id) => $q->where('company_id', $id));
    }

    // Ном компанид харьяалагддаг тул зочинд ямар номыг харуулахаа мэдэхгүй
    private function loginRequired(): ?array
    {
        return $this->user
            ? null
            : ['error' => 'Номын мэдээлэл харахын тулд нэвтэрнэ үү.'];
    }

    // Claude-д очих номын хамгийн бага мэдээлэл: id, isbn, timestamp-гүй
    private function summary(Book $b): array
    {
        return [
            'title'     => $b->title,
            'authors'   => $b->authors->pluck('name'),
            'available' => $b->available_copies,
        ];
    }

    // Claude ямар ч тоо илгээж болно — 1..$max хооронд барина
    private function limit(array $input, int $default, int $max): int
    {
        return min(max((int) ($input['limit'] ?? $default), 1), $max);
    }
}
