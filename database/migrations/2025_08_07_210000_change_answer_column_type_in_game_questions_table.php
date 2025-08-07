<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeAnswerColumnTypeInGameQuestionsTable extends Migration
{
    public function up()
    {
        // For MySQL
        DB::statement('ALTER TABLE game_questions MODIFY COLUMN answer VARCHAR(255)');
        
        // For PostgreSQL, use:
        // DB::statement('ALTER TABLE game_questions ALTER COLUMN answer TYPE VARCHAR(255) USING answer::VARCHAR');
        
        // For SQLite, you would need to recreate the table (more complex)
    }

    public function down()
    {
        // For MySQL
        DB::statement('ALTER TABLE game_questions MODIFY COLUMN answer INT');
        
        // For PostgreSQL, use:
        // DB::statement('ALTER TABLE game_questions ALTER COLUMN answer TYPE INTEGER USING answer::INTEGER');
    }
}