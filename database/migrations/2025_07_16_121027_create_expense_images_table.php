<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expense_images', function (Blueprint $table) {
            $table->id()->comment('Primary key for the expense images');
            $table->unsignedBigInteger('expense_id')->comment('Foreign key referencing the expenses.id');
            $table->string('path')->comment('Path to the uploaded image');
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expense_images');
    }
};
