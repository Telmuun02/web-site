<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;


class ChatTools
{
    public function getBooks()
    {
        return DB::table('books')
            ->select('id', 'title', 'available_copies')
            ->limit(20)
            ->get();
    }   

    public function getCategory()
    {
        return DB::table('categories')
            ->select('id', 'name')
            ->limit(20)
            ->get();
    }

    public function getAuthors()
    {
        return DB::table('authors')
            ->select('id', 'name')
            ->limit(20)
            ->get();
    }

    public function getCompany(){
        return DB::table('companies')
            ->select('id', 'name')
            ->limit(20)
            ->get();
    }
}
