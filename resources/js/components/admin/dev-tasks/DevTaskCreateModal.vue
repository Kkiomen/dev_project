<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDevTasksStore } from '@/stores/devTasks';
import { useToast } from '@/composables/useToast';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const devTasksStore = useDevTasksStore();
const toast = useToast();

const saving = ref(false);
const showAdvanced = ref(false);
const showNewProject = ref(false);

const form = ref({
    project: '',
    title: '',
    pm_description: '',
    priority: 'medium',
    labels: [],
    estimated_hours: null,
});

const newProject = ref({
    prefix: '',
    name: '',
});

const newLabel = ref('');

watch(() => props.show, (val) => {
    if (val) {
        form.value = {
            project: devTasksStore.projects[0]?.prefix || '',
            title: '',
            pm_description: '',
            priority: 'medium',
            labels: [],
            estimated_hours: null,
        };
        showAdvanced.value = false;
        showNewProject.value = false;
        newProject.value = { prefix: '', name: '' };
        newLabel.value = '';
    }
});

const hasProjects = computed(() => devTasksStore.projects.length > 0);

const priorityConfig = {
    urgent: { icon: '⚡', color: '#DC2626', bg: 'bg-red-50', text: 'text-red-700', ring: 'ring-red-200' },
    high: { icon: '↑', color: '#F97316', bg: 'bg-orange-50', text: 'text-orange-700', ring: 'ring-orange-200' },
    medium: { icon: '→', color: '#3B82F6', bg: 'bg-blue-50', text: 'text-blue-700', ring: 'ring-blue-200' },
    low: { icon: '↓', color: '#6B7280', bg: 'bg-gray-50', text: 'text-gray-600', ring: 'ring-gray-200' },
};

const handleCreateProject = async () => {
    if (!newProject.value.prefix.trim() || !newProject.value.name.trim()) return;

    try {
        const project = await devTasksStore.createProject({
            prefix: newProject.value.prefix.toUpperCase().replace(/[^A-Z]/g, ''),
            name: newProject.value.name,
        });
        form.value.project = project.prefix;
        showNewProject.value = false;
        newProject.value = { prefix: '', name: '' };
        toast.success(t('devTasks.projectCreated'));
    } catch (error) {
        console.error('Failed to create project:', error);
        toast.error(error.response?.data?.message || t('devTasks.projectCreateError'));
    }
};

const handleSubmit = async () => {
    if (!form.value.project || !form.value.title.trim()) return;

    saving.value = true;
    try {
        const task = await devTasksStore.createTask({
            ...form.value,
            estimated_hours: form.value.estimated_hours || null,
        });
        emit('created', task);
    } catch (error) {
        console.error('Failed to create task:', error);
        toast.error(t('devTasks.createError'));
    } finally {
        saving.value = false;
    }
};

const addLabel = () => {
    const label = newLabel.value.trim();
    if (label && !form.value.labels.includes(label)) {
        form.value.labels.push(label);
    }
    newLabel.value = '';
};

const removeLabel = (index) => {
    form.value.labels.splice(index, 1);
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-1">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ t('devTasks.createTask') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('devTasks.createTaskSubtitle') }}</p>
                    </div>
                </div>
                <button
                    @click="$emit('close')"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-5">
                <!-- Project Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('devTasks.fields.project') }}
                        <span class="text-red-500">*</span>
                    </label>

                    <div v-if="showNewProject" class="p-4 bg-gray-50 rounded-xl space-y-3">
                        <div class="grid grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ t('devTasks.fields.prefix') }}</label>
                                <input
                                    v-model="newProject.prefix"
                                    type="text"
                                    class="block w-full rounded-lg border-gray-300 text-sm uppercase font-mono"
                                    maxlength="10"
                                    placeholder="AUTO"
                                />
                            </div>
                            <div class="col-span-3">
                                <label class="block text-xs text-gray-500 mb-1">{{ t('devTasks.fields.projectName') }}</label>
                                <input
                                    v-model="newProject.name"
                                    type="text"
                                    class="block w-full rounded-lg border-gray-300 text-sm"
                                    placeholder="Automation Panel"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button type="button" size="sm" @click="handleCreateProject" :disabled="!newProject.prefix || !newProject.name">
                                {{ t('devTasks.createProject') }}
                            </Button>
                            <Button type="button" size="sm" variant="ghost" @click="showNewProject = false">
                                {{ t('common.cancel') }}
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex gap-2">
                        <div class="relative flex-1">
                            <select
                                v-model="form.project"
                                class="block w-full rounded-lg border-gray-300 text-sm pr-10 appearance-none"
                                :required="hasProjects"
                            >
                                <option v-if="!hasProjects" value="" disabled>{{ t('devTasks.noProjects') }}</option>
                                <option v-for="project in devTasksStore.projects" :key="project.id" :value="project.prefix">
                                    {{ project.prefix }} - {{ project.name }}
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showNewProject = true"
                            class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border border-gray-300 hover:border-gray-400 rounded-lg transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('devTasks.newProject') }}
                        </button>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('devTasks.fields.title') }}
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        :placeholder="t('devTasks.fields.titlePlaceholder')"
                        class="block w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                        autofocus
                    />
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('devTasks.fields.priority') }}</label>
                    <div class="flex gap-2">
                        <button
                            v-for="(config, key) in priorityConfig"
                            :key="key"
                            type="button"
                            @click="form.priority = key"
                            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border-2 transition-all"
                            :class="form.priority === key
                                ? `${config.bg} ${config.text} border-current`
                                : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'"
                        >
                            <span>{{ config.icon }}</span>
                            <span class="hidden sm:inline">{{ t(`devTasks.priorities.${key}`) }}</span>
                        </button>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('devTasks.fields.pmDescription') }}
                        <span class="text-xs text-gray-400 font-normal ml-1">({{ t('common.optional') }})</span>
                    </label>
                    <textarea
                        v-model="form.pm_description"
                        rows="4"
                        :placeholder="t('devTasks.fields.pmDescriptionPlaceholder')"
                        class="block w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 resize-none"
                    />
                </div>

                <!-- Advanced options toggle -->
                <button
                    type="button"
                    @click="showAdvanced = !showAdvanced"
                    class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
                >
                    <svg
                        class="w-4 h-4 transition-transform"
                        :class="{ 'rotate-90': showAdvanced }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ t('devTasks.advancedOptions') }}
                </button>

                <!-- Advanced options -->
                <div v-show="showAdvanced" class="space-y-4 pl-6 border-l-2 border-gray-100">
                    <!-- Estimated hours -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('devTasks.fields.estimatedHours') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="form.estimated_hours"
                                type="number"
                                min="0"
                                class="w-24 rounded-lg border-gray-300 text-sm"
                                placeholder="0"
                            />
                            <span class="text-sm text-gray-500">{{ t('devTasks.hours') }}</span>
                        </div>
                    </div>

                    <!-- Labels -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('devTasks.fields.labels') }}</label>
                        <div v-if="form.labels.length" class="flex flex-wrap gap-1.5 mb-2">
                            <span
                                v-for="(label, index) in form.labels"
                                :key="index"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs font-medium group"
                            >
                                {{ label }}
                                <button type="button" @click="removeLabel(index)" class="hover:text-red-600 opacity-60 group-hover:opacity-100">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="newLabel"
                                type="text"
                                :placeholder="t('devTasks.addLabel')"
                                class="flex-1 rounded-lg border-gray-300 text-sm"
                                @keyup.enter.prevent="addLabel"
                            />
                            <Button type="button" size="sm" variant="secondary" @click="addLabel" :disabled="!newLabel.trim()">
                                {{ t('common.add') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button variant="secondary" @click="$emit('close')">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :loading="saving" :disabled="!form.project || !form.title.trim()">
                        {{ t('devTasks.createTask') }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
