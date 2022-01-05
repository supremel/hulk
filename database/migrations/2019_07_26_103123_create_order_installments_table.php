<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOrderInstallmentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_installments', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('order_id')->comment('订单id');
            $table->integer('period')->default(0)->comment('期次');
            $table->integer('capital')->default(0)->comment('本金');
            $table->integer('paid_capital')->default(0)->comment('已还本金');
            $table->integer('interest')->default(0)->comment('利息');
            $table->integer('paid_interest')->default(0)->comment('已还利息');
            $table->integer('fee')->default(0)->comment('逾期费');
            $table->integer('paid_fee')->default(0)->comment('已还逾期费');
            $table->integer('other_fee_type')->default(0)->comment('其他费用类型，0:无，1:提前还款手续费,2:会员费(砍头失败)');
            $table->integer('other_fee')->default(0)->comment('其他费用');
            $table->integer('paid_other_fee')->default(0)->comment('已还其他费用');
            $table->integer('other_fee_capital')->default(0)->comment('其他费用中的本金部分');
            $table->integer('overdue_days')->index('overdue_days')->default(0)->comment('逾期天数');
            $table->date('date')->index('date')->comment('还款日');
            $table->dateTime('pay_off_time')->nullable()->comment('还清时间');
            $table->integer('status')->default(4)->comment('状态, 4:待还款,5:已结清');
            $table->timestamps();
            $table->index(['order_id', 'period', 'date'], 'uo');
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
        Schema::drop('order_installments');
    }

}
