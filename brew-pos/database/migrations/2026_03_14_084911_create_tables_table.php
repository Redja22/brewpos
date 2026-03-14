<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedTinyInteger('number')->unique();
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->enum('status', ['available', 'occupied', 'reserved', 'inactive'])->default('available');
            $table->unsignedSmallInteger('position_x')->default(0);
            $table->unsignedSmallInteger('position_y')->default(0);
            $table->enum('shape', ['circle', 'square', 'rectangle'])->default('square');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
