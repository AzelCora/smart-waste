<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_events', function (Blueprint $table) {
            $table->id();
            $table->string('bin_id');
            $table->decimal('location_x', 10, 7);
            $table->decimal('location_y', 10, 7);
            $table->string('type');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index('bin_id');
            $table->index('type');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_events');
    }
};
