<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserRelationshipTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_relationship', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->bigInteger('user_id')->default(0)->index('user_id')->comment('用户id');
            $table->string('name', 64)->default('')->comment('姓名');
            $table->string('phone', 32)->default('')->comment('电话');
            $table->integer('type')->default(0)->comment('关系类型，0:直系亲属,1:紧急联系人');
            $table->string('relation', 32)->default('0')->comment('关系，如父母,配偶,朋友,同事');
            $table->integer('status')->default(0)->comment('-3:认证请求失败,-2:已失效,-1:认证失败，0:初始化，1:认证中，2:认证成功');
            $table->timestamps();
            $table->index(['phone', 'status'], 'phone_status');
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
        Schema::drop('user_relationship');
    }

}
