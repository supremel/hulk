<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRepaymentRecordsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('repayment_records', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->bigInteger('order_id')->comment('订单id');
            $table->string('biz_no', 32)->unique('biz_uiq')->comment(' 业务流水号');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('bank_card_id')->comment('银行卡id');
            $table->integer('type')->comment('还款类型，0:还单期，1:还剩余所有，2:还前n期，3:其他');
            $table->integer('business_type')->default(1)->comment('还款业务类型,1:会员充值,2:系统定时划扣,3:催收划扣,4:客服划扣,5:线下还款,6:砍头,7:老系统还款同步');
            $table->integer('amount')->comment('还款金额');
            $table->integer('pay_amount')->comment('支付金额（还款金额-优惠券金额）');
            $table->integer('capital')->comment('本金金额');
            $table->integer('interest')->comment('利息金额');
            $table->integer('fee')->comment('逾期费用');
            $table->integer('other_fee')->default(0)->comment('其他费用');
            $table->integer('overfulfil_amount')->default(0)->comment('超额金额，即多还金额');
            $table->string('coupon_biz_no', 32)->default('')->comment('优惠券biz_no');
            $table->integer('coupon_amount')->default(0)->comment('优惠券金额');
            $table->integer('status')->default(0)->comment('状态,-1:还款失败,0:初始化,1:还款成功');
            $table->timestamp('request_time')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('请求时间');
            $table->dateTime('finish_time')->nullable()->comment('完成时间');
            $table->string('extra', 1024)->nullable()->default('')->comment('额外信息，如还款渠道');
            $table->timestamps();
            $table->index(['created_at',], 'created_at');
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
        Schema::drop('repayment_records');
    }

}
