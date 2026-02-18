<template>
    <div class="space-y-4">
        <h4 class="text-xs font-medium text-gray-300">{{ t('nle.inspector.effects') }}</h4>

        <!-- Active effects -->
        <div v-if="element.effects?.length" class="space-y-2">
            <div
                v-for="(effect, index) in element.effects"
                :key="index"
                class="flex items-center justify-between bg-gray-800 rounded p-2"
            >
                <span class="text-xs text-gray-300 capitalize">{{ effect.type }}</span>
                <button
                    @click="removeEffect(index)"
                    class="text-gray-500 hover:text-red-400 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Available effects -->
        <div>
            <h5 class="text-[10px] text-gray-500 mb-1.5">{{ t('nle.inspector.addEffect') }}</h5>
            <div class="grid grid-cols-2 gap-1.5">
                <button
                    v-for="effect in availableEffects"
                    :key="effect.type"
                    @click="addEffect(effect.type)"
                    class="px-2 py-1.5 text-[11px] text-gray-400 bg-gray-800 hover:bg-gray-700 hover:text-white rounded transition-colors text-left"
                >
                    {{ effect.label }}
                </button>
            </div>
        </div>

        <!-- Transition (between elements) -->
        <div class="pt-2 border-t border-gray-700">
            <h4 class="text-xs font-medium text-gray-300 mb-2">{{ t('nle.inspector.transition') }}</h4>
            <select
                :value="element.transition?.type || ''"
                @change="setTransition($event.target.value)"
                class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
            >
                <option value="">{{ t('nle.inspector.noTransition') }}</option>
                <option value="fade">Fade</option>
                <option value="wipeleft">Wipe Left</option>
                <option value="wiperight">Wipe Right</option>
                <option value="slideup">Slide Up</option>
                <option value="slidedown">Slide Down</option>
                <option value="circlecrop">Circle Crop</option>
            </select>

            <div v-if="element.transition" class="mt-2">
                <label class="block text-[10px] text-gray-500 mb-0.5">{{ t('nle.inspector.transitionDuration') }}</label>
                <input
                    type="number"
                    :value="element.transition.duration || 0.5"
                    @input="updateTransitionDuration(parseFloat($event.target.value) || 0.5)"
                    step="0.1"
                    min="0.1"
                    max="3"
                    class="w-full bg-gray-800 text-white text-sm rounded px-2 py-1 border border-gray-600 focus:border-blue-500 focus:outline-none"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

const { t } = useI18n();
const store = useVideoEditorStore();

const props = defineProps({
    element: { type: Object, required: true },
});

const availableEffects = computed(() => [
    { type: 'brightness', label: t('nle.effects.brightness') },
    { type: 'contrast', label: t('nle.effects.contrast') },
    { type: 'saturation', label: t('nle.effects.saturation') },
    { type: 'blur', label: t('nle.effects.blur') },
    { type: 'grayscale', label: t('nle.effects.grayscale') },
    { type: 'sepia', label: t('nle.effects.sepia') },
]);

function addEffect(type) {
    const effects = [...(props.element.effects || []), { type, value: 1.0 }];
    store.updateElementProperty(props.element.id, 'effects', effects);
}

function removeEffect(index) {
    const effects = [...(props.element.effects || [])];
    effects.splice(index, 1);
    store.updateElementProperty(props.element.id, 'effects', effects);
}

function setTransition(type) {
    if (!type) {
        store.updateElementProperty(props.element.id, 'transition', null);
    } else {
        store.updateElementProperty(props.element.id, 'transition', { type, duration: 0.5 });
    }
}

function updateTransitionDuration(duration) {
    if (props.element.transition) {
        store.updateElementProperty(props.element.id, 'transition', {
            ...props.element.transition,
            duration,
        });
    }
}
</script>
