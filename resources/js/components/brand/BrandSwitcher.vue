<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { storeToRefs } from 'pinia';

const { t } = useI18n();
const brandsStore = useBrandsStore();
const { brands, currentBrand, loading } = storeToRefs(brandsStore);

const isOpen = ref(false);
const dropdownRef = ref(null);

const sortedBrands = computed(() => {
    return [...brands.value].sort((a, b) => {
        // Current brand first
        if (a.id === currentBrand.value?.id) return -1;
        if (b.id === currentBrand.value?.id) return 1;
        return a.name.localeCompare(b.name);
    });
});

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const selectBrand = async (brand) => {
    if (brand.id === currentBrand.value?.id) {
        isOpen.value = false;
        return;
    }

    try {
        await brandsStore.setCurrentBrand(brand.id);
        isOpen.value = false;
        // Emit event for parent to handle navigation/refresh if needed
        window.dispatchEvent(new CustomEvent('brand-changed', { detail: brand }));
    } catch (error) {
        console.error('Failed to switch brand:', error);
    }
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    if (brands.value.length === 0) {
        await brandsStore.fetchBrands();
    }
    if (!currentBrand.value) {
        await brandsStore.fetchCurrentBrand();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            @click="toggleDropdown"
            class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="loading"
        >
            <span v-if="currentBrand" class="truncate max-w-[150px]">
                {{ currentBrand.name }}
            </span>
            <span v-else class="text-gray-400">
                {{ t('brands.selectBrand') }}
            </span>
            <svg
                class="w-4 h-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 z-50 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
            >
                <div v-if="sortedBrands.length > 0">
                    <button
                        v-for="brand in sortedBrands"
                        :key="brand.id"
                        @click="selectBrand(brand)"
                        class="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-gray-50"
                        :class="{ 'bg-blue-50': brand.id === currentBrand?.id }"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ brand.name }}
                            </p>
                            <p v-if="brand.industry" class="text-xs text-gray-500 truncate">
                                {{ brand.industry }}
                            </p>
                        </div>
                        <svg
                            v-if="brand.id === currentBrand?.id"
                            class="w-5 h-5 text-blue-600 flex-shrink-0"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div v-else class="px-4 py-3 text-sm text-gray-500 text-center">
                    {{ t('brands.noBrands') }}
                </div>

                <div class="border-t border-gray-200 mt-1 pt-1">
                    <router-link
                        to="/brands/new"
                        @click="isOpen = false"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t('brands.createNew') }}
                    </router-link>
                    <router-link
                        to="/brands"
                        @click="isOpen = false"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ t('brands.manageBrands') }}
                    </router-link>
                </div>
            </div>
        </Transition>
    </div>
</template>
