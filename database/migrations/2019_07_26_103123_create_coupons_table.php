<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCouponsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增ID');
            $table->bigInteger('user_id')->comment('用户id');
            $table->char('biz_no', 32)->comment('流水号');
            $table->integer('amount')->comment('优惠券金额');
            $table->integer('type')->comment('优惠券类型，0:还款抵扣券');
            $table->integer('min_amount')->comment('最低使用金额');
            $table->dateTime('start_time')->nullable()->comment('开始时间');
            $table->dateTime('end_time')->nullable()->comment('结束时间');
            $table->integer('status')->default(0)->comment('状态，0:待使用,1:已使用,2:已过期');
            $table->string('extra', 1024)->comment('额外信息，如发放来源');
            $table->timestamps();
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
        Schema::drop('coupons');
    }

}
