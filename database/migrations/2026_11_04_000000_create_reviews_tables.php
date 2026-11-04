<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('place_id')->nullable();
            $table->string('place_name');
            $table->string('place_address')->nullable();
            $table->json('questions');
            $table->unsignedTinyInteger('stars');
            $table->string('length', 20);
            $table->string('tone', 20);
            $table->timestamps();
        });

        Schema::create('review_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('instruction')->nullable();
            $table->string('model');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_versions');
        Schema::dropIfExists('reviews');
    }
};
