<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProceduresTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->char('biz_no', 32)->unique('biz_uniq');
            $table->bigInteger('user_id')->index('user_id')->comment('用户 id');
            $table->integer('authed_amount')->default(0)->comment('授权金额');
            $table->integer('authed_min_amount')->default(0)->comment('授信的最低可借金额');
            $table->integer('authed_step_amount')->default(0)->comment('授信可借金额步长');
            $table->string('authed_periods', 64)->default('')->comment('授权期次，多个以“,”分割');
            $table->integer('authed_valid_days')->default(0)->comment('授信额度有效期');
            $table->integer('authed_fee_rate')->default(0)->comment('授信月息');
            $table->integer('authed_repay_type')->default(0)->comment('授信的还款方式');
            $table->integer('authed_fee_type')->default(0)->comment('授信的计息方式');
            $table->string('capital_label', 32)->default('')->comment('资方标识');
            $table->integer('need_open_account')->default(1)->comment('是否需要开户，0：不需要，1：需要');
            $table->integer('order_amount')->default(0)->comment('订单金额，单位：分');
            $table->integer('order_periods')->default(0)->comment('订单期次');
            $table->bigInteger('order_id')->default(0)->comment('订单ID');
            $table->integer('sample_id')->default(0)->comment('流程样板，0:默认（通用样板）');
            $table->integer('mode_id')->default(0)->comment('流程模式，0:默认（开户在前）');
            $table->integer('source')->default(0)->comment('事件来源, 0:自有,1:奇虎360，2:榕树,3:融360,4:小黑鱼,5:洋钱罐,6:去哪借,7:新浪');
            $table->integer('crontab_lock')->default(0)->comment('状态改变锁');
            $table->integer('status')->default(0)->comment('状态，-1:失败，0:进行中，1:成功');
            $table->integer('sub_status')->default(0)->comment('子状态，-9:提现失败，-8:放款失败，-7:授权失败，-6:进件失败，-5:第二次风控审核失败，-3:开户失败，-2:资金路由失败，-1:第一次风控审核失败，0:初始化, 1:待第一次风控，2:待资金路由，3:待开户, 4:待提借款订单，5:待第二次风控，6:待进件，7:待授权，8:待放款，9:待提现，10:成功完成');
            $table->timestamps();
            $table->index(['updated_at', 'sub_status', 'status'], 'crontab');
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
        Schema::drop('procedures');
    }

}
