<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedSmallInteger('width')->nullable()->after('position_y');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
