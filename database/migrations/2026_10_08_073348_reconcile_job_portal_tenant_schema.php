<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo_path')->nullable();
                $table->string('industry')->nullable();
                $table->string('website')->nullable();
                $table->text('about')->nullable();
                $table->enum('status', ['active', 'suspended'])->default('active');
                $table->unsignedInteger('max_active_jobs')->default(10);
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'partner_id')) {
                $table->foreignId('partner_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('partners')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'suspended'])
                    ->default('active')
                    ->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'partner_id')) {
                $table->dropForeign(['partner_id']);
                $table->dropColumn('partner_id');
            }

            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('partners');
    }
};