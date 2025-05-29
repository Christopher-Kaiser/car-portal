<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ride_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_id')->constrained('car_requests')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed'])->default('pending');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->json('pickup_coordinates');
            $table->json('dropoff_coordinates');
            $table->integer('passenger_count');
            $table->timestamp('estimated_pickup_time')->nullable();
            $table->timestamp('estimated_dropoff_time')->nullable();
            $table->integer('detour_distance')->comment('in meters');
            $table->integer('detour_duration')->comment('in seconds');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ride_shares');
    }
}; 