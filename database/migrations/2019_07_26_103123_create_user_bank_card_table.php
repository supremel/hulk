<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserBankCardTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_bank_card', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('profile id');
            $table->bigInteger('user_id')->index('user_id')->comment('用户id');
            $table->integer('type')->default(0)->comment('类型，0:认证银行卡, 1:还款银行卡');
            $table->integer('card_type')->default(0)->comment('银行卡类型，0:借记卡,1:贷记卡');
            $table->string('card_no', 64)->default('')->comment('银行卡号');
            $table->string('bank_code', 32)->default('')->comment('银行编号');
            $table->char('reserved_phone', 11)->default('')->comment('预留手机号');
            $table->string('sms_biz_no', 32)->default('')->unique('sms_uniq')->comment('短信流水号');
            $table->string('sign_biz_no', 32)->default('')->comment('签约流水号');
            $table->string('extra', 1024)->default('')->comment('签约额外信息');
            $table->integer('status')->default(0)->comment('-3:认证请求失败,-2:已失效,-1:认证失败，0:初始化，1:认证中，2:认证成功');
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
        Schema::drop('user_bank_card');
    }

}
