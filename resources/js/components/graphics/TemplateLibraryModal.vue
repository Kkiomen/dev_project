<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'template-copied']);

const { t } = useI18n();
const authStore = useAuthStore();

const loading = ref(false);
const templates = ref([]);
const categories = ref([]);
const selectedCategory = ref(null);
const copying = ref(null);
const deleting = ref(null);

const filteredTemplates = computed(() => {
    if (!selectedCategory.value) {
        return templates.value;
    }
    return templates.value.filter(t => t.library_category === selectedCategory.value);
});

const fetchLibrary = async () => {
    loading.value = true;
    try {
        const [templatesRes, categoriesRes] = await Promise.all([
            axios.get('/api/v1/library/templates'),
            axios.get('/api/v1/library/categories'),
        ]);
        templates.value = templatesRes.data.data;
        categories.value = categoriesRes.data.categories;
    } catch (error) {
        console.error('Failed to fetch library:', error);
    } finally {
        loading.value = false;
    }
};

const copyTemplate = async (template) => {
    copying.value = template.id;
    try {
        const response = await axios.post(`/api/v1/library/templates/${template.id}/copy`);
        emit('template-copied', response.data.data);
    } catch (error) {
        console.error('Failed to copy template:', error);
    } finally {
        copying.value = null;
    }
};

const deleteTemplate = async (template) => {
    if (!confirm(t('graphics.library.confirmDelete'))) {
        return;
    }

    deleting.value = template.id;
    try {
        await axios.delete(`/api/v1/library/templates/${template.id}`);
        templates.value = templates.value.filter(t => t.id !== template.id);
    } catch (error) {
        console.error('Failed to delete template:', error);
    } finally {
        deleting.value = null;
    }
};

onMounted(() => {
    if (props.show) {
        fetchLibrary();
    }
});

// Watch for show changes
import { watch } from 'vue';
watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchLibrary();
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="$emit('close')"
        >
            <div class="flex min-h-full items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ t('graphics.library.title') }}
                        </h2>
                        <button
                            @click="$emit('close')"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Category filter -->
                    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button
                                @click="selectedCategory = null"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                    selectedCategory === null
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                                ]"
                            >
                                {{ t('graphics.library.allCategories') }}
                            </button>
                            <button
                                v-for="category in categories"
                                :key="category"
                                @click="selectedCategory = category"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                    selectedCategory === category
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                                ]"
                            >
                                {{ category }}
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <!-- Loading -->
                        <div v-if="loading" class="flex items-center justify-center py-12">
                            <svg class="w-8 h-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="filteredTemplates.length === 0" class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <p class="text-gray-500 font-medium">{{ t('graphics.library.noTemplates') }}</p>
                            <p class="text-gray-400 text-sm mt-1">{{ t('graphics.library.noTemplatesDescription') }}</p>
                        </div>

                        <!-- Templates grid -->
                        <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div
                                v-for="template in filteredTemplates"
                                :key="template.id"
                                class="group bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow"
                            >
                                <!-- Thumbnail -->
                                <div class="aspect-square bg-gray-100 relative">
                                    <img
                                        v-if="template.thumbnail_url"
                                        :src="template.thumbnail_url"
                                        :alt="template.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>

                                    <!-- Overlay with actions -->
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                        <Button
                                            size="sm"
                                            :loading="copying === template.id"
                                            @click="copyTemplate(template)"
                                        >
                                            {{ t('graphics.library.useTemplate') }}
                                        </Button>

                                        <!-- Admin delete button -->
                                        <button
                                            v-if="authStore.isAdmin"
                                            @click="deleteTemplate(template)"
                                            :disabled="deleting === template.id"
                                            class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50"
                                            :title="t('common.delete')"
                                        >
                                            <svg v-if="deleting === template.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Info -->
                                <div class="p-3">
                                    <h3 class="font-medium text-gray-900 truncate">{{ template.name }}</h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-500">{{ template.width }}×{{ template.height }}</span>
                                        <span v-if="template.library_category" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                            {{ template.library_category }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
