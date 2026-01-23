<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    templateId: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close', 'added']);

const { t } = useI18n();

const loading = ref(false);
const category = ref('');
const existingCategories = ref([]);

const fetchCategories = async () => {
    try {
        const response = await axios.get('/api/v1/library/categories');
        existingCategories.value = response.data.categories;
    } catch (error) {
        console.error('Failed to fetch categories:', error);
    }
};

const addToLibrary = async () => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/v1/templates/${props.templateId}/add-to-library`, {
            category: category.value || null,
        });
        emit('added', response.data.data);
        emit('close');
    } catch (error) {
        console.error('Failed to add to library:', error);
        alert(error.response?.data?.message || 'Failed to add to library');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (props.show) {
        fetchCategories();
    }
});

import { watch } from 'vue';
watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchCategories();
        category.value = '';
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
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ t('graphics.library.addToLibrary') }}
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
                    <div class="p-6">
                        <div class="space-y-4">
                            <!-- Category input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('graphics.library.category') }}
                                    <span class="text-gray-400 font-normal">({{ t('common.optional') }})</span>
                                </label>
                                <input
                                    v-model="category"
                                    type="text"
                                    list="categories"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :placeholder="t('graphics.library.categoryPlaceholder')"
                                />
                                <datalist id="categories">
                                    <option v-for="cat in existingCategories" :key="cat" :value="cat" />
                                </datalist>
                            </div>

                            <!-- Existing categories -->
                            <div v-if="existingCategories.length > 0">
                                <p class="text-sm text-gray-500 mb-2">{{ t('graphics.library.selectCategory') }}:</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="cat in existingCategories"
                                        :key="cat"
                                        @click="category = cat"
                                        :class="[
                                            'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                            category === cat
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        ]"
                                    >
                                        {{ cat }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                        <Button variant="secondary" @click="$emit('close')">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button :loading="loading" @click="addToLibrary">
                            {{ t('graphics.library.addToLibrary') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
