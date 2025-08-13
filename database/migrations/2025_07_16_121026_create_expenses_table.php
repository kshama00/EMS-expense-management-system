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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->comment('ID of the user who submitted the expense');
            $table->date('date')->comment('Date of the expense');
            $table->string('type')->comment('1=Travel, 2=Lodging, 3=Food, 4=Printing, 5=Mobile, 6=Miscellaneous');
            $table->string('location')->comment('current loaction of the user from where the expense was made');
            $table->text('remarks')->nullable()->comment('Any remarks for the expense');
            $table->decimal('amount', 10, 2)->comment('Submitted expense amount');
            $table->decimal('approved_amount', 10, 2)->nullable()->comment('Amount approved by the admin');
            $table->json('meta_data')->nullable()->comment('Additional dynamic data for the expense');

            $table->enum('status', ['1', '2', '3', '4', '5'])->default('1')->comment('1=Pending, 2=Approved, 3=Rejected, 4=Partially Approved, 5=Cancelled');

            $table->unsignedBigInteger('approved_by')->nullable()->comment('User ID of the approver');
            $table->dateTime('approved_at')->nullable()->comment('Datetime when the expense was approved');
            $table->text('admin_comment')->nullable()->comment('Comment by the admin during approval/rejection');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expenses');
    }
};
