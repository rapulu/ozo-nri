<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('condolences', function (Blueprint $table) {
            $table->unique('deceased_member_id', 'condolences_deceased_member_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('condolences', function (Blueprint $table) {
            $table->dropUnique('condolences_deceased_member_id_unique');
        });
    }
};
