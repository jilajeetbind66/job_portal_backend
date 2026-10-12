<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('tagline')->nullable()->after('display_name');
            $table->string('company_size')->nullable()->after('industry');
            $table->string('location')->nullable()->after('website');
            $table->text('address')->nullable()->after('location');
            $table->string('email')->nullable()->after('address');
            $table->string('phone')->nullable()->after('email');
            $table->unsignedSmallInteger('founded_year')->nullable()->after('phone');
            $table->json('benefits')->nullable()->after('about');
            $table->json('social_links')->nullable()->after('benefits');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'tagline',
                'company_size',
                'location',
                'address',
                'email',
                'phone',
                'founded_year',
                'benefits',
                'social_links',
            ]);
        });
    }
};

