<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCapitalRouteRecordsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capital_route_records', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->string('biz_no', 32)->default('')->comment('业务流水号');
            $table->bigInteger('user_id')->comment('用户ID');
            $table->bigInteger('procedure_id')->comment('流程id');
            $table->string('label', 32)->default('')->comment('资方标识');
            $table->integer('need_open_account')->default(0)->comment('是否需要开户，0:不需要，1:需要');
            $table->string('extra', 512)->default('')->comment('额外信息，json格式字符串');
            $table->timestamps();
            $table->index(['user_id', 'procedure_id'], 'up');
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
        Schema::drop('capital_route_records');
    }

}
