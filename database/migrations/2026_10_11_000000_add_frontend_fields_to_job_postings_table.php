<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->text('requirements')->nullable();
            $table->json('skills')->nullable();
            $table->string('currency', 3)->default('INR');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['department', 'requirements', 'skills', 'currency']);
        });
    }
};