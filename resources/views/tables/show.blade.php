<x-app-layout>
    <div class="h-[calc(100vh-64px)] flex flex-col" x-data="gridTable(@js($tableData), @js($fieldsData), @js($rowsData))">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <!-- Back to dashboard -->
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>

                <!-- Base/Table selector -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-500">{{ $table->base->name }}</span>
                    <span class="text-gray-300">/</span>
                    <div class="relative" x-data="{ open: false }">
                        <!-- Editable table name -->
                        <div x-show="!editingTableName" class="flex items-center">
                            <button @click="open = !open" class="flex items-center space-x-1 text-sm font-semibold text-gray-900 hover:text-gray-700">
                                <span @dblclick.stop="startEditTableName()">{{ $table->name }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                        <input x-show="editingTableName"
                               x-model="tableNameValue"
                               @keydown.enter="saveTableName()"
                               @keydown.escape="cancelEditTableName()"
                               @blur="saveTableName()"
                               x-ref="tableNameInput"
                               class="text-sm font-semibold text-gray-900 border border-blue-500 rounded px-2 py-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               x-cloak>

                        <!-- Table dropdown -->
                        <div x-show="open && !editingTableName" @click.away="open = false" x-cloak
                             class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                            @foreach($table->base->tables as $t)
                                <a href="{{ route('web.tables.show', $t) }}"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ $t->id === $table->id ? 'bg-blue-50 text-blue-700' : '' }}">
                                    {{ $t->name }}
                                </a>
                            @endforeach
                            <hr class="my-1">
                            <button @click="showAddTableModal = true; open = false"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                + Dodaj tabelę
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View switcher -->
            <div class="flex items-center space-x-2">
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <a href="{{ route('web.tables.show', $table) }}"
                       class="px-3 py-1 text-sm font-medium rounded-md bg-white shadow text-gray-900">
                        Grid
                    </a>
                    <a href="{{ route('web.tables.kanban', $table) }}"
                       class="px-3 py-1 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">
                        Kanban
                    </a>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-500" x-text="rows.length + ' rekordów'"></span>
                <!-- Search -->
                <div class="relative">
                    <input type="text" x-model="searchQuery" @input="filterRows()"
                           placeholder="Szukaj..."
                           class="text-sm border-gray-300 rounded-md pl-8 pr-3 py-1.5 focus:border-blue-500 focus:ring-blue-500">
                    <svg class="w-4 h-4 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button @click="addRow()"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Dodaj wiersz
                </button>
            </div>
        </div>

        <!-- Grid -->
        <div class="flex-1 overflow-auto bg-white">
            <table class="w-full border-collapse min-w-max">
                <!-- Header -->
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <!-- Row number column -->
                        <th class="w-12 min-w-[48px] px-2 py-2 text-xs font-medium text-gray-500 border-b border-r border-gray-200 bg-gray-50">
                            #
                        </th>

                        <!-- Field columns -->
                        <template x-for="field in fields" :key="field.id">
                            <th class="relative px-3 py-2 text-left text-xs font-medium text-gray-700 border-b border-r border-gray-200 bg-gray-50 group"
                                :style="{ width: field.width + 'px', minWidth: field.width + 'px' }">
                                <div class="flex items-center space-x-2 pr-6">
                                    <span class="text-gray-400" x-text="getFieldIcon(field.type)"></span>
                                    <span x-text="field.name" class="truncate"></span>
                                    <span x-show="field.is_primary" class="text-blue-500 text-xs">*</span>
                                </div>

                                <!-- Field menu button -->
                                <button @click.stop="openFieldMenu($event, field)"
                                        class="absolute right-1 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Resize handle -->
                                <div class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-blue-500 transition-colors"
                                     @mousedown.stop.prevent="startResize($event, field)"></div>
                            </th>
                        </template>

                        <!-- Add field column -->
                        <th class="w-10 min-w-[40px] px-2 py-2 border-b border-gray-200 bg-gray-50">
                            <button @click="openAddFieldModal()"
                                    class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </th>
                    </tr>
                </thead>

                <!-- Body -->
                <tbody>
                    <template x-for="(row, rowIndex) in filteredRows" :key="row.id">
                        <tr class="group hover:bg-blue-50/30"
                            :class="{ 'bg-blue-50/50': selectedRow === row.id }">
                            <!-- Row number -->
                            <td class="px-2 py-1 text-xs text-gray-400 border-r border-b border-gray-200 text-center bg-gray-50/50">
                                <div class="flex items-center justify-center space-x-1">
                                    <span x-text="rowIndex + 1" class="group-hover:hidden"></span>
                                    <div class="hidden group-hover:flex items-center space-x-1">
                                        <button @click="duplicateRow(row.id)" title="Duplikuj"
                                                class="text-gray-400 hover:text-blue-500">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        <button @click="deleteRow(row.id)" title="Usuń"
                                                class="text-gray-400 hover:text-red-500">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- Cells -->
                            <template x-for="field in fields" :key="field.id">
                                <td class="px-1 py-0.5 border-r border-b border-gray-200 relative cursor-pointer"
                                    :class="{
                                        'ring-2 ring-blue-500 ring-inset bg-blue-50/50': activeCell?.row === row.id && activeCell?.field === field.id
                                    }"
                                    :style="{ width: field.width + 'px', minWidth: field.width + 'px' }"
                                    @click="activateCell(row.id, field.id)"
                                    @dblclick="editCell(row.id, field.id)">

                                    <!-- Display mode -->
                                    <div x-show="!(editingCell?.row === row.id && editingCell?.field === field.id)"
                                         class="min-h-[32px] px-2 py-1">

                                        <!-- Text -->
                                        <template x-if="field.type === 'text' || field.type === 'url'">
                                            <span class="text-sm text-gray-900 truncate block" x-text="getCellValue(row.id, field.id) || ''"></span>
                                        </template>

                                        <!-- Number -->
                                        <template x-if="field.type === 'number'">
                                            <span class="text-sm text-gray-900 tabular-nums" x-text="formatNumber(getCellValue(row.id, field.id), field)"></span>
                                        </template>

                                        <!-- Date -->
                                        <template x-if="field.type === 'date'">
                                            <span class="text-sm text-gray-900" x-text="formatDate(getCellValue(row.id, field.id))"></span>
                                        </template>

                                        <!-- Checkbox -->
                                        <template x-if="field.type === 'checkbox'">
                                            <input type="checkbox"
                                                   :checked="getCellValue(row.id, field.id)"
                                                   @click.stop
                                                   @change="updateCell(row.id, field.id, $event.target.checked)"
                                                   class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                        </template>

                                        <!-- Select -->
                                        <template x-if="field.type === 'select'">
                                            <div x-show="getCellValue(row.id, field.id)">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                      :style="{ backgroundColor: getCellValue(row.id, field.id)?.color + '20', color: getCellValue(row.id, field.id)?.color }"
                                                      x-text="getCellValue(row.id, field.id)?.name">
                                                </span>
                                            </div>
                                        </template>

                                        <!-- Multi-select -->
                                        <template x-if="field.type === 'multi_select'">
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="choice in (getCellValue(row.id, field.id) || [])" :key="choice.id">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                          :style="{ backgroundColor: choice.color + '20', color: choice.color }"
                                                          x-text="choice.name">
                                                    </span>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- Attachment - Display mode with upload button -->
                                        <template x-if="field.type === 'attachment'">
                                            <div class="flex items-center space-x-1">
                                                <template x-for="(att, i) in (getCellValue(row.id, field.id) || []).slice(0, 3)" :key="att.id">
                                                    <div class="relative group/att w-7 h-7 rounded overflow-hidden bg-gray-100 flex-shrink-0">
                                                        <img x-show="att.is_image" :src="att.thumbnail_url || att.url" class="w-full h-full object-cover">
                                                        <div x-show="!att.is_image" class="w-full h-full flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                            </svg>
                                                        </div>
                                                        <!-- Delete button on hover -->
                                                        <button @click.stop="removeAttachment(row.id, field.id, att.id)"
                                                                class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover/att:opacity-100 transition-opacity">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                                <span x-show="(getCellValue(row.id, field.id) || []).length > 3"
                                                      class="text-xs text-gray-500"
                                                      x-text="'+' + ((getCellValue(row.id, field.id) || []).length - 3)">
                                                </span>
                                                <!-- Upload button -->
                                                <label @click.stop class="cursor-pointer w-7 h-7 flex items-center justify-center border border-dashed border-gray-300 rounded hover:border-blue-500 hover:bg-blue-50 transition-colors">
                                                    <input type="file" class="hidden" @change="uploadAttachment($event, row.id, field.id)"
                                                           accept="image/*,application/pdf" multiple>
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </label>
                                            </div>
                                        </template>

                                        <!-- JSON -->
                                        <template x-if="field.type === 'json'">
                                            <span class="text-sm text-gray-500 font-mono truncate block"
                                                  x-text="JSON.stringify(getCellValue(row.id, field.id)) || ''"></span>
                                        </template>
                                    </div>

                                    <!-- Edit mode -->
                                    <div x-show="editingCell?.row === row.id && editingCell?.field === field.id"
                                         class="min-h-[32px]">

                                        <!-- Text input -->
                                        <template x-if="field.type === 'text' || field.type === 'url'">
                                            <input type="text"
                                                   x-model="editingValue"
                                                   @keydown.enter="saveCell()"
                                                   @keydown.escape="cancelEdit()"
                                                   @keydown.tab="saveAndMoveNext($event)"
                                                   x-ref="cellInput"
                                                   class="w-full px-2 py-1 text-sm border-0 focus:ring-0 bg-transparent">
                                        </template>

                                        <!-- Number input -->
                                        <template x-if="field.type === 'number'">
                                            <input type="number"
                                                   x-model="editingValue"
                                                   @keydown.enter="saveCell()"
                                                   @keydown.escape="cancelEdit()"
                                                   @keydown.tab="saveAndMoveNext($event)"
                                                   x-ref="cellInput"
                                                   step="any"
                                                   class="w-full px-2 py-1 text-sm border-0 focus:ring-0 bg-transparent">
                                        </template>

                                        <!-- Date input -->
                                        <template x-if="field.type === 'date'">
                                            <input type="datetime-local"
                                                   x-model="editingValue"
                                                   @keydown.enter="saveCell()"
                                                   @keydown.escape="cancelEdit()"
                                                   x-ref="cellInput"
                                                   class="w-full px-2 py-1 text-sm border-0 focus:ring-0 bg-transparent">
                                        </template>

                                        <!-- Select dropdown -->
                                        <template x-if="field.type === 'select'">
                                            <select x-model="editingValue"
                                                    @change="saveCell()"
                                                    @keydown.escape="cancelEdit()"
                                                    x-ref="cellInput"
                                                    class="w-full px-2 py-1 text-sm border-0 focus:ring-0 bg-transparent">
                                                <option value="">-- Wybierz --</option>
                                                <template x-for="choice in (field.options?.choices || [])" :key="choice.id">
                                                    <option :value="choice.id" x-text="choice.name"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <!-- Multi-select (simplified) -->
                                        <template x-if="field.type === 'multi_select'">
                                            <div class="p-1">
                                                <template x-for="choice in (field.options?.choices || [])" :key="choice.id">
                                                    <label class="flex items-center space-x-2 px-2 py-1 hover:bg-gray-100 rounded cursor-pointer">
                                                        <input type="checkbox"
                                                               :checked="(editingValue || []).includes(choice.id)"
                                                               @change="toggleMultiSelectChoice(choice.id)"
                                                               class="w-3.5 h-3.5 text-blue-600 rounded border-gray-300">
                                                        <span class="text-sm" :style="{ color: choice.color }" x-text="choice.name"></span>
                                                    </label>
                                                </template>
                                                <button @click="saveCell()" class="mt-1 w-full px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                                                    Zapisz
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </template>

                            <!-- Empty cell for add column -->
                            <td class="border-b border-gray-200"></td>
                        </tr>
                    </template>

                    <!-- Add row placeholder -->
                    <tr class="hover:bg-gray-50 cursor-pointer" @click="addRow()">
                        <td class="px-2 py-2 text-xs text-gray-400 border-r border-gray-200 text-center">+</td>
                        <td :colspan="fields.length + 1" class="px-2 py-2 text-sm text-gray-400">
                            Kliknij aby dodać nowy wiersz...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Field Menu Dropdown -->
        <div x-show="fieldMenuOpen" x-cloak
             :style="{ top: fieldMenuPosition.y + 'px', left: fieldMenuPosition.x + 'px' }"
             @click.away="fieldMenuOpen = false"
             class="fixed z-50 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-1">
            <button @click="startRenameField()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <span class="w-5 text-center mr-2">✏️</span> Zmień nazwę
            </button>
            <button @click="openEditFieldModal()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <span class="w-5 text-center mr-2">🔄</span> Zmień typ
            </button>
            <template x-if="selectedField?.type === 'select' || selectedField?.type === 'multi_select'">
                <button @click="openFieldOptionsModal()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <span class="w-5 text-center mr-2">⚙️</span> Zarządzaj opcjami
                </button>
            </template>
            <hr class="my-1">
            <button @click="moveFieldLeft()" :disabled="isFirstField()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="w-5 text-center mr-2">⬅️</span> Przesuń w lewo
            </button>
            <button @click="moveFieldRight()" :disabled="isLastField()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="w-5 text-center mr-2">➡️</span> Przesuń w prawo
            </button>
            <hr class="my-1">
            <button @click="deleteField()" :disabled="selectedField?.is_primary"
                    class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="w-5 text-center mr-2">🗑️</span> Usuń pole
            </button>
        </div>

        <!-- Add/Edit Field Modal -->
        <div x-show="showFieldModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div @click="closeFieldModal()" class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6" x-text="editingFieldId ? 'Edytuj pole' : 'Dodaj nowe pole'"></h3>

                    <form @submit.prevent="saveField">
                        <!-- Field name -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nazwa pola</label>
                            <input type="text" x-model="fieldForm.name" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Np. Imię i nazwisko">
                        </div>

                        <!-- Field type selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Wybierz typ pola</label>
                            <div class="grid grid-cols-3 gap-3">
                                <template x-for="ft in fieldTypeDefinitions" :key="ft.value">
                                    <div @click="fieldForm.type = ft.value"
                                         :class="fieldForm.type === ft.value ? 'ring-2 ring-blue-500 border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                         class="relative border rounded-lg p-4 cursor-pointer transition-all">
                                        <div class="flex flex-col items-center text-center">
                                            <span class="text-2xl mb-2" x-text="ft.icon"></span>
                                            <span class="text-sm font-medium text-gray-900" x-text="ft.label"></span>
                                        </div>
                                        <!-- Info tooltip -->
                                        <div class="absolute top-2 right-2 group">
                                            <button type="button" @click.stop class="text-gray-400 hover:text-gray-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                            <div class="absolute right-0 top-6 w-48 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10">
                                                <p class="font-medium mb-1" x-text="ft.description"></p>
                                                <p class="text-gray-400">Przykład: <span x-text="ft.example"></span></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Select/Multi-select options -->
                        <div x-show="fieldForm.type === 'select' || fieldForm.type === 'multi_select'" class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Opcje wyboru</label>
                            <div class="space-y-2 mb-3">
                                <template x-for="(choice, index) in fieldForm.choices" :key="index">
                                    <div class="flex items-center space-x-2">
                                        <input type="color" x-model="choice.color" class="w-8 h-8 rounded border-0 cursor-pointer">
                                        <input type="text" x-model="choice.name" placeholder="Nazwa opcji"
                                               class="flex-1 rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <button type="button" @click="removeChoice(index)" class="text-gray-400 hover:text-red-500">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addChoice()" class="text-sm text-blue-600 hover:text-blue-700">
                                + Dodaj opcję
                            </button>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="closeFieldModal()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Anuluj
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                <span x-text="editingFieldId ? 'Zapisz zmiany' : 'Dodaj pole'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Field Options Modal (for select/multi_select) -->
        <div x-show="showFieldOptionsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div @click="showFieldOptionsModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Zarządzaj opcjami - <span x-text="selectedField?.name"></span></h3>

                    <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                        <template x-for="(choice, index) in selectedField?.options?.choices || []" :key="choice.id">
                            <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded-lg">
                                <input type="color" :value="choice.color" @change="updateChoiceColor(index, $event.target.value)"
                                       class="w-8 h-8 rounded border-0 cursor-pointer">
                                <input type="text" :value="choice.name" @blur="updateChoiceName(index, $event.target.value)"
                                       class="flex-1 rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <button @click="deleteChoice(index)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <button @click="addNewChoice()" class="w-full px-4 py-2 text-sm text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 mb-4">
                        + Dodaj nową opcję
                    </button>

                    <div class="flex justify-end">
                        <button @click="showFieldOptionsModal = false"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                            Zamknij
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Table Modal -->
        <div x-show="showAddTableModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div @click="showAddTableModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Dodaj tabelę</h3>
                    <form @submit.prevent="addTable">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nazwa tabeli</label>
                            <input type="text" x-model="newTableName" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showAddTableModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Anuluj
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                                Dodaj
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rename Field Inline -->
        <div x-show="renamingField" x-cloak
             :style="{ top: fieldMenuPosition.y + 'px', left: (fieldMenuPosition.x - 100) + 'px' }"
             class="fixed z-50 bg-white rounded-lg shadow-xl border border-gray-200 p-3">
            <input type="text" x-model="renameFieldValue" x-ref="renameFieldInput"
                   @keydown.enter="saveRenameField()"
                   @keydown.escape="renamingField = false"
                   @blur="saveRenameField()"
                   class="w-48 rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                   placeholder="Nazwa pola">
        </div>

        <!-- Upload progress indicator -->
        <div x-show="uploading" x-cloak
             class="fixed bottom-4 right-4 bg-white rounded-lg shadow-xl border border-gray-200 p-4 flex items-center space-x-3">
            <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm text-gray-700">Przesyłanie pliku...</span>
        </div>
    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Define gridTable as a global function for Alpine.js
        window.gridTable = function(initialTable, initialFields, initialRows) {
            return {
                table: initialTable,
                fields: initialFields,
                rows: initialRows,
                filteredRows: initialRows,
                cellValues: {},

                // Search
                searchQuery: '',

                // UI State
                selectedRow: null,
                activeCell: null,
                editingCell: null,
                editingValue: null,
                showAddTableModal: false,
                newTableName: '',

                // Table name editing
                editingTableName: false,
                tableNameValue: '',

                // Field menu
                fieldMenuOpen: false,
                fieldMenuPosition: { x: 0, y: 0 },
                selectedField: null,

                // Field rename
                renamingField: false,
                renameFieldValue: '',

                // Field modal
                showFieldModal: false,
                editingFieldId: null,
                fieldForm: {
                    name: '',
                    type: 'text',
                    choices: []
                },

                // Field options modal
                showFieldOptionsModal: false,

                // Upload state
                uploading: false,

                // Resize state
                resizing: false,
                resizeField: null,
                resizeStartX: 0,
                resizeStartWidth: 0,

                // Field type definitions
                fieldTypeDefinitions: [
                    { value: 'text', icon: 'Aa', label: 'Tekst', description: 'Jednoliniowy tekst', example: 'Jan Kowalski, ul. Długa 15' },
                    { value: 'number', icon: '#', label: 'Liczba', description: 'Liczby całkowite lub dziesiętne', example: '42, 3.14, -100' },
                    { value: 'date', icon: '📅', label: 'Data', description: 'Data i opcjonalnie godzina', example: '2024-01-15, 14:30' },
                    { value: 'checkbox', icon: '☑️', label: 'Checkbox', description: 'Tak/Nie, prawda/fałsz', example: '✓ lub puste' },
                    { value: 'select', icon: '▼', label: 'Wybór', description: 'Jedna opcja z listy', example: 'Status: Nowy, W trakcie' },
                    { value: 'multi_select', icon: '≡', label: 'Multi-wybór', description: 'Wiele opcji z listy', example: 'Tagi: Pilne, Ważne' },
                    { value: 'attachment', icon: '📎', label: 'Załącznik', description: 'Pliki, zdjęcia, dokumenty', example: 'foto.jpg, dokument.pdf' },
                    { value: 'url', icon: '🔗', label: 'URL', description: 'Link do strony', example: 'https://example.com' },
                    { value: 'json', icon: '{ }', label: 'JSON', description: 'Dane strukturalne', example: '{"klucz": "wartość"}' }
                ],

                init() {
                    // Build cell values cache
                    this.rows.forEach(row => {
                        this.cellValues[row.id] = row.values || {};
                    });

                    // Keyboard navigation
                    document.addEventListener('keydown', (e) => this.handleKeydown(e));

                    // Mouse up for resize
                    document.addEventListener('mouseup', () => this.stopResize());
                    document.addEventListener('mousemove', (e) => this.onResize(e));
                },

                // Search/Filter
                filterRows() {
                    if (!this.searchQuery.trim()) {
                        this.filteredRows = this.rows;
                        return;
                    }
                    const query = this.searchQuery.toLowerCase();
                    this.filteredRows = this.rows.filter(row => {
                        return Object.values(this.cellValues[row.id] || {}).some(value => {
                            if (value === null || value === undefined) return false;
                            if (typeof value === 'object') {
                                return JSON.stringify(value).toLowerCase().includes(query);
                            }
                            return String(value).toLowerCase().includes(query);
                        });
                    });
                },

                // Table name editing
                startEditTableName() {
                    this.tableNameValue = this.table.name;
                    this.editingTableName = true;
                    this.$nextTick(() => {
                        this.$refs.tableNameInput?.focus();
                        this.$refs.tableNameInput?.select();
                    });
                },

                async saveTableName() {
                    if (!this.editingTableName) return;
                    const newName = this.tableNameValue.trim();
                    if (newName && newName !== this.table.name) {
                        try {
                            await axios.put(`/api/v1/tables/${this.table.id}`, { name: newName });
                            this.table.name = newName;
                            // Update page title or refresh
                            document.querySelector('button span')?.textContent = newName;
                            location.reload(); // Simple refresh to update all references
                        } catch (error) {
                            console.error('Failed to update table name:', error);
                        }
                    }
                    this.editingTableName = false;
                },

                cancelEditTableName() {
                    this.editingTableName = false;
                },

                // Cell Operations
                getCellValue(rowId, fieldId) {
                    return this.cellValues[rowId]?.[fieldId] ?? null;
                },

                activateCell(rowId, fieldId) {
                    this.activeCell = { row: rowId, field: fieldId };
                    this.selectedRow = rowId;
                },

                editCell(rowId, fieldId) {
                    const field = this.fields.find(f => f.id === fieldId);
                    if (field.type === 'checkbox' || field.type === 'attachment') return;

                    this.editingCell = { row: rowId, field: fieldId };
                    const value = this.getCellValue(rowId, fieldId);

                    if (field.type === 'select') {
                        this.editingValue = value?.id || '';
                    } else if (field.type === 'multi_select') {
                        this.editingValue = (value || []).map(v => v.id || v);
                    } else if (field.type === 'date' && value) {
                        this.editingValue = value.replace(' ', 'T').substring(0, 16);
                    } else {
                        this.editingValue = value ?? '';
                    }

                    this.$nextTick(() => {
                        const input = this.$refs.cellInput;
                        if (input) {
                            input.focus();
                            if (input.select) input.select();
                        }
                    });
                },

                toggleMultiSelectChoice(choiceId) {
                    if (!Array.isArray(this.editingValue)) {
                        this.editingValue = [];
                    }
                    const index = this.editingValue.indexOf(choiceId);
                    if (index === -1) {
                        this.editingValue.push(choiceId);
                    } else {
                        this.editingValue.splice(index, 1);
                    }
                },

                async saveCell() {
                    if (!this.editingCell) return;

                    const { row: rowId, field: fieldId } = this.editingCell;
                    const field = this.fields.find(f => f.id === fieldId);
                    let value = this.editingValue;

                    if (value === '' || value === null) {
                        value = null;
                    } else if (field.type === 'number') {
                        value = parseFloat(value);
                    }

                    this.editingCell = null;
                    this.editingValue = null;

                    await this.updateCell(rowId, fieldId, value);
                },

                cancelEdit() {
                    this.editingCell = null;
                    this.editingValue = null;
                },

                saveAndMoveNext(event) {
                    event.preventDefault();
                    this.saveCell();
                    const fieldIndex = this.fields.findIndex(f => f.id === this.activeCell.field);
                    if (fieldIndex < this.fields.length - 1) {
                        this.editCell(this.activeCell.row, this.fields[fieldIndex + 1].id);
                    }
                },

                async updateCell(rowId, fieldId, value) {
                    if (!this.cellValues[rowId]) this.cellValues[rowId] = {};

                    const field = this.fields.find(f => f.id === fieldId);

                    // Optimistic update with proper formatting
                    if (field.type === 'select' && value) {
                        const choice = field.options?.choices?.find(c => c.id === value);
                        this.cellValues[rowId][fieldId] = choice || null;
                    } else if (field.type === 'multi_select' && Array.isArray(value)) {
                        const choices = value.map(id => field.options?.choices?.find(c => c.id === id)).filter(Boolean);
                        this.cellValues[rowId][fieldId] = choices;
                    } else {
                        this.cellValues[rowId][fieldId] = value;
                    }

                    try {
                        await axios.put(`/api/v1/rows/${rowId}/cells/${fieldId}`, { value });
                    } catch (error) {
                        console.error('Failed to update cell:', error);
                    }
                },

                // Attachment Operations
                async uploadAttachment(event, rowId, fieldId) {
                    const files = event.target.files;
                    if (!files.length) return;

                    this.uploading = true;

                    try {
                        // First, ensure cell exists by updating it
                        const cellResponse = await axios.put(`/api/v1/rows/${rowId}/cells/${fieldId}`, {
                            value: this.cellValues[rowId]?.[fieldId] || []
                        });
                        const cellId = cellResponse.data.data.id;

                        // Upload each file
                        for (const file of files) {
                            const formData = new FormData();
                            formData.append('file', file);

                            const response = await axios.post(
                                `/api/v1/cells/${cellId}/attachments`,
                                formData,
                                { headers: { 'Content-Type': 'multipart/form-data' } }
                            );

                            // Add to local state
                            if (!this.cellValues[rowId]) this.cellValues[rowId] = {};
                            if (!this.cellValues[rowId][fieldId]) this.cellValues[rowId][fieldId] = [];
                            this.cellValues[rowId][fieldId] = [...this.cellValues[rowId][fieldId], response.data.data];
                        }
                    } catch (error) {
                        console.error('Failed to upload attachment:', error);
                        alert('Nie udało się przesłać pliku: ' + (error.response?.data?.message || error.message));
                    } finally {
                        this.uploading = false;
                        event.target.value = ''; // Reset file input
                    }
                },

                async removeAttachment(rowId, fieldId, attachmentId) {
                    if (!confirm('Usunąć ten załącznik?')) return;

                    try {
                        await axios.delete(`/api/v1/attachments/${attachmentId}`);

                        // Remove from local state
                        if (this.cellValues[rowId]?.[fieldId]) {
                            this.cellValues[rowId][fieldId] = this.cellValues[rowId][fieldId].filter(a => a.id !== attachmentId);
                        }
                    } catch (error) {
                        console.error('Failed to remove attachment:', error);
                    }
                },

                // Row Operations
                async addRow() {
                    try {
                        const response = await axios.post(`/api/v1/tables/${this.table.id}/rows`, { values: {} });
                        const newRow = response.data.data;
                        this.rows.push({ id: newRow.id, values: {} });
                        this.cellValues[newRow.id] = {};
                        this.filterRows();

                        this.$nextTick(() => {
                            if (this.fields.length > 0) {
                                this.editCell(newRow.id, this.fields[0].id);
                            }
                        });
                    } catch (error) {
                        console.error('Failed to add row:', error);
                    }
                },

                async duplicateRow(rowId) {
                    try {
                        const values = { ...this.cellValues[rowId] };
                        // Convert formatted values back to raw for API
                        const rawValues = {};
                        for (const [fieldId, value] of Object.entries(values)) {
                            const field = this.fields.find(f => f.id === fieldId);
                            if (field?.type === 'select' && value?.id) {
                                rawValues[fieldId] = value.id;
                            } else if (field?.type === 'multi_select' && Array.isArray(value)) {
                                rawValues[fieldId] = value.map(v => v.id || v);
                            } else if (field?.type !== 'attachment') {
                                rawValues[fieldId] = value;
                            }
                        }

                        const response = await axios.post(`/api/v1/tables/${this.table.id}/rows`, { values: rawValues });
                        const newRow = response.data.data;
                        this.rows.push({ id: newRow.id, values: newRow.values });
                        this.cellValues[newRow.id] = newRow.values || {};
                        this.filterRows();
                    } catch (error) {
                        console.error('Failed to duplicate row:', error);
                    }
                },

                async deleteRow(rowId) {
                    if (!confirm('Czy na pewno chcesz usunąć ten wiersz?')) return;

                    try {
                        await axios.delete(`/api/v1/rows/${rowId}`);
                        this.rows = this.rows.filter(r => r.id !== rowId);
                        delete this.cellValues[rowId];
                        this.filterRows();

                        if (this.selectedRow === rowId) {
                            this.selectedRow = null;
                            this.activeCell = null;
                        }
                    } catch (error) {
                        console.error('Failed to delete row:', error);
                    }
                },

                // Field Operations
                openFieldMenu(event, field) {
                    this.selectedField = field;
                    this.fieldMenuPosition = { x: event.clientX, y: event.clientY };
                    this.fieldMenuOpen = true;
                },

                isFirstField() {
                    return this.fields.indexOf(this.selectedField) === 0;
                },

                isLastField() {
                    return this.fields.indexOf(this.selectedField) === this.fields.length - 1;
                },

                openAddFieldModal() {
                    this.editingFieldId = null;
                    this.fieldForm = { name: '', type: 'text', choices: [] };
                    this.showFieldModal = true;
                },

                openEditFieldModal() {
                    this.fieldMenuOpen = false;
                    this.editingFieldId = this.selectedField.id;
                    this.fieldForm = {
                        name: this.selectedField.name,
                        type: this.selectedField.type,
                        choices: [...(this.selectedField.options?.choices || [])]
                    };
                    this.showFieldModal = true;
                },

                closeFieldModal() {
                    this.showFieldModal = false;
                    this.editingFieldId = null;
                    this.fieldForm = { name: '', type: 'text', choices: [] };
                },

                addChoice() {
                    const colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4', '#3B82F6', '#8B5CF6', '#EC4899'];
                    this.fieldForm.choices.push({
                        id: 'new_' + Date.now(),
                        name: '',
                        color: colors[this.fieldForm.choices.length % colors.length]
                    });
                },

                removeChoice(index) {
                    this.fieldForm.choices.splice(index, 1);
                },

                async saveField() {
                    if (!this.fieldForm.name.trim()) return;

                    const payload = {
                        name: this.fieldForm.name,
                        type: this.fieldForm.type
                    };

                    if (this.fieldForm.type === 'select' || this.fieldForm.type === 'multi_select') {
                        payload.options = {
                            choices: this.fieldForm.choices.filter(c => c.name.trim()).map(c => ({
                                id: c.id.startsWith('new_') ? undefined : c.id,
                                name: c.name,
                                color: c.color
                            }))
                        };
                    }

                    try {
                        if (this.editingFieldId) {
                            // Update existing field
                            const response = await axios.put(`/api/v1/fields/${this.editingFieldId}`, payload);
                            const index = this.fields.findIndex(f => f.id === this.editingFieldId);
                            if (index !== -1) {
                                this.fields[index] = response.data.data;
                            }
                        } else {
                            // Create new field
                            const response = await axios.post(`/api/v1/tables/${this.table.id}/fields`, payload);
                            this.fields.push(response.data.data);
                        }
                        this.closeFieldModal();
                    } catch (error) {
                        console.error('Failed to save field:', error);
                        alert('Nie udało się zapisać pola: ' + (error.response?.data?.message || error.message));
                    }
                },

                startRenameField() {
                    this.fieldMenuOpen = false;
                    this.renameFieldValue = this.selectedField.name;
                    this.renamingField = true;
                    this.$nextTick(() => {
                        this.$refs.renameFieldInput?.focus();
                        this.$refs.renameFieldInput?.select();
                    });
                },

                async saveRenameField() {
                    if (!this.renamingField) return;
                    const newName = this.renameFieldValue.trim();
                    if (newName && newName !== this.selectedField.name) {
                        try {
                            await axios.put(`/api/v1/fields/${this.selectedField.id}`, { name: newName });
                            this.selectedField.name = newName;
                        } catch (error) {
                            console.error('Failed to rename field:', error);
                        }
                    }
                    this.renamingField = false;
                },

                async moveFieldLeft() {
                    const index = this.fields.indexOf(this.selectedField);
                    if (index <= 0) return;

                    try {
                        await axios.put(`/api/v1/fields/${this.selectedField.id}`, { position: index - 1 });
                        this.fields.splice(index, 1);
                        this.fields.splice(index - 1, 0, this.selectedField);
                    } catch (error) {
                        console.error('Failed to move field:', error);
                    }
                    this.fieldMenuOpen = false;
                },

                async moveFieldRight() {
                    const index = this.fields.indexOf(this.selectedField);
                    if (index >= this.fields.length - 1) return;

                    try {
                        await axios.put(`/api/v1/fields/${this.selectedField.id}`, { position: index + 1 });
                        this.fields.splice(index, 1);
                        this.fields.splice(index + 1, 0, this.selectedField);
                    } catch (error) {
                        console.error('Failed to move field:', error);
                    }
                    this.fieldMenuOpen = false;
                },

                async deleteField() {
                    if (this.selectedField.is_primary) {
                        alert('Nie można usunąć pola głównego');
                        return;
                    }
                    if (!confirm('Czy na pewno chcesz usunąć to pole? Wszystkie dane w tej kolumnie zostaną utracone.')) {
                        this.fieldMenuOpen = false;
                        return;
                    }

                    try {
                        await axios.delete(`/api/v1/fields/${this.selectedField.id}`);
                        this.fields = this.fields.filter(f => f.id !== this.selectedField.id);
                    } catch (error) {
                        console.error('Failed to delete field:', error);
                        alert(error.response?.data?.message || 'Nie można usunąć pola');
                    }
                    this.fieldMenuOpen = false;
                },

                // Field options modal
                openFieldOptionsModal() {
                    this.fieldMenuOpen = false;
                    this.showFieldOptionsModal = true;
                },

                async updateChoiceColor(index, color) {
                    const choice = this.selectedField.options.choices[index];
                    choice.color = color;
                    await this.saveFieldOptions();
                },

                async updateChoiceName(index, name) {
                    const choice = this.selectedField.options.choices[index];
                    if (choice.name !== name) {
                        choice.name = name;
                        await this.saveFieldOptions();
                    }
                },

                async deleteChoice(index) {
                    this.selectedField.options.choices.splice(index, 1);
                    await this.saveFieldOptions();
                },

                async addNewChoice() {
                    const colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4', '#3B82F6', '#8B5CF6', '#EC4899'];
                    const newChoice = {
                        name: 'Nowa opcja',
                        color: colors[this.selectedField.options.choices.length % colors.length]
                    };

                    try {
                        const response = await axios.post(`/api/v1/fields/${this.selectedField.id}/choices`, newChoice);
                        // Refresh field data
                        const fieldResponse = await axios.get(`/api/v1/fields/${this.selectedField.id}`);
                        const index = this.fields.findIndex(f => f.id === this.selectedField.id);
                        if (index !== -1) {
                            this.fields[index] = fieldResponse.data.data;
                            this.selectedField = this.fields[index];
                        }
                    } catch (error) {
                        console.error('Failed to add choice:', error);
                    }
                },

                async saveFieldOptions() {
                    try {
                        await axios.put(`/api/v1/fields/${this.selectedField.id}`, {
                            options: this.selectedField.options
                        });
                    } catch (error) {
                        console.error('Failed to save field options:', error);
                    }
                },

                // Table Operations
                async addTable() {
                    if (!this.newTableName.trim()) return;

                    try {
                        const response = await axios.post(`/api/v1/bases/${this.table.base_id}/tables`, {
                            name: this.newTableName
                        });
                        window.location.href = '/tables/' + response.data.data.id;
                    } catch (error) {
                        console.error('Failed to add table:', error);
                    }
                },

                // Column Resize
                startResize(event, field) {
                    this.resizing = true;
                    this.resizeField = field;
                    this.resizeStartX = event.clientX;
                    this.resizeStartWidth = field.width;
                },

                onResize(event) {
                    if (!this.resizing) return;

                    const diff = event.clientX - this.resizeStartX;
                    const newWidth = Math.max(80, this.resizeStartWidth + diff);

                    const field = this.fields.find(f => f.id === this.resizeField.id);
                    if (field) {
                        field.width = newWidth;
                    }
                },

                async stopResize() {
                    if (!this.resizing) return;

                    const field = this.fields.find(f => f.id === this.resizeField?.id);
                    if (field) {
                        try {
                            await axios.put(`/api/v1/fields/${field.id}`, { width: field.width });
                        } catch (error) {
                            console.error('Failed to save column width:', error);
                        }
                    }

                    this.resizing = false;
                    this.resizeField = null;
                },

                // Keyboard Navigation
                handleKeydown(event) {
                    if (!this.activeCell || this.editingCell || this.showFieldModal || this.showAddTableModal) return;

                    const { row: rowId, field: fieldId } = this.activeCell;
                    const rowIndex = this.filteredRows.findIndex(r => r.id === rowId);
                    const fieldIndex = this.fields.findIndex(f => f.id === fieldId);

                    switch (event.key) {
                        case 'ArrowUp':
                            event.preventDefault();
                            if (rowIndex > 0) {
                                this.activateCell(this.filteredRows[rowIndex - 1].id, fieldId);
                            }
                            break;
                        case 'ArrowDown':
                            event.preventDefault();
                            if (rowIndex < this.filteredRows.length - 1) {
                                this.activateCell(this.filteredRows[rowIndex + 1].id, fieldId);
                            }
                            break;
                        case 'ArrowLeft':
                            event.preventDefault();
                            if (fieldIndex > 0) {
                                this.activateCell(rowId, this.fields[fieldIndex - 1].id);
                            }
                            break;
                        case 'ArrowRight':
                            event.preventDefault();
                            if (fieldIndex < this.fields.length - 1) {
                                this.activateCell(rowId, this.fields[fieldIndex + 1].id);
                            }
                            break;
                        case 'Enter':
                            event.preventDefault();
                            this.editCell(rowId, fieldId);
                            break;
                        case 'Delete':
                        case 'Backspace':
                            event.preventDefault();
                            const field = this.fields.find(f => f.id === fieldId);
                            if (field.type !== 'attachment') {
                                this.updateCell(rowId, fieldId, null);
                            }
                            break;
                    }
                },

                // Formatting
                getFieldIcon(type) {
                    const def = this.fieldTypeDefinitions.find(ft => ft.value === type);
                    return def?.icon || '?';
                },

                formatNumber(value, field) {
                    if (value === null || value === undefined) return '';
                    const precision = field.options?.precision ?? 2;
                    return Number(value).toFixed(precision);
                },

                formatDate(value) {
                    if (!value) return '';
                    try {
                        return new Date(value).toLocaleDateString('pl-PL');
                    } catch {
                        return value;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
