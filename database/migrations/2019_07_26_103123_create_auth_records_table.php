<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAuthRecordsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auth_records', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->char('biz_no', 32)->unique('biz_uniq')->comment('业务流水号');
            $table->bigInteger('user_id')->comment('用户id');
            $table->bigInteger('procedure_id')->comment('流程id');
            $table->string('capital_label', 32)->default('')->comment('资方标识');
            $table->bigInteger('order_id')->comment('订单id');
            $table->integer('type')->default(0)->comment('授权类型,0:放款授权');
            $table->string('extra', 512)->default('')->comment('额外信息，json格式字符串');
            $table->integer('is_submit')->default(0)->comment('是否已提交，0：未提交，1：已提交');
            $table->integer('no_operate')->default(0)->comment('是否操作了 流程表、用户表 关联 额度、认证失效 项，0：已操作，1：未操作');
            $table->dateTime('request_time')->nullable()->comment('授权发起时间');
            $table->dateTime('finish_time')->nullable()->comment('授权完成时间');
            $table->integer('status')->default(0)->comment('状态，-1:授权失败，0:初始化，1:授权成功');
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
        Schema::drop('auth_records');
    }

}
