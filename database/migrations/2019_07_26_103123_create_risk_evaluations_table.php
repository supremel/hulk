<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRiskEvaluationsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('risk_evaluations', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->char('biz_no', 32)->unique('biz_uniq')->comment('业务流水号');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('procedure_id')->default(0)->comment('流程id');
            $table->integer('num')->default(1)->comment('第几次');
            $table->integer('trigger_type')->default(0)->comment('触发类型,0:用户主动，1:系统触发');
            $table->integer('amount')->default(0)->comment('授权金额');
            $table->integer('min_amount')->default(0)->comment('最低可借额度');
            $table->integer('step_amount')->default(0)->comment('可借额度的步长');
            $table->char('cate', 64)->default('')->comment('授权期次, 多个以”,"分割');
            $table->integer('valid_days')->default(0)->comment('额度有效期');
            $table->string('remark', 256)->nullable()->comment('决策标记(拒绝原因)');
            $table->integer('vendor')->nullable()->comment('建议放款资金方,1:笑脸');
            $table->integer('score')->nullable()->comment('决策打分');
            $table->integer('fee_rate')->nullable()->comment('费率');
            $table->integer('repay_type')->nullable()->comment('还款方式，1:按月还款，2:固定还款日，3:到期一次性还本付息');
            $table->integer('fee_type')->nullable()->comment('计息方式，1:等本等息');
            $table->string('unvalid_list', 128)->nullable()->comment('需要清空的授权项，[7,8]');
            $table->integer('freeze')->nullable()->comment('拒绝用户冻结时间，天数');
            $table->integer('is_submit')->default(0)->comment('是否已提交，0：未提交，1：已提交');
            $table->integer('no_operate')->default(0)->comment('是否操作了 流程表、用户表 关联 额度、认证失效 项，0：已操作，1：未操作');
            $table->string('extra')->default('')->comment('额外信息, 如授信结果说明');
            $table->dateTime('request_time')->nullable()->comment('请求时间');
            $table->dateTime('finish_time')->nullable()->comment('完成时间');
            $table->integer('status')->default(0)->comment('授信状态, -1:授信失败,0:初始化,1:授信成功');
            $table->timestamps();
            $table->index(['user_id', 'procedure_id'], 'up');
            $table->index(['user_id', 'created_at'], 'uc');
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
        Schema::drop('risk_evaluations');
    }

}
