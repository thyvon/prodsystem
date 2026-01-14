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
        Schema::create('timelines', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation: links timeline to any model
            $table->morphs('timelineable'); // timelineable_type, timelineable_id

            $table->string('title'); // Short title of the event
            $table->text('description')->nullable(); // Details about the event
            $table->foreignId('user_id')->constrained()->onDelete('restrict'); // Who triggered the event
            $table->datetime('event_date')->nullable(); // When the event occurred
            $table->string('event_type')->nullable(); // Optional type of event
            $table->json('meta')->nullable(); // Optional extra info in JSON

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timelines');
    }
};
