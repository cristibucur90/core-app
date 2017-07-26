<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssociationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('association', function (Blueprint $table) {
            $table->increments('id');
            $table->string('source');
            $table->string('provider');
            $table->string('type');
            $table->string('isNoTip');
            $table->string('isVip');
            $table->string('country');
            $table->string('league');
            $table->string('homeTeam');
            $table->string('awayTeam');
            $table->string('odd');
            $table->string('predictionId');
            $table->string('result');
            $table->string('statusId', 2);
            $table->timestamp('eventDate')->nullable();
            $table->timestamp('systemDate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('association');
    }
}