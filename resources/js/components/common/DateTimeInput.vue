<script setup>
import { ref, computed, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'datetime', // 'datetime', 'date', 'time'
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const settingsStore = useSettingsStore();

const is24Hour = computed(() => settingsStore.settings.timeFormat === '24h');

// Parse the modelValue into date and time parts
const datePart = ref('');
const hourPart = ref('12');
const minutePart = ref('00');
const periodPart = ref('AM');

// Parse input value
const parseValue = (value) => {
    if (!value) {
        datePart.value = '';
        hourPart.value = is24Hour.value ? '09' : '09';
        minutePart.value = '00';
        periodPart.value = 'AM';
        return;
    }

    // Handle both "YYYY-MM-DDTHH:MM" and "YYYY-MM-DD HH:MM" formats
    const parts = value.replace('T', ' ').split(' ');
    datePart.value = parts[0] || '';

    if (parts[1]) {
        const timeParts = parts[1].split(':');
        let hours = parseInt(timeParts[0], 10);
        minutePart.value = timeParts[1]?.substring(0, 2) || '00';

        if (is24Hour.value) {
            hourPart.value = hours.toString().padStart(2, '0');
        } else {
            // Convert to 12-hour format
            if (hours === 0) {
                hourPart.value = '12';
                periodPart.value = 'AM';
            } else if (hours === 12) {
                hourPart.value = '12';
                periodPart.value = 'PM';
            } else if (hours > 12) {
                hourPart.value = (hours - 12).toString().padStart(2, '0');
                periodPart.value = 'PM';
            } else {
                hourPart.value = hours.toString().padStart(2, '0');
                periodPart.value = 'AM';
            }
        }
    }
};

// Build output value
const buildValue = () => {
    if (props.type === 'date') {
        return datePart.value;
    }

    if (!datePart.value && props.type === 'datetime') {
        return '';
    }

    let hours = parseInt(hourPart.value, 10);

    if (!is24Hour.value) {
        // Convert from 12-hour to 24-hour
        if (periodPart.value === 'AM') {
            if (hours === 12) hours = 0;
        } else {
            if (hours !== 12) hours += 12;
        }
    }

    const timeStr = `${hours.toString().padStart(2, '0')}:${minutePart.value}`;

    if (props.type === 'time') {
        return timeStr;
    }

    return `${datePart.value}T${timeStr}`;
};

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
    parseValue(newValue);
}, { immediate: true });

// Watch for format changes
watch(() => settingsStore.settings.timeFormat, () => {
    parseValue(props.modelValue);
});

// Emit changes
const emitChange = () => {
    const value = buildValue();
    emit('update:modelValue', value);
};

// Generate hour options
const hourOptions = computed(() => {
    if (is24Hour.value) {
        return Array.from({ length: 24 }, (_, i) => i.toString().padStart(2, '0'));
    }
    return Array.from({ length: 12 }, (_, i) => (i + 1).toString().padStart(2, '0'));
});

// Generate minute options (every 5 minutes)
const minuteOptions = computed(() => {
    return Array.from({ length: 12 }, (_, i) => (i * 5).toString().padStart(2, '0'));
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Date input -->
        <input
            v-if="type !== 'time'"
            v-model="datePart"
            type="date"
            :required="required"
            :disabled="disabled"
            class="flex-1 min-w-[140px] px-3 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
            @change="emitChange"
        />

        <!-- Time inputs -->
        <div
            v-if="type !== 'date'"
            class="flex items-center gap-1"
        >
            <!-- Hour select -->
            <select
                v-model="hourPart"
                :disabled="disabled"
                class="pl-3 pr-8 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed appearance-none bg-white bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2020%2020%22%20fill%3d%22%236b7280%22%3e%3cpath%20fill-rule%3d%22evenodd%22%20d%3d%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20clip-rule%3d%22evenodd%22%2f%3e%3c%2fsvg%3e')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat"
                @change="emitChange"
            >
                <option v-for="hour in hourOptions" :key="hour" :value="hour">
                    {{ hour }}
                </option>
            </select>

            <span class="text-gray-500 font-medium">:</span>

            <!-- Minute select -->
            <select
                v-model="minutePart"
                :disabled="disabled"
                class="pl-3 pr-8 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed appearance-none bg-white bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2020%2020%22%20fill%3d%22%236b7280%22%3e%3cpath%20fill-rule%3d%22evenodd%22%20d%3d%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20clip-rule%3d%22evenodd%22%2f%3e%3c%2fsvg%3e')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat"
                @change="emitChange"
            >
                <option v-for="minute in minuteOptions" :key="minute" :value="minute">
                    {{ minute }}
                </option>
            </select>

            <!-- AM/PM select (only for 12h format) -->
            <select
                v-if="!is24Hour"
                v-model="periodPart"
                :disabled="disabled"
                class="pl-3 pr-8 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed appearance-none bg-white bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2020%2020%22%20fill%3d%22%236b7280%22%3e%3cpath%20fill-rule%3d%22evenodd%22%20d%3d%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20clip-rule%3d%22evenodd%22%2f%3e%3c%2fsvg%3e')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat"
                @change="emitChange"
            >
                <option value="AM">AM</option>
                <option value="PM">PM</option>
            </select>
        </div>
    </div>
</template>
