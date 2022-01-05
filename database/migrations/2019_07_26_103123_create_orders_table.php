<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->char('biz_no', 32)->default('')->unique('biz_uniq')->comment('业务流水号');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('procedure_id')->comment('流程id');
            $table->string('capital_label', 32)->default('')->comment('资方标识');
            $table->integer('amount')->comment('金额，单位：分');
            $table->integer('periods')->comment('期次');
            $table->integer('periods_type')->default(0)->comment('期次类型，0:月，1:日');
            $table->integer('interest_rate')->comment('利率，万分位，3%=300');
            $table->integer('loan_usage')->comment('借款用途');
            $table->integer('source')->default(0)->comment('事件来源, 0:自有,1:奇虎360，2:榕树,3:融360,4:小黑鱼,5:洋钱罐,6:去哪借,7:新浪');
            $table->integer('repay_type')->default(0)->comment('授信的还款方式');
            $table->integer('fee_type')->default(0)->comment('授信的计息方式');
            $table->string('capital_loan_usage', 16)->comment('资方的借款用途');
            $table->dateTime('loaned_date')->nullable()->comment('放款完成时间');
            $table->dateTime('withdrawed_date')->nullable()->comment('提现完成时间');
            $table->dateTime('procedure_finish_date')->nullable()->comment('流程完成时间');
            $table->dateTime('pay_off_date')->nullable()->comment('还清时间');
            $table->integer('status')->default(0)->comment('状态，-1:放款失败，0:初始化，1:审核中，2:放款中，3:提现中, 4:还款中（已放款），5:已结清');
            $table->timestamps();
            $table->index(['user_id', 'created_at'], 'uc');
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
        Schema::drop('orders');
    }

}
