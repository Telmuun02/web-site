<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookDetailResource;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Номын CRUD үйлдлүүд.
 *
 * Ингэхдээ аль хэдийн өгөгдлийн сангаас 
 * татсан өгөгдлүүдийг татаж дараагаар нь харах боломжтой болгоно.
 */
class BookController extends Controller
{
    /**
     * Номын жагсаалт — шүүлтүүр, хайлт, хуудаслалттай.  GET /api/books
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::with(['category', 'authors', 'company']);

        $user = $request->user();
        $scope = 'all';

        if ($user->role !== 'admin') {
            $query->where('company_id', $user->company_id);
            $scope = "c{$user->company_id}";
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $name = $request->category;
            $query->whereHas('category', fn ($q) => $q->where('name', $name));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('authors', fn ($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->availability === 'available') {
            $query->where('available_copies', '>', 0);
        } elseif ($request->availability === 'checked-out') {
            $query->where('available_copies', '=', 0);
        }

        $version = Cache::get('books.version', 1);

        $page         = $request->input('page', 1);
        $category     = $request->input('category', 'all');
        $search       = $request->input('search', '');
        $availability = $request->input('availability', '');

        $key = "books.v{$version}.{$scope}.{$category}.{$search}.{$availability}.page.{$page}";

        $data = Cache::remember($key, 60, fn () => BookResource::collection($query->paginate(8))->response()->getData(true));

        return response()->json($data);
    }

    /**
     * Шинэ ном үүсгэх.  POST /api/books
     *
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'isbn'           => 'required|string|unique:books',
            'category_id'    => 'required|exists:categories,id',
            'total_copies'   => 'required|integer|min:0',
            'author_ids'     => 'array',            // сонголтоор зохиолчдын id жагсаалт
            'author_ids.*'   => 'exists:authors,id',
        ]);

        $book = Book::create([
            'title'            => $validated['title'],
            'isbn'             => $validated['isbn'],
            'category_id'      => $validated['category_id'],
            'total_copies'     => $validated['total_copies'],
            'available_copies' => $validated['total_copies'],
        ]);

        if (! empty($validated['author_ids'])) {
            $book->authors()->attach($validated['author_ids']);
        }

        Cache::put('books.version', Cache::get('books.version', 1) + 1);

        return response()->json($book->load(['category', 'authors']), 201);
    }

    /**
     * Нэг номын дэлгэрэнгүй.  GET /api/books/{book}
     *
     * Resource ашигласан нь чухал: index() ч мөн resource буцаадаг тул нэг
     * entity хоёр өөр хэлбэртэй байхаас сэргийлнэ. Мөн cover_url энд ирнэ.
     */
    public function show(Request $request, Book $book): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $book->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Энэ ном таны компанид харьяалагдахгүй байна.',
            ], 403);
        }

        $book->load(['category', 'authors', 'company']);

        return response()->json(new BookDetailResource($book));
    }

    /**
     * Ном засварлах.  PUT /api/books/{book}
     *
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'isbn'         => 'sometimes|required|string|unique:books,isbn,' . $book->id,
            'category_id'  => 'sometimes|required|exists:categories,id',
            'total_copies' => 'sometimes|required|integer|min:0',
            'author_ids'   => 'array',
            'author_ids.*' => 'exists:authors,id',
        ]);

        $book->update($validated);

        if ($request->has('author_ids')) {
            $book->authors()->sync($validated['author_ids'] ?? []);
        }

        Cache::put('books.version', Cache::get('books.version', 1) + 1);

        return response()->json($book->load(['category', 'authors']));
    }

    /**
     * Ном устгах.  DELETE /api/books/{book}
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        Cache::put('books.version', Cache::get('books.version', 1) + 1);

        return response()->json(['message' => 'Ном устгагдлаа.']);
    }

    public function search(Request $request, EmbeddingService $embeddings): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $user      = $request->user();
        $companyId = $user->role === 'admin' ? null : $user->company_id;

        $results = $embeddings->search($request->q, 10, $companyId);

        return BookResource::collection($results->pluck('book'))->response();
    }
}
