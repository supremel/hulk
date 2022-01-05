<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRepayInstallmentRefTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('repay_installment_ref', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->bigInteger('repayment_id')->comment('还款记录id');
            $table->bigInteger('order_id')->comment('订单id');
            $table->bigInteger('installment_id')->comment('还款计划id');
            $table->integer('capital')->comment('本金');
            $table->integer('interest')->comment('利息');
            $table->integer('fee')->default(0)->comment('逾期费');
            $table->integer('other_fee')->default(0)->comment('其他费用');
            $table->integer('status')->default(0)->comment('状态,-1:还款失败,0:初始化,1:还款成功');
            $table->timestamps();
            $table->index(['repayment_id', 'installment_id'], 'repay_install');
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
        Schema::drop('repay_installment_ref');
    }

}
