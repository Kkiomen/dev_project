<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    card: { type: Object, required: true },
    columnId: { type: String, required: true },
    index: { type: Number, required: true },
});

const emit = defineEmits(['click', 'drop-at']);

const { t } = useI18n();
const dragOver = ref(false);
const isDragging = ref(false);

// Drag source
const handleDragStart = (e) => {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/card-id', props.card.id);
    e.dataTransfer.setData('text/column-id', props.columnId);
    isDragging.value = true;
};

const handleDragEnd = () => {
    isDragging.value = false;
};

// Drop target (between cards)
const handleCardDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    dragOver.value = true;
};

const handleCardDragLeave = () => {
    dragOver.value = false;
};

const handleCardDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragOver.value = false;

    const cardId = e.dataTransfer.getData('text/card-id');
    const fromColumnId = e.dataTransfer.getData('text/column-id');

    if (!cardId || cardId === props.card.id) return;

    emit('drop-at', cardId, fromColumnId);
};

const labelColors = {
    red: 'bg-red-100 text-red-700 ring-red-200/50',
    green: 'bg-emerald-100 text-emerald-700 ring-emerald-200/50',
    blue: 'bg-blue-100 text-blue-700 ring-blue-200/50',
    yellow: 'bg-amber-100 text-amber-700 ring-amber-200/50',
    purple: 'bg-purple-100 text-purple-700 ring-purple-200/50',
    pink: 'bg-pink-100 text-pink-700 ring-pink-200/50',
};

const getLabelClass = (label) => {
    return labelColors[label] || 'bg-gray-100 text-gray-600 ring-gray-200/50';
};

const hasFooter = () => {
    return props.card.due_date || (props.card.description && props.card.description.length > 0);
};
</script>

<template>
    <div
        class="card-item group bg-white rounded-lg p-3 cursor-pointer border border-gray-200/80 hover:border-gray-300 transition-all duration-150"
        :class="{
            'border-t-2 border-t-blue-400 -mt-px': dragOver,
            'opacity-50 scale-95': isDragging,
            'shadow-sm hover:shadow-md': !isDragging,
        }"
        draggable="true"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @dragover="handleCardDragOver"
        @dragleave="handleCardDragLeave"
        @drop="handleCardDrop"
        @click="$emit('click')"
    >
        <!-- Color accent bar -->
        <div
            v-if="card.color"
            class="h-1 rounded-full -mt-1 mb-2.5 -mx-0.5"
            :style="{ backgroundColor: card.color }"
        />

        <!-- Labels -->
        <div v-if="card.labels && card.labels.length" class="flex flex-wrap gap-1 mb-2">
            <span
                v-for="label in card.labels"
                :key="label"
                class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide ring-1"
                :class="getLabelClass(label)"
            >
                {{ label }}
            </span>
        </div>

        <!-- Title -->
        <p class="text-sm text-gray-900 font-medium leading-snug group-hover:text-gray-950 transition-colors">
            {{ card.title }}
        </p>

        <!-- Description indicator -->
        <p v-if="card.description" class="text-xs text-gray-400 mt-1.5 line-clamp-2 leading-relaxed">
            {{ card.description }}
        </p>

        <!-- Footer -->
        <div v-if="hasFooter() || card.created_by" class="flex items-center gap-2 mt-2.5 pt-2 border-t border-gray-100">
            <!-- Due date -->
            <span
                v-if="card.due_date"
                class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-md font-medium"
                :class="card.is_overdue
                    ? 'bg-red-50 text-red-600 ring-1 ring-red-200/50'
                    : 'bg-gray-50 text-gray-500'"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ card.due_date }}
            </span>

            <div class="flex-1" />

            <!-- Description icon indicator -->
            <svg v-if="card.description" class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
            </svg>

            <!-- Labels count indicator -->
            <span v-if="card.labels && card.labels.length" class="flex items-center gap-0.5 text-gray-300">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
            </span>
        </div>
    </div>
</template>

<style scoped>
.card-item {
    transform: translateZ(0);
}
.card-item:active {
    cursor: grabbing;
}
</style>
