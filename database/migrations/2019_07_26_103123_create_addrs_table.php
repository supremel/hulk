<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAddrsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addrs', function (Blueprint $table) {
            $table->integer('code')->primary()->comment('地区编码');
            $table->string('name', 128)->nullable()->comment('地区名称');
            $table->integer('province')->nullable()->comment('所属省编码');
            $table->integer('city')->nullable()->comment('所属市编码');
            $table->index(['province', 'city'], 'p_c_normal');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('addrs');
    }

}
