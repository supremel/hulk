<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserBaseInfoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_base_info', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('profile id');
            $table->bigInteger('user_id')->index('user_id')->comment('用户id');
            $table->string('education', 16)->default('')->comment('最高学历');
            $table->string('industry', 16)->default('‘’')->comment('从事行业');
            $table->string('company_name', 128)->default('')->comment('单位名称');
            $table->string('month_income', 16)->default('')->comment('月收入');
            $table->string('addr', 128)->default('')->comment('常用地址');
            $table->string('email', 128)->default('')->comment('电子邮箱');
            $table->integer('province')->comment('省编码');
            $table->integer('city')->comment('市编码');
            $table->integer('county')->comment('区县编码');
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
        Schema::drop('user_base_info');
    }

}
