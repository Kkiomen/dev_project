<?php

use App\Enums\LayerType;
use App\Models\Layer;
use App\Models\Template;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->template = Template::factory()->create(['user_id' => $this->user->id]);
});

describe('Layer API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/templates/{$this->template->public_id}/layers")
            ->assertUnauthorized();
    });

    it('can list layers in a template', function () {
        $layers = Layer::factory()->count(3)->create(['template_id' => $this->template->id]);

        Sanctum::actingAs($this->user);
        $this
            ->getJson("/api/v1/templates/{$this->template->public_id}/layers")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'type', 'position', 'visible', 'locked', 'x', 'y', 'width', 'height', 'properties'],
                ],
            ]);
    });

    it('can create a text layer', function () {
        $data = [
            'name' => 'My Text',
            'type' => 'text',
            'x' => 100,
            'y' => 100,
            'properties' => [
                'text' => 'Hello World',
                'fontSize' => 32,
                'fill' => '#FF0000',
            ],
        ];

        Sanctum::actingAs($this->user);
        $this
            ->postJson("/api/v1/templates/{$this->template->public_id}/layers", $data)
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Text')
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.properties.text', 'Hello World');

        $this->assertDatabaseHas('layers', [
            'name' => 'My Text',
            'template_id' => $this->template->id,
        ]);
    });

    it('can create a rectangle layer', function () {
        $data = [
            'name' => 'My Rectangle',
            'type' => 'rectangle',
            'x' => 50,
            'y' => 50,
            'width' => 200,
            'height' => 100,
            'properties' => [
                'fill' => '#00FF00',
                'cornerRadius' => 10,
            ],
        ];

        Sanctum::actingAs($this->user);
        $this
            ->postJson("/api/v1/templates/{$this->template->public_id}/layers", $data)
            ->assertCreated()
            ->assertJsonPath('data.type', 'rectangle')
            ->assertJsonPath('data.properties.fill', '#00FF00');
    });

    it('validates layer creation', function () {
        Sanctum::actingAs($this->user);
        $this
            ->postJson("/api/v1/templates/{$this->template->public_id}/layers", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type']);

        Sanctum::actingAs($this->user);
        $this
            ->postJson("/api/v1/templates/{$this->template->public_id}/layers", [
                'name' => 'Test',
                'type' => 'invalid_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('can update a layer', function () {
        $layer = Layer::factory()->text()->create(['template_id' => $this->template->id]);

        Sanctum::actingAs($this->user);
        $this
            ->putJson("/api/v1/layers/{$layer->public_id}", [
                'name' => 'Updated Text',
                'x' => 200,
                'y' => 300,
                'properties' => [
                    'text' => 'Updated content',
                    'fontSize' => 48,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Text')
            ->assertJsonPath('data.x', 200);

        $this->assertDatabaseHas('layers', [
            'id' => $layer->id,
            'name' => 'Updated Text',
        ]);
    });

    it('can delete a layer', function () {
        $layer = Layer::factory()->create(['template_id' => $this->template->id]);

        Sanctum::actingAs($this->user);
        $this
            ->deleteJson("/api/v1/layers/{$layer->public_id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('layers', ['id' => $layer->id]);
    });

    it('can reorder a layer', function () {
        $layer1 = Layer::factory()->create(['template_id' => $this->template->id, 'position' => 0]);
        $layer2 = Layer::factory()->create(['template_id' => $this->template->id, 'position' => 1]);
        $layer3 = Layer::factory()->create(['template_id' => $this->template->id, 'position' => 2]);

        Sanctum::actingAs($this->user);
        $this
            ->postJson("/api/v1/layers/{$layer3->public_id}/reorder", [
                'position' => 0,
            ])
            ->assertOk();

        $layer1->refresh();
        $layer2->refresh();
        $layer3->refresh();

        expect($layer3->position)->toBe(0);
        expect($layer1->position)->toBe(1);
        expect($layer2->position)->toBe(2);
    });

    it('can bulk update layers', function () {
        $layer1 = Layer::factory()->create(['template_id' => $this->template->id, 'name' => 'Layer 1']);
        $layer2 = Layer::factory()->create(['template_id' => $this->template->id, 'name' => 'Layer 2']);

        Sanctum::actingAs($this->user);
        $this
            ->putJson("/api/v1/templates/{$this->template->public_id}/layers", [
                'layers' => [
                    ['id' => $layer1->public_id, 'name' => 'Updated Layer 1', 'x' => 100],
                    ['id' => $layer2->public_id, 'name' => 'Updated Layer 2', 'y' => 200],
                ],
            ])
            ->assertOk();

        $layer1->refresh();
        $layer2->refresh();

        expect($layer1->name)->toBe('Updated Layer 1');
        expect($layer1->x)->toBe(100.0);
        expect($layer2->name)->toBe('Updated Layer 2');
        expect($layer2->y)->toBe(200.0);
    });

    it('cannot access another user\'s layers', function () {
        $otherUser = User::factory()->create();
        $otherTemplate = Template::factory()->create(['user_id' => $otherUser->id]);
        $layer = Layer::factory()->create(['template_id' => $otherTemplate->id]);

        Sanctum::actingAs($this->user);
        $this
            ->putJson("/api/v1/layers/{$layer->public_id}", ['name' => 'Hacked'])
            ->assertForbidden();
    });

});
