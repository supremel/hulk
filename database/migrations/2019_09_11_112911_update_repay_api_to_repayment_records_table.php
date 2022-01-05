<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRepayApiToRepaymentRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('repayment_records', function (Blueprint $table) {
            $table->integer('repay_api')->default(0)->comment('支付接口，0：通用支付接口，1：分扣支付接口')->after('coupon_amount');
            $table->integer('status')->default(0)->comment('状态,-1:还款失败,0:初始化,1:还款成功,2:部分还款成功')->change();
            $table->string('extra', 2048)->nullable()->default('')->comment('额外信息，如还款渠道')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('repayment_records', function (Blueprint $table) {
            //
        });
    }
}
