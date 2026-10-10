<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_postings', 'rejection_note')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->text('rejection_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_postings', 'rejection_note')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->dropColumn('rejection_note');
            });
        }
    }
};
