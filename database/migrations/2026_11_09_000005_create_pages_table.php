<?php

use App\Support\DefaultPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Information pages (About us, Terms, Privacy...), editable under Setup > Website pages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->string('meta_description', 300)->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (DefaultPages::all() as $slug => [$title, $sort, $meta, $body]) {
            DB::table('pages')->insert([
                'slug' => $slug, 'title' => $title, 'meta_description' => $meta, 'body' => $body,
                'is_published' => true, 'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
