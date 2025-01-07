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
        Schema::create('tally_sync', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->timestamp('sync_time')->useCurrent();
            $table->binary('request_data')->nullable();
            $table->binary('response_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_sync');
    }
};
