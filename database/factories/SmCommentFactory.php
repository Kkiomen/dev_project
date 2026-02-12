<?php

namespace Database\Factories;

use App\Models\SmComment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmCommentFactory extends Factory
{
    protected $model = SmComment::class;

    public function definition(): array
    {
        return [
            'brand_id' => 1,
            'platform' => fake()->randomElement(['instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'youtube']),
            'external_post_id' => fake()->uuid(),
            'external_comment_id' => fake()->uuid(),
            'social_post_id' => null,
            'author_handle' => fake()->userName(),
            'author_name' => fake()->name(),
            'author_avatar' => null,
            'text' => fake()->sentence(),
            'sentiment' => fake()->randomElement(['positive', 'neutral', 'negative']),
            'is_replied' => false,
            'reply_text' => null,
            'replied_at' => null,
            'is_hidden' => false,
            'is_flagged' => false,
            'posted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
