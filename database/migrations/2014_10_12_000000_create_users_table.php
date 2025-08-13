<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('userid')->nullable();
            $table->string('name');
            $table->string('email')->unique();

            $table->string('role')->default('1');
            $table->string('hq_code')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->rememberToken();

            $table->enum('status', ['-1', '0', '1'])->default('1');

            $table->string('user_manager')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamp('last_login')->nullable();

            $table->timestamps();
            $table->foreign('hq_code')->references('hq_code')->on('headquarters')->onDelete('set null');

        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }


};
