<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateContractsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->integer('relation_type')->comment('合同关联类型, 0:订单类 , 1:用户类');
            $table->bigInteger('relation_id')->comment('关联id');
            $table->string('title', 128)->comment('合同标题');
            $table->string('contract_sn', 128)->unique('contract_uniq')->comment('协议编号');
            $table->integer('contract_type')->comment('合同类型');
            $table->string('original_pdf', 512)->default('')->comment('协议PDF版地址');
            $table->integer('is_upload')->default(0)->comment('合同是否上传');
            $table->string('sign_pdf', 512)->default('')->comment('签章协议PDF版地址');
            $table->string('h5_view_url', 512)->default('')->comment('协议html版地址');
            $table->integer('status')->comment('状态，0：初始化，1：成功，-1：失败');
            $table->timestamps();
            $table->index(['relation_id', 'relation_type'], 'relation_index');
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
        Schema::drop('contracts');
    }

}
