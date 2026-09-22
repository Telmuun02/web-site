<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Номын текстийн embedding векторууд — арга тус бүрд тусдаа багана.
            // Хоёр өөр аргын векторыг хооронд нь харьцуулж болохгүй тул холихгүй.
            $table->json('math_embedding')->nullable();   // n-gram + hashing (AI-гүй)
            $table->json('ai_embedding')->nullable();     // Gemini гэх мэт AI model
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Зөвхөн байгаа баганыг устгана — өмнөх хувилбаруудаар (embedding,
            // embedding_source) ажилласан DB дээр rollback хийхэд алдаа гарахгүй.
            $columns = array_filter(
                ['embedding', 'embedding_source', 'math_embedding', 'ai_embedding'],
                fn ($col) => Schema::hasColumn('books', $col)
            );
            $table->dropColumn(array_values($columns));
        });
    }
};
