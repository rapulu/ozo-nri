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
        Schema::create('condolences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('deceased_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->decimal('amount_per_member', 12, 2)->default(0);
            $table->date('date_announced');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condolences');
    }
};
