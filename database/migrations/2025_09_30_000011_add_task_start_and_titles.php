<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestampTz('actual_start_at_src')->nullable();
            $table->string('request_title')->nullable();
            $table->string('problem_title')->nullable();
            $table->string('change_title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['actual_start_at_src','request_title','problem_title','change_title']);
        });
    }
};

