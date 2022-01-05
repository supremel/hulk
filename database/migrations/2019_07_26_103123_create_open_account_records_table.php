<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOpenAccountRecordsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('open_account_records', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->string('biz_no', 32)->default('')->unique('biz_uniq')->comment('业务流水号');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('procedure_id')->comment('流程id');
            $table->string('capital_label', 32)->default('')->comment('资方标识');
            $table->string('bank_code', 32)->default('')->comment('银行编码');
            $table->string('card_no', 64)->default('')->comment('银行卡号');
            $table->string('extra')->default('')->comment('额外信息, 如开户结果说明');
            $table->integer('is_submit')->default(0)->comment('是否已提交，0：未提交，1：已提交');
            $table->integer('no_operate')->default(0)->comment('是否操作了 流程表、用户表 关联 额度、认证失效 项，0：已操作，1：未操作');
            $table->dateTime('request_time')->nullable()->comment('开户发起时间');
            $table->dateTime('finish_time')->nullable()->comment('开户完成时间');
            $table->integer('status')->default(0)->comment('状态，-1:开户失败, 0:开户中,1:开户成功');
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
        Schema::drop('open_account_records');
    }

}
