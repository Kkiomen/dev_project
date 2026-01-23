<x-app-layout>
    <div class="h-[calc(100vh-64px)] flex flex-col" x-data="baseView(@js($base->public_id))">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-lg font-semibold text-gray-900">{{ $base->name }}</h1>
            </div>
        </div>

        <!-- Empty state -->
        <div class="flex-1 flex items-center justify-center bg-gray-50">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Brak tabel</h3>
                <p class="mt-1 text-sm text-gray-500">Zacznij od utworzenia pierwszej tabeli.</p>
                <div class="mt-6">
                    <button @click="showCreateModal = true"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nowa tabela
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Table Modal -->
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div @click="showCreateModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Nowa tabela</h3>
                    <form @submit.prevent="createTable">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nazwa tabeli</label>
                                <input type="text" x-model="newTableName" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                       placeholder="Moja tabela">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showCreateModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Anuluj
                            </button>
                            <button type="submit"
                                    :disabled="creating"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!creating">Utwórz</span>
                                <span x-show="creating">Tworzenie...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Define baseView as a global function for Alpine.js
        window.baseView = function(baseId) {
            return {
                baseId: baseId,
                showCreateModal: false,
                creating: false,
                newTableName: '',

                async createTable() {
                    if (!this.newTableName.trim()) return;

                    this.creating = true;
                    try {
                        const response = await axios.post(`/api/v1/bases/${this.baseId}/tables`, {
                            name: this.newTableName
                        });
                        window.location.href = '/tables/' + response.data.data.id;
                    } catch (error) {
                        console.error('Error creating table:', error);
                        alert('Wystąpił błąd podczas tworzenia tabeli');
                    } finally {
                        this.creating = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
