<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    currentTemplateId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close', 'template-copied', 'applied-to-current']);

const { t } = useI18n();
const authStore = useAuthStore();

const loading = ref(false);
const templates = ref([]);
const copying = ref(null);
const applying = ref(null);
const deleting = ref(null);

const fetchLibrary = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/v1/library/templates');
        templates.value = response.data.data;
    } catch (error) {
        console.error('Failed to fetch library:', error);
    } finally {
        loading.value = false;
    }
};

// Apply template to current canvas (replace layers)
const applyToCurrent = async (template) => {
    if (!props.currentTemplateId) return;

    applying.value = template.id;
    try {
        const response = await axios.post(`/api/v1/library/templates/${template.id}/apply`, {
            target_template_id: props.currentTemplateId,
        });
        emit('applied-to-current', response.data.data);
    } catch (error) {
        console.error('Failed to apply template:', error);
        alert(error.response?.data?.message || 'Failed to apply template');
    } finally {
        applying.value = null;
    }
};

// Copy as new template
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

// Remove template from library (not permanent delete)
const removeFromLibrary = async (template) => {
    if (!confirm(t('graphics.library.confirmRemove'))) {
        return;
    }

    deleting.value = template.id;
    try {
        await axios.post(`/api/v1/templates/${template.id}/remove-from-library`);
        templates.value = templates.value.filter(t => t.id !== template.id);
    } catch (error) {
        console.error('Failed to remove from library:', error);
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

                <!-- Modal - wider for 5 columns -->
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[85vh] flex flex-col">
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
                        <div v-else-if="templates.length === 0" class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <p class="text-gray-500 font-medium">{{ t('graphics.library.noTemplates') }}</p>
                            <p class="text-gray-400 text-sm mt-1">{{ t('graphics.library.noTemplatesDescription') }}</p>
                        </div>

                        <!-- Templates grid - 5 columns, smaller cards -->
                        <div v-else class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 gap-3">
                            <div
                                v-for="template in templates"
                                :key="template.id"
                                class="group bg-white border border-gray-200 rounded-md overflow-hidden hover:shadow-md transition-shadow"
                            >
                                <!-- Thumbnail -->
                                <div class="aspect-[4/3] bg-gray-100 relative">
                                    <img
                                        v-if="template.thumbnail_url"
                                        :src="template.thumbnail_url"
                                        :alt="template.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>

                                    <!-- Overlay with actions -->
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 p-2">
                                        <!-- Apply to current (main action when in editor) -->
                                        <button
                                            v-if="currentTemplateId"
                                            :disabled="applying === template.id"
                                            @click="applyToCurrent(template)"
                                            class="w-full px-2 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition-colors disabled:opacity-50"
                                        >
                                            <span v-if="applying === template.id">...</span>
                                            <span v-else>{{ t('graphics.library.useHere') }}</span>
                                        </button>

                                        <!-- Copy as new template -->
                                        <button
                                            :disabled="copying === template.id"
                                            @click="copyTemplate(template)"
                                            class="w-full px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition-colors disabled:opacity-50"
                                        >
                                            <span v-if="copying === template.id">...</span>
                                            <span v-else>{{ t('graphics.library.createNew') }}</span>
                                        </button>

                                        <!-- Admin remove from library button -->
                                        <button
                                            v-if="authStore.isAdmin"
                                            @click="removeFromLibrary(template)"
                                            :disabled="deleting === template.id"
                                            class="absolute top-1 right-1 p-1 bg-amber-600 hover:bg-amber-700 text-white rounded transition-colors disabled:opacity-50"
                                            :title="t('graphics.library.removeFromLibrary')"
                                        >
                                            <svg v-if="deleting === template.id" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Info - minimal -->
                                <div class="px-1.5 py-1">
                                    <h3 class="font-medium text-gray-900 text-xs truncate">{{ template.name }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
