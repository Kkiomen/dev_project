<?php

use App\Models\Template;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Template API', function () {

    it('requires authentication', function () {
        $this->getJson('/api/v1/templates')
            ->assertUnauthorized();
    });

    it('can list user templates', function () {
        $templates = Template::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Create another user's template (should not appear)
        Template::factory()->create();

        Sanctum::actingAs($this->user);
        $this
            ->getJson('/api/v1/templates')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'width', 'height', 'background_color', 'created_at', 'updated_at'],
                ],
            ]);
    });

    it('can create a template', function () {
        $data = [
            'name' => 'Test Template',
            'description' => 'Test description',
            'width' => 1080,
            'height' => 1080,
            'background_color' => '#FFFFFF',
        ];

        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/templates', $data)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Template')
            ->assertJsonPath('data.width', 1080)
            ->assertJsonPath('data.height', 1080);

        $this->assertDatabaseHas('templates', [
            'name' => 'Test Template',
            'user_id' => $this->user->id,
        ]);
    });

    it('validates template creation', function () {
        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/templates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        Sanctum::actingAs($this->user);
        $this
            ->postJson('/api/v1/templates', [
                'name' => 'Test',
                'width' => -100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['width']);
    });

    it('can show a template', function () {
        $template = Template::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->public_id)
            ->assertJsonPath('data.name', $template->name);
    });

    it('cannot show another user\'s template', function () {
        $otherUser = User::factory()->create();
        $template = Template::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);
        $this
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertForbidden();
    });

    it('can update a template', function () {
        $template = Template::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->putJson("/api/v1/templates/{$template->public_id}", [
                'name' => 'Updated Name',
                'width' => 1200,
                'height' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.width', 1200);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
        ]);
    });

    it('can delete a template', function () {
        $template = Template::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
        $this
            ->deleteJson("/api/v1/templates/{$template->public_id}")
            ->assertNoContent();

        $this->assertSoftDeleted('templates', ['id' => $template->id]);
    });

    it('can duplicate a template', function () {
        $template = Template::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Template',
        ]);

        Sanctum::actingAs($this->user);
        $response = $this
            ->postJson("/api/v1/templates/{$template->public_id}/duplicate")
            ->assertCreated();

        $this->assertDatabaseCount('templates', 2);

        $duplicated = Template::where('id', '!=', $template->id)->first();
        expect($duplicated->name)->toContain('Original Template');
        expect($duplicated->user_id)->toBe($this->user->id);
    });

});
