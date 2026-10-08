<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')
                ->constrained('partners')
                ->cascadeOnDelete();

            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->longText('description');

            $table->string('employment_type');
            $table->string('work_mode');

            $table->string('location')->nullable();

            $table->unsignedInteger('min_exp')->default(0);
            $table->unsignedInteger('max_exp')->nullable();

            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();

            $table->unsignedInteger('openings')->default(1);

            $table->enum('status', [
                'draft',
                'pending_review',
                'published',
                'closed',
                'rejected',
            ])->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->unique(['partner_id', 'slug']);
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};