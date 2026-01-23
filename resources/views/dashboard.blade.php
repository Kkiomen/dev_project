<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Moje bazy danych
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="dashboard()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Create new base button -->
            <div class="mb-6">
                <button @click="showCreateModal = true"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nowa baza danych
                </button>
            </div>

            <!-- Bases grid -->
            @if($bases->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Brak baz danych</h3>
                        <p class="mt-1 text-sm text-gray-500">Zacznij od utworzenia nowej bazy danych.</p>
                        <div class="mt-6">
                            <button @click="showCreateModal = true"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Nowa baza danych
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($bases as $base)
                        <a href="{{ route('web.bases.show', $base) }}"
                           class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center"
                                         style="background-color: {{ $base->color }}20">
                                        <svg class="w-6 h-6" style="color: {{ $base->color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-medium text-gray-900">{{ $base->name }}</h3>
                                        <p class="text-sm text-gray-500">{{ $base->tables_count }} {{ trans_choice('tabela|tabele|tabel', $base->tables_count) }}</p>
                                    </div>
                                </div>
                                @if($base->description)
                                    <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $base->description }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Create Base Modal -->
        <div x-show="showCreateModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-title"
             role="dialog"
             aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showCreateModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="showCreateModal = false"
                     class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="showCreateModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <form @submit.prevent="createBase">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Nowa baza danych
                            </h3>
                            <div class="mt-4">
                                <label for="name" class="block text-sm font-medium text-gray-700">Nazwa</label>
                                <input type="text"
                                       x-model="newBase.name"
                                       id="name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                       placeholder="Moja baza danych"
                                       required>
                            </div>
                            <div class="mt-4">
                                <label for="description" class="block text-sm font-medium text-gray-700">Opis (opcjonalnie)</label>
                                <textarea x-model="newBase.description"
                                          id="description"
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                          placeholder="Opis bazy danych..."></textarea>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700">Kolor</label>
                                <div class="mt-2 flex space-x-2">
                                    <template x-for="color in colors" :key="color">
                                        <button type="button"
                                                @click="newBase.color = color"
                                                class="w-8 h-8 rounded-full border-2 transition-all"
                                                :class="newBase.color === color ? 'border-gray-800 scale-110' : 'border-transparent'"
                                                :style="{ backgroundColor: color }">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button type="submit"
                                    :disabled="creating"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm disabled:opacity-50">
                                <span x-show="!creating">Utwórz</span>
                                <span x-show="creating">Tworzenie...</span>
                            </button>
                            <button type="button"
                                    @click="showCreateModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                                Anuluj
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function dashboard() {
            return {
                showCreateModal: false,
                creating: false,
                colors: ['#3B82F6', '#EF4444', '#F97316', '#EAB308', '#22C55E', '#14B8A6', '#8B5CF6', '#EC4899'],
                newBase: {
                    name: '',
                    description: '',
                    color: '#3B82F6',
                },

                async createBase() {
                    if (!this.newBase.name.trim()) return;

                    this.creating = true;
                    try {
                        const response = await axios.post('/api/v1/bases', this.newBase);
                        window.location.href = '/bases/' + response.data.data.id;
                    } catch (error) {
                        console.error('Error creating base:', error);
                        alert('Wystąpił błąd podczas tworzenia bazy danych');
                    } finally {
                        this.creating = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
