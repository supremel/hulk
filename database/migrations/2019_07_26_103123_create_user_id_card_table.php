<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserIdCardTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_id_card', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('profile id');
            $table->bigInteger('user_id')->index('user_id')->comment('用户id');
            $table->string('front_id', 128)->comment('身份证正面照路径');
            $table->string('back_id', 128)->comment('身份证反面照路径');
            $table->string('name', 64)->default('')->comment('姓名');
            $table->char('identity', 18)->default('')->index('identity_normal')->comment('身份证号码');
            $table->integer('age')->default(0)->comment('年龄');
            $table->integer('gender')->default(0)->comment('性别,0:男，1:女');
            $table->string('ethnicity', 32)->default('')->comment('民族');
            $table->string('birthday', 32)->default('')->comment('出生日期');
            $table->string('addr')->default('')->comment('地址');
            $table->string('issued_by', 64)->default('')->comment('颁发机关');
            $table->string('start_time', 32)->default('')->comment('有效期开始时间');
            $table->string('end_time', 32)->default('')->comment('有效期结束时间');
            $table->text('extra', 65535)->nullable()->comment('附加信息，json格式，包含ocr精度等信息');
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
        Schema::drop('user_id_card');
    }

}
