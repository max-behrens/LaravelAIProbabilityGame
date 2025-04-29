<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('game_type_id')->constrained('game_types')->onDelete('cascade');
        });
    }
    
    public function down()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['game_type_id']);
            $table->dropColumn('game_type_id');
        });
    }
    
};
