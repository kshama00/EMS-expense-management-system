<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('headquarters', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key

            $table->string('fin_year', 20);
            $table->string('hq_name', 200);
            $table->string('hq_code', 10)->unique();
            $table->string('territory_code', 50);
            $table->string('territory_name', 50);
            $table->string('region_code', 50);
            $table->string('region_name', 50);
            $table->string('zone_code', 50);
            $table->string('zone_name', 50);
            $table->string('division_code', 50);
            $table->string('division_name', 50);
            $table->string('sbu_code', 50);
            $table->string('sbu_name', 50);
            $table->string('department_code', 50);
            $table->string('department_name', 50);
            $table->enum('type', ['0', '1', '2', '3'])->comment('0: Retail 1: ASO 2: MA 3: CSD');
            $table->longText('brand');
            $table->enum('status', ['-1', '0', '1']);
            $table->string('state', 100);
            $table->string('lang', 50);
            $table->string('created_by', 100)->nullable();

            $table->timestamps();


        });
    }

    public function down()
    {
        Schema::dropIfExists('headquarters');
    }
};
