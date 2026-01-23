<?php

use App\Models\Base;
use App\Models\Table;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Base::factory()->create(['user_id' => $this->user->id]);
});

describe('Table API', function () {

    it('can list tables in a base', function () {
        Table::factory()->count(3)->create(['base_id' => $this->base->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/bases/{$this->base->public_id}/tables")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'base_id', 'name', 'description', 'position', 'fields_count', 'rows_count'],
                ],
            ]);
    });

    it('can create a table', function () {
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/bases/{$this->base->public_id}/tables", [
                'name' => 'New Table',
                'description' => 'Table description',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Table');

        $this->assertDatabaseHas('tables', [
            'base_id' => $this->base->id,
            'name' => 'New Table',
        ]);

        // Should create default primary field
        $table = Table::where('name', 'New Table')->first();
        expect($table->fields)->toHaveCount(1);
        expect($table->fields->first()->name)->toBe('Name');
        expect($table->fields->first()->is_primary)->toBeTrue();
    });

    it('can show a table with fields and rows', function () {
        $table = Table::factory()->create(['base_id' => $this->base->id]);
        $table->fields()->create(['name' => 'Test Field', 'type' => 'text']);
        $table->rows()->create();

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/tables/{$table->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $table->public_id)
            ->assertJsonCount(1, 'data.fields')
            ->assertJsonCount(1, 'data.rows');
    });

    it('can update a table', function () {
        $table = Table::factory()->create(['base_id' => $this->base->id]);

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/tables/{$table->public_id}", [
                'name' => 'Updated Table Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Table Name');
    });

    it('can delete a table', function () {
        $table = Table::factory()->create(['base_id' => $this->base->id]);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/tables/{$table->public_id}")
            ->assertNoContent();

        $this->assertSoftDeleted('tables', ['id' => $table->id]);
    });

    it('cannot access tables from another user\'s base', function () {
        $otherUser = User::factory()->create();
        $otherBase = Base::factory()->create(['user_id' => $otherUser->id]);
        $table = Table::factory()->create(['base_id' => $otherBase->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/tables/{$table->public_id}")
            ->assertForbidden();
    });

});
