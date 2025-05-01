<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveScoreAwardedFromGameTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('game_types', function (Blueprint $table) {
            $table->dropColumn('score_awarded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('game_types', function (Blueprint $table) {
            $table->integer('score_awarded')->nullable(); // or whatever original type it was
        });
    }
}
