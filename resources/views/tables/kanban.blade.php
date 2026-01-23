<x-app-layout>
    <div class="h-[calc(100vh-64px)] flex flex-col" x-data="kanbanBoard(@js($tableData), @js($fieldsData), @js($rowsData), @js($groupByField?->public_id))">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>

                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-500">{{ $table->base->name }}</span>
                    <span class="text-gray-300">/</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $table->name }}</span>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <!-- Group by selector -->
                @if($selectFields->isNotEmpty())
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">Grupuj wg:</span>
                    <select @change="changeGroupBy($event.target.value)"
                            class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                        @foreach($selectFields as $sf)
                            <option value="{{ $sf->public_id }}" {{ $groupByField?->id === $sf->id ? 'selected' : '' }}>
                                {{ $sf->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- View switcher -->
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <a href="{{ route('web.tables.show', $table) }}"
                       class="px-3 py-1 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">
                        Grid
                    </a>
                    <a href="{{ route('web.tables.kanban', $table) }}"
                       class="px-3 py-1 text-sm font-medium rounded-md bg-white shadow text-gray-900">
                        Kanban
                    </a>
                </div>
            </div>
        </div>

        @if(!$groupByField)
            <div class="flex-1 flex items-center justify-center bg-gray-50">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Brak pola do grupowania</h3>
                    <p class="mt-1 text-sm text-gray-500">Dodaj pole typu "Wybór" lub "Wybór wielokrotny" aby używać widoku Kanban.</p>
                    <div class="mt-6">
                        <a href="{{ route('web.tables.show', $table) }}"
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Wróć do widoku Grid
                        </a>
                    </div>
                </div>
            </div>
        @else
            <!-- Kanban Board -->
            <div class="flex-1 overflow-x-auto bg-gray-100 p-4">
                <div class="flex space-x-4 h-full min-w-max">
                    <!-- No status column -->
                    <div class="w-72 flex-shrink-0 flex flex-col bg-gray-200 rounded-lg">
                        <div class="px-3 py-2 font-medium text-gray-700 border-b border-gray-300">
                            <span>Bez statusu</span>
                            <span class="ml-2 text-sm text-gray-500" x-text="getColumnCards(null).length"></span>
                        </div>
                        <div class="flex-1 overflow-y-auto p-2 space-y-2"
                             x-ref="columnNull"
                             data-column-id="">
                            <template x-for="row in getColumnCards(null)" :key="row.id">
                                <div class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md transition-shadow"
                                     :data-row-id="row.id"
                                     @click="openCard(row)">
                                    <div class="font-medium text-gray-900 truncate" x-text="getPrimaryValue(row)"></div>
                                    <div class="mt-2 text-sm text-gray-500 line-clamp-2" x-text="getSecondaryValue(row)"></div>
                                </div>
                            </template>

                            <button @click="addCard(null)"
                                    class="w-full py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded">
                                + Dodaj kartę
                            </button>
                        </div>
                    </div>

                    <!-- Status columns -->
                    @foreach($groupByField->getChoices() as $choice)
                        <div class="w-72 flex-shrink-0 flex flex-col bg-gray-200 rounded-lg">
                            <div class="px-3 py-2 font-medium border-b border-gray-300 flex items-center space-x-2">
                                <span class="w-3 h-3 rounded-full" style="background-color: {{ $choice['color'] }}"></span>
                                <span>{{ $choice['name'] }}</span>
                                <span class="ml-auto text-sm text-gray-500" x-text="getColumnCards('{{ $choice['id'] }}').length"></span>
                            </div>
                            <div class="flex-1 overflow-y-auto p-2 space-y-2"
                                 x-ref="column{{ $choice['id'] }}"
                                 data-column-id="{{ $choice['id'] }}">
                                <template x-for="row in getColumnCards('{{ $choice['id'] }}')" :key="row.id">
                                    <div class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md transition-shadow"
                                         :data-row-id="row.id"
                                         draggable="true"
                                         @dragstart="onDragStart($event, row)"
                                         @click="openCard(row)">
                                        <div class="font-medium text-gray-900 truncate" x-text="getPrimaryValue(row)"></div>
                                        <div class="mt-2 text-sm text-gray-500 line-clamp-2" x-text="getSecondaryValue(row)"></div>
                                    </div>
                                </template>

                                <button @click="addCard('{{ $choice['id'] }}')"
                                        class="w-full py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded">
                                    + Dodaj kartę
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Card Detail Modal -->
            <div x-show="showCardModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-start justify-center min-h-screen pt-20 px-4">
                    <div @click="showCardModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Szczegóły rekordu</h3>
                            <button @click="showCardModal = false" class="text-gray-400 hover:text-gray-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                            <template x-for="field in fields" :key="field.id">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" x-text="field.name"></label>
                                    <div class="mt-1">
                                        <!-- Text/URL input -->
                                        <template x-if="field.type === 'text' || field.type === 'url'">
                                            <input type="text"
                                                   :value="selectedCard?.values[field.id] || ''"
                                                   @change="updateCardField(field.id, $event.target.value)"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </template>

                                        <!-- Number input -->
                                        <template x-if="field.type === 'number'">
                                            <input type="number"
                                                   :value="selectedCard?.values[field.id] || ''"
                                                   @change="updateCardField(field.id, parseFloat($event.target.value))"
                                                   step="any"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </template>

                                        <!-- Date input -->
                                        <template x-if="field.type === 'date'">
                                            <input type="datetime-local"
                                                   :value="(selectedCard?.values[field.id] || '').replace(' ', 'T').substring(0, 16)"
                                                   @change="updateCardField(field.id, $event.target.value)"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </template>

                                        <!-- Checkbox -->
                                        <template x-if="field.type === 'checkbox'">
                                            <input type="checkbox"
                                                   :checked="selectedCard?.values[field.id]"
                                                   @change="updateCardField(field.id, $event.target.checked)"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        </template>

                                        <!-- Select -->
                                        <template x-if="field.type === 'select'">
                                            <select @change="updateCardField(field.id, $event.target.value || null)"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                <option value="">-- Wybierz --</option>
                                                <template x-for="choice in (field.options?.choices || [])" :key="choice.id">
                                                    <option :value="choice.id"
                                                            :selected="selectedCard?.values[field.id] === choice.id"
                                                            x-text="choice.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
                            <button @click="deleteCard()"
                                    class="text-red-600 hover:text-red-700 text-sm font-medium">
                                Usuń rekord
                            </button>
                            <button @click="showCardModal = false"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                Zamknij
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Define kanbanBoard as a global function for Alpine.js
        window.kanbanBoard = function(table, fields, rows, groupByFieldId) {
            return {
                table: table,
                fields: fields,
                rows: rows,
                groupByFieldId: groupByFieldId,
                showCardModal: false,
                selectedCard: null,
                draggedRow: null,

                init() {
                    // Setup drop zones
                    this.$nextTick(() => {
                        document.querySelectorAll('[data-column-id]').forEach(column => {
                            column.addEventListener('dragover', (e) => {
                                e.preventDefault();
                                column.classList.add('bg-blue-100');
                            });
                            column.addEventListener('dragleave', () => {
                                column.classList.remove('bg-blue-100');
                            });
                            column.addEventListener('drop', (e) => {
                                e.preventDefault();
                                column.classList.remove('bg-blue-100');
                                const columnId = column.dataset.columnId || null;
                                this.onDrop(columnId);
                            });
                        });
                    });
                },

                getGroupByField() {
                    return this.fields.find(f => f.id === this.groupByFieldId);
                },

                getPrimaryField() {
                    return this.fields.find(f => f.is_primary) || this.fields[0];
                },

                getColumnCards(choiceId) {
                    return this.rows.filter(row => {
                        const value = row.values[this.groupByFieldId];
                        if (choiceId === null || choiceId === '') {
                            return !value;
                        }
                        return value === choiceId;
                    });
                },

                getPrimaryValue(row) {
                    const primaryField = this.getPrimaryField();
                    return row.values[primaryField?.id] || 'Bez nazwy';
                },

                getSecondaryValue(row) {
                    // Get first non-primary text field value
                    const primaryField = this.getPrimaryField();
                    const secondaryField = this.fields.find(f =>
                        f.id !== primaryField?.id &&
                        f.id !== this.groupByFieldId &&
                        (f.type === 'text' || f.type === 'number')
                    );
                    return secondaryField ? row.values[secondaryField.id] : '';
                },

                openCard(row) {
                    this.selectedCard = row;
                    this.showCardModal = true;
                },

                async addCard(choiceId) {
                    const values = {};
                    if (choiceId && this.groupByFieldId) {
                        values[this.groupByFieldId] = choiceId;
                    }

                    try {
                        const response = await axios.post(`/api/v1/tables/${this.table.id}/rows`, { values });
                        const newRow = {
                            id: response.data.data.id,
                            values: values
                        };
                        this.rows.push(newRow);
                        this.openCard(newRow);
                    } catch (error) {
                        console.error('Failed to add card:', error);
                    }
                },

                async updateCardField(fieldId, value) {
                    if (!this.selectedCard) return;

                    // Update local state
                    this.selectedCard.values[fieldId] = value;

                    // Update in rows array
                    const row = this.rows.find(r => r.id === this.selectedCard.id);
                    if (row) {
                        row.values[fieldId] = value;
                    }

                    // API call
                    try {
                        await axios.put(`/api/v1/rows/${this.selectedCard.id}/cells/${fieldId}`, { value });
                    } catch (error) {
                        console.error('Failed to update field:', error);
                    }
                },

                async deleteCard() {
                    if (!this.selectedCard) return;
                    if (!confirm('Czy na pewno chcesz usunąć ten rekord?')) return;

                    try {
                        await axios.delete(`/api/v1/rows/${this.selectedCard.id}`);
                        this.rows = this.rows.filter(r => r.id !== this.selectedCard.id);
                        this.showCardModal = false;
                        this.selectedCard = null;
                    } catch (error) {
                        console.error('Failed to delete card:', error);
                    }
                },

                onDragStart(event, row) {
                    this.draggedRow = row;
                    event.dataTransfer.effectAllowed = 'move';
                },

                async onDrop(columnId) {
                    if (!this.draggedRow || !this.groupByFieldId) return;

                    const value = columnId || null;

                    // Update local state
                    this.draggedRow.values[this.groupByFieldId] = value;

                    // API call
                    try {
                        await axios.put(`/api/v1/rows/${this.draggedRow.id}/cells/${this.groupByFieldId}`, { value });
                    } catch (error) {
                        console.error('Failed to move card:', error);
                    }

                    this.draggedRow = null;
                },

                changeGroupBy(fieldId) {
                    window.location.href = `{{ route('web.tables.kanban', $table) }}?group_by=${fieldId}`;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
