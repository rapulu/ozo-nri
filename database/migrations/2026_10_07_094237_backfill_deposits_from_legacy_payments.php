<?php

use App\Models\Deposit;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Deposit::backfillFromPayments();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank: backfilled rows mix with later deposits.
    }
};
