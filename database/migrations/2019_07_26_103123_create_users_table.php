<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('用户id，内部使用');
            $table->bigInteger('old_user_id')->unique('old_user_id')->comment('新架构之前用户的id，风控使用');
            $table->char('uid', 64)->unique('uid_uniq')->comment('用户uid，外部使用');
            $table->char('phone', 11)->unique('phone_uniq')->comment('手机号');
            $table->string('name', 64)->default('')->comment('用户姓名');
            $table->char('identity', 18)->default('')->index('identity')->comment('用户身份证号码');
            $table->string('bank_code', 32)->default('')->comment('银行编码');
            $table->string('card_no', 64)->default('')->comment('银行卡号');
            $table->integer('type')->default(0)->comment('账户类型,0:未借款,1:已借款');
            $table->string('reg_channel', 32)->default('')->comment('注册渠道');
            $table->integer('authed_amount')->default(0)->comment('授权金额');
            $table->integer('frozen_status')->default(0)->comment('冻结状态-流程子状态');
            $table->dateTime('frozen_start_time')->nullable()->comment('冻结开始时间');
            $table->dateTime('frozen_end_time')->nullable()->comment('冻结结束时间');
            $table->dateTime('active_time')->nullable()->index('active_time')->comment('最近活跃时间');
            $table->integer('status')->default(1)->comment('账户状态, -1:失效,0:初始化,1:有效');
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
        Schema::drop('users');
    }

}
