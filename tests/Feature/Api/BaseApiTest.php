<?php

use App\Models\Base;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Base API', function () {

    it('requires authentication', function () {
        $this->getJson('/api/v1/bases')
            ->assertUnauthorized();
    });

    it('can list user bases', function () {
        $bases = Base::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Create another user's base (should not appear)
        Base::factory()->create();

        Sanctum::actingAs($this->user);
        $this
            ->getJson('/api/v1/bases')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'color', 'icon', 'tables_count', 'created_at', 'updated_at'],
                ],
            ]);
    });

    it('can create a base', function () {
        $data = [
            'name' => 'Test Base',
            'description' => 'Test description',
            'color' => '#FF5733',
        ];

        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/bases', $data)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Base')
            ->assertJsonPath('data.description', 'Test description')
            ->assertJsonPath('data.color', '#FF5733');

        $this->assertDatabaseHas('bases', [
            'name' => 'Test Base',
            'user_id' => $this->user->id,
        ]);

        // Should create default table with primary field
        $base = Base::where('name', 'Test Base')->first();
        expect($base->tables)->toHaveCount(1);
        expect($base->tables->first()->fields)->toHaveCount(1);
        expect($base->tables->first()->fields->first()->is_primary)->toBeTrue();
    });

    it('validates base creation', function () {
        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/bases', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/bases', ['name' => 'Test', 'color' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    });

    it('can show a base', function () {
        $base = Base::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->getJson("/api/v1/bases/{$base->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $base->public_id)
            ->assertJsonPath('data.name', $base->name);
    });

    it('cannot show another user\'s base', function () {
        $otherUser = User::factory()->create();
        $base = Base::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);
        $this
            ->getJson("/api/v1/bases/{$base->public_id}")
            ->assertForbidden();
    });

    it('can update a base', function () {
        $base = Base::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->putJson("/api/v1/bases/{$base->public_id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('bases', [
            'id' => $base->id,
            'name' => 'Updated Name',
        ]);
    });

    it('can delete a base', function () {
        $base = Base::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->deleteJson("/api/v1/bases/{$base->public_id}")
            ->assertNoContent();

        $this->assertSoftDeleted('bases', ['id' => $base->id]);
    });

});
