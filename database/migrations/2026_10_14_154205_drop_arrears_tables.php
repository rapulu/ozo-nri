<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('arrear_payments');
        Schema::dropIfExists('arrears');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tables removed with the arrears feature; no rollback.
    }
};
