<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Нээлттэй route-ууд (нэвтрэхгүйгээр хандах боломжтой)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Баталгаажуулах холбоосыг дахин илгээх — НЭЭЛТТЭЙ байх ёстой.
// Хатуу горимд баталгаажаагүй хэрэглэгчид token байхгүй тул auth дор тавьж болохгүй.
// throttle:5,1 — минутад 5 удаа. Үүнгүйгээр спам илгээх суваг болно.
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:5,1')
    ->name('verification.send');

// Ангилал, зохиолчийг зочид ч үзэж болно
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/authors', [AuthorController::class, 'index']);
Route::get('/authors/{author}', [AuthorController::class, 'show']);

// Чат туслах. POST — хэрэглэгчийн мессеж body-д ирнэ. auth дор байгаа нь
// Anthropic API key-ээ зочдод үрэхгүй байх хамгаалалт.
// throttle:20,1 — нэг хэрэглэгч минутад 20 мессеж; API нь төлбөртэй тул заавал.
Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:10,1');
Route::post('/chat/reset', [ChatController::class, 'reset'])->middleware('throttle:10,1');

// АНХААР: Ном ХАРАХ нь нээлттэй БАЙХАА больсон.
// Ном бүр компанид харьяалагддаг тул хэрэглэгч зөвхөн өөрийн компанийн
// номыг харах ёстой. Зочинд ямар компани гэдгийг тодорхойлох боломжгүй
// учир нэвтрэхийг шаардана — доорх auth:api бүлгийг үзнэ үү.

// prefix
// mailable
// config
// mail -> smtp eer ashiglana. 587

/*
|--------------------------------------------------------------------------
| Нэвтэрсэн хэрэглэгч
|--------------------------------------------------------------------------
| Хатуу горимд token нь зөвхөн баталгаажсан хэрэглэгчид олгогддог тул
| энд байгаа хэн боловч аль хэдийн баталгаажсан байна.
*/
Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Ном үзэх — нэвтэрсэн байх шаардлагатай. Хэрэглэгч зөвхөн өөрийн
    // компанийн номыг, админ бүгдийг харна (BookController-д шүүгдэнэ).
    Route::get('/books', [BookController::class, 'index']);
    // /books/{book}-оос ӨМНӨ байх ёстой — эс бөгөөс "search"-ийг ном id гэж уншина.
    Route::get('/books/search', [BookController::class, 'search']);
    Route::get('/books/{book}', [BookController::class, 'show']);
});

// 'verified' middleware нь JSON бус хүсэлтийг энэ нэртэй route руу чиглүүлэхийг
// оролддог тул тодорхойлж өгөх шаардлагатай.
Route::get('/email/verify', fn () => response()->json([
    'message' => 'И-мэйл хаягаа баталгаажуулна уу.',
], 403))->name('verification.notice');

/*
|--------------------------------------------------------------------------
| Хамгаалагдсан route-ууд (token + и-мэйл баталгаажсан байх шаардана)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'verified'])->group(function () {
    // --- Зөвхөн админ (токенд тухайн scope байх ёстой) ---
    // scopes: middleware нь контроллер руу ОРОХООС ӨМНӨ зогсооно. Эрхийн
    // дүрэм route файлыг уншихад л харагдаж байгаа нь давуу тал.
    Route::apiResource('categories', CategoryController::class)
        ->except(['index', 'show'])
        ->middleware('scopes:catalog:manage');

    Route::apiResource('authors', AuthorController::class)
        ->except(['index', 'show'])
        ->middleware('scopes:catalog:manage');

    Route::apiResource('books', BookController::class)
        ->except(['index', 'show'])
        ->middleware('scopes:books:manage');

    // --- Бүх нэвтэрсэн хэрэглэгч ---
    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::put('/loans/{loan}/return', [LoanController::class, 'returnBook']);

    // --- Зөвхөн админ ---
    Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])
        ->middleware('scopes:loans:manage');
});
