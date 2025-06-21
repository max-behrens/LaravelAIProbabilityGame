<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameType;

class GameQuestionFactory extends Factory
{
    protected $questions = [
        'number' => [
            'easy' => [
                'question' => 'What is 7 + 5?',
                'answer' => '12',
                'score' => 5,
            ],
            'medium' => [
                'question' => 'What is the square root of 144?',
                'answer' => '12',
                'score' => 10,
            ],
            'hard' => [
                'question' => 'What is the value of Pi (to 2 decimal places)?',
                'answer' => '3.14',
                'score' => 15,
            ],
        ],
        'history' => [
            'easy' => [
                'question' => 'Who was the first President of the United States?',
                'answer' => 'George Washington',
                'score' => 5,
            ],
            'medium' => [
                'question' => 'In which year did World War II end?',
                'answer' => '1945',
                'score' => 10,
            ],
            'hard' => [
                'question' => 'Which empire was ruled by Genghis Khan?',
                'answer' => 'Mongol Empire',
                'score' => 15,
            ],
        ],
        'geography' => [
            'easy' => [
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
                'score' => 5,
            ],
            'medium' => [
                'question' => 'Which river runs through Baghdad?',
                'answer' => 'Tigris',
                'score' => 10,
            ],
            'hard' => [
                'question' => 'What is the smallest country in South America?',
                'answer' => 'Suriname',
                'score' => 15,
            ],
        ],
    ];

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(),
            'answer' => $this->faker->word(),
            'game_type_id' => GameType::factory(),
            'score_awarded' => 5,
        ];
    }

    public function forGameTypeAndDifficulty(string $gameType, string $difficulty)
    {
        $q = $this->questions[$gameType][$difficulty];

        return $this->state([
            'question' => $q['question'],
            'answer' => $q['answer'],
            'score_awarded' => $q['score'],
        ]);
    }
}
