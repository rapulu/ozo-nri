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
        Schema::create('arrear_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrear_id')->constrained('arrears')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('payment_method', 30)->default('cash');
            $table->string('reference')->nullable();
            $table->string('reason', 30)->default('other');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arrear_payments');
    }
};
