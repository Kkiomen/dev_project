<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/common/Modal.vue';
import Button from '@/components/common/Button.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    event: {
        type: Object,
        default: null,
    },
    initialDate: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close', 'save', 'delete']);

const { t } = useI18n();

const defaultColors = [
    '#3B82F6', // blue
    '#10B981', // green
    '#F59E0B', // amber
    '#EF4444', // red
    '#8B5CF6', // violet
    '#EC4899', // pink
    '#6366F1', // indigo
    '#14B8A6', // teal
];

const eventTypes = [
    { value: 'meeting', label: 'calendarEvents.eventTypes.meeting' },
    { value: 'birthday', label: 'calendarEvents.eventTypes.birthday' },
    { value: 'reminder', label: 'calendarEvents.eventTypes.reminder' },
    { value: 'other', label: 'calendarEvents.eventTypes.other' },
];

const form = ref({
    title: '',
    description: '',
    color: '#3B82F6',
    event_type: 'meeting',
    starts_at: '',
    ends_at: '',
    all_day: false,
});

const isEdit = computed(() => !!props.event);

const modalTitle = computed(() => {
    return isEdit.value ? t('calendarEvents.editEvent') : t('calendarEvents.addEvent');
});

// Initialize form when modal opens
watch(() => props.show, (value) => {
    if (value) {
        if (props.event) {
            // Editing existing event
            form.value = {
                title: props.event.title || '',
                description: props.event.description || '',
                color: props.event.color || '#3B82F6',
                event_type: props.event.event_type || 'meeting',
                starts_at: formatDateTimeLocal(props.event.starts_at),
                ends_at: props.event.ends_at ? formatDateTimeLocal(props.event.ends_at) : '',
                all_day: props.event.all_day || false,
            };
        } else {
            // Creating new event
            const now = new Date();
            let defaultDate = props.initialDate || now.toISOString().split('T')[0];

            form.value = {
                title: '',
                description: '',
                color: '#3B82F6',
                event_type: 'meeting',
                starts_at: `${defaultDate}T09:00`,
                ends_at: `${defaultDate}T10:00`,
                all_day: false,
            };
        }
    }
});

const formatDateTimeLocal = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const handleSubmit = () => {
    const data = {
        ...form.value,
        starts_at: form.value.all_day
            ? new Date(form.value.starts_at.split('T')[0] + 'T00:00:00').toISOString()
            : new Date(form.value.starts_at).toISOString(),
        ends_at: form.value.ends_at && !form.value.all_day
            ? new Date(form.value.ends_at).toISOString()
            : null,
    };

    emit('save', data);
};

const handleDelete = () => {
    emit('delete', props.event.id);
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ modalTitle }}
                </h2>
                <button
                    @click="close"
                    class="text-gray-400 hover:text-gray-500"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('calendarEvents.fields.title') }} *
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        :placeholder="t('calendarEvents.fields.title')"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('calendarEvents.fields.description') }}
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        :placeholder="t('calendarEvents.fields.description')"
                    />
                </div>

                <!-- Event Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('calendarEvents.fields.eventType') }}
                    </label>
                    <select
                        v-model="form.event_type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option v-for="type in eventTypes" :key="type.value" :value="type.value">
                            {{ t(type.label) }}
                        </option>
                    </select>
                </div>

                <!-- Color -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('calendarEvents.fields.color') }}
                    </label>
                    <div class="flex items-center space-x-2">
                        <button
                            v-for="color in defaultColors"
                            :key="color"
                            type="button"
                            class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                            :class="form.color === color ? 'border-gray-800 scale-110' : 'border-transparent'"
                            :style="{ backgroundColor: color }"
                            @click="form.color = color"
                        />
                        <input
                            v-model="form.color"
                            type="color"
                            class="w-8 h-8 rounded cursor-pointer border border-gray-300"
                        />
                    </div>
                </div>

                <!-- All Day Toggle -->
                <div class="flex items-center space-x-3">
                    <input
                        v-model="form.all_day"
                        type="checkbox"
                        id="all_day"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <label for="all_day" class="text-sm font-medium text-gray-700">
                        {{ t('calendarEvents.fields.allDay') }}
                    </label>
                </div>

                <!-- Date/Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('calendarEvents.fields.startsAt') }} *
                        </label>
                        <input
                            v-model="form.starts_at"
                            :type="form.all_day ? 'date' : 'datetime-local'"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <div v-if="!form.all_day">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('calendarEvents.fields.endsAt') }}
                        </label>
                        <input
                            v-model="form.ends_at"
                            type="datetime-local"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div>
                        <Button
                            v-if="isEdit"
                            type="button"
                            variant="danger"
                            @click="handleDelete"
                        >
                            {{ t('calendarEvents.deleteEvent') }}
                        </Button>
                    </div>
                    <div class="flex items-center space-x-3">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="close"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>
