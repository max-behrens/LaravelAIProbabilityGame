<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameQuestion;
use App\Models\GameType;
use App\Models\GameTypeDifficulty;
use App\Models\GameTypeCategory;

class GameQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $gameType = GameType::where('name', 'Game of Lies')->firstOrFail();

        $difficulties = GameTypeDifficulty::get()->keyBy('name');
        $categories = GameTypeCategory::get()->keyBy('name');


        $scores = [
            'Easy' => 5,
            'Medium' => 10,
            'Hard' => 15
        ];

        $questions = [

            // === Category: Number ===
            // Easy
            ['difficulty' => 'Easy', 'category' => 'Number', 'question' => 'What is 8 + 7?', 'answer' => '15'],
            ['difficulty' => 'Easy', 'category' => 'Number', 'question' => 'What is 12 - 5?', 'answer' => '7'],
            ['difficulty' => 'Easy', 'category' => 'Number', 'question' => 'What is 6 × 4?', 'answer' => '24'],
            ['difficulty' => 'Easy', 'category' => 'Number', 'question' => 'What is 20 ÷ 4?', 'answer' => '5'],
            ['difficulty' => 'Easy', 'category' => 'Number', 'question' => 'What is 9 + 9?', 'answer' => '18'],

            // Medium
            ['difficulty' => 'Medium', 'category' => 'Number', 'question' => 'What is the square root of 169?', 'answer' => '13'],
            ['difficulty' => 'Medium', 'category' => 'Number', 'question' => 'What is 15% of 200?', 'answer' => '30'],
            ['difficulty' => 'Medium', 'category' => 'Number', 'question' => 'What is 7³ (7 cubed)?', 'answer' => '343'],
            ['difficulty' => 'Medium', 'category' => 'Number', 'question' => 'What is the next prime number after 17?', 'answer' => '19'],
            ['difficulty' => 'Medium', 'category' => 'Number', 'question' => 'What is 144 ÷ 12?', 'answer' => '12'],

            // Hard
            ['difficulty' => 'Hard', 'category' => 'Number', 'question' => 'What is the value of π (pi) to 3 decimal places?', 'answer' => '3.142'],
            ['difficulty' => 'Hard', 'category' => 'Number', 'question' => 'What is the 12th Fibonacci number?', 'answer' => '144'],
            ['difficulty' => 'Hard', 'category' => 'Number', 'question' => 'What is e (Euler\'s number) to 2 decimal places?', 'answer' => '2.72'],
            ['difficulty' => 'Hard', 'category' => 'Number', 'question' => 'What is the square root of 289?', 'answer' => '17'],
            ['difficulty' => 'Hard', 'category' => 'Number', 'question' => 'What is 2⁸ (2 to the power of 8)?', 'answer' => '256'],

            // === Category: History ===
            // Easy
            ['difficulty' => 'Easy', 'category' => 'History', 'question' => 'Who was the first President of the United States?', 'answer' => 'George Washington'],
            ['difficulty' => 'Easy', 'category' => 'History', 'question' => 'In which year did World War II end?', 'answer' => '1945'],
            ['difficulty' => 'Easy', 'category' => 'History', 'question' => 'Which ancient wonder was located in Alexandria?', 'answer' => 'Lighthouse'],
            ['difficulty' => 'Easy', 'category' => 'History', 'question' => 'Who painted the Mona Lisa?', 'answer' => 'Leonardo da Vinci'],
            ['difficulty' => 'Easy', 'category' => 'History', 'question' => 'In which year did the Berlin Wall fall?', 'answer' => '1989'],

            // Medium
            ['difficulty' => 'Medium', 'category' => 'History', 'question' => 'Which empire was ruled by Genghis Khan?', 'answer' => 'Mongol Empire'],
            ['difficulty' => 'Medium', 'category' => 'History', 'question' => 'In which year was the Declaration of Independence signed?', 'answer' => '1776'],
            ['difficulty' => 'Medium', 'category' => 'History', 'question' => 'Who was the last Pharaoh of Egypt?', 'answer' => 'Cleopatra VII'],
            ['difficulty' => 'Medium', 'category' => 'History', 'question' => 'Which battle marked the end of Napoleon\'s rule?', 'answer' => 'Waterloo'],
            ['difficulty' => 'Medium', 'category' => 'History', 'question' => 'What was the name of the ship that carried the Pilgrims to America?', 'answer' => 'Mayflower'],

            // Hard
            ['difficulty' => 'Hard', 'category' => 'History', 'question' => 'Which treaty ended the War of Spanish Succession?', 'answer' => 'Treaty of Utrecht'],
            ['difficulty' => 'Hard', 'category' => 'History', 'question' => 'Who was the Byzantine Emperor during the First Crusade?', 'answer' => 'Alexios I Komnenos'],
            ['difficulty' => 'Hard', 'category' => 'History', 'question' => 'In which year was the Battle of Hastings?', 'answer' => '1066'],
            ['difficulty' => 'Hard', 'category' => 'History', 'question' => 'Which ancient Greek historian is known as the "Father of History"?', 'answer' => 'Herodotus'],
            ['difficulty' => 'Hard', 'category' => 'History', 'question' => 'What was the capital of the Aztec Empire?', 'answer' => 'Tenochtitlan'],

            // === Category: Geography ===
            // Easy
            ['difficulty' => 'Easy', 'category' => 'Geography', 'question' => 'What is the capital of France?', 'answer' => 'Paris'],
            ['difficulty' => 'Easy', 'category' => 'Geography', 'question' => 'Which is the largest continent?', 'answer' => 'Asia'],
            ['difficulty' => 'Easy', 'category' => 'Geography', 'question' => 'What is the longest river in the world?', 'answer' => 'Nile'],
            ['difficulty' => 'Easy', 'category' => 'Geography', 'question' => 'Which mountain range contains Mount Everest?', 'answer' => 'Himalayas'],
            ['difficulty' => 'Easy', 'category' => 'Geography', 'question' => 'What is the smallest country in the world?', 'answer' => 'Vatican City'],

            // Medium
            ['difficulty' => 'Medium', 'category' => 'Geography', 'question' => 'Which river runs through Baghdad?', 'answer' => 'Tigris'],
            ['difficulty' => 'Medium', 'category' => 'Geography', 'question' => 'What is the capital of New Zealand?', 'answer' => 'Wellington'],
            ['difficulty' => 'Medium', 'category' => 'Geography', 'question' => 'Which desert is the largest in Africa?', 'answer' => 'Sahara'],
            ['difficulty' => 'Medium', 'category' => 'Geography', 'question' => 'In which country would you find Machu Picchu?', 'answer' => 'Peru'],
            ['difficulty' => 'Medium', 'category' => 'Geography', 'question' => 'What is the deepest ocean trench?', 'answer' => 'Mariana Trench'],

            // Hard
            ['difficulty' => 'Hard', 'category' => 'Geography', 'question' => 'What is the smallest country in South America?', 'answer' => 'Suriname'],
            ['difficulty' => 'Hard', 'category' => 'Geography', 'question' => 'Which strait separates Europe and Africa?', 'answer' => 'Strait of Gibraltar'],
            ['difficulty' => 'Hard', 'category' => 'Geography', 'question' => 'What is the highest capital city in the world?', 'answer' => 'La Rinconada'],
            ['difficulty' => 'Hard', 'category' => 'Geography', 'question' => 'Which African country has three capital cities?', 'answer' => 'South Africa'],
            ['difficulty' => 'Hard', 'category' => 'Geography', 'question' => 'What is the name of the supercontinent that existed 200 million years ago?', 'answer' => 'Pangaea'],
        ];

        foreach ($questions as $q) {
            $difficulty = $difficulties[$q['difficulty']] ?? null;
            $category = $categories[$q['category']] ?? null;

            if (!$difficulty || !$category) {
                continue; // skip if missing data
            }

            GameQuestion::updateOrCreate(
                [
                    'game_type_id' => $gameType->id,
                    'difficulty_id' => $difficulty->id,
                    'category_id' => $category->id,
                    'question' => $q['question']
                ],
                [
                    'answer' => $q['answer'],
                    'score_awarded' => $scores[$q['difficulty']]
                ]
            );
        }
    }
}
