<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import Button from '@/components/common/Button.vue';

const { t } = useI18n();
const router = useRouter();
const brandsStore = useBrandsStore();
const { confirm } = useConfirm();
const toast = useToast();

const loading = ref(true);

const fetchData = async () => {
    loading.value = true;
    try {
        await brandsStore.fetchBrands();
        await brandsStore.fetchCurrentBrand();
    } catch (error) {
        console.error('Failed to fetch brands:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);

const handleDelete = async (brand, event) => {
    event.stopPropagation();
    const confirmed = await confirm({
        title: t('brands.deleteBrand'),
        message: t('brands.deleteBrandConfirm', { name: brand.name }),
        confirmText: t('common.delete'),
        variant: 'danger',
    });

    if (confirmed) {
        try {
            await brandsStore.deleteBrand(brand.id);
            toast.success(t('brands.brandDeleted'));
        } catch (error) {
            console.error('Failed to delete brand:', error);
        }
    }
};

const handleEdit = (brand, event) => {
    event.stopPropagation();
    router.push({ name: 'brand.edit', params: { brandId: brand.id } });
};

const handleSelectBrand = async (brand) => {
    if (brandsStore.currentBrand?.id === brand.id) return;

    try {
        await brandsStore.setCurrentBrand(brand.id);
        toast.success(t('brands.brandSwitched'));
    } catch (error) {
        console.error('Failed to set current brand:', error);
    }
};

const getEnabledPlatforms = (brand) => {
    if (!brand.platforms) return [];
    return Object.entries(brand.platforms)
        .filter(([_, config]) => config?.enabled)
        .map(([platform]) => platform);
};

const platformIcons = {
    facebook: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`,
    instagram: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>`,
    youtube: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>`,
};

const platformColors = {
    facebook: 'text-blue-600',
    instagram: 'text-pink-600',
    youtube: 'text-red-600',
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <RouterLink
                            :to="{ name: 'dashboard' }"
                            class="p-2 -ml-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </RouterLink>
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">
                                {{ t('brands.pageTitle') }}
                            </h1>
                            <p class="mt-0.5 text-sm text-gray-500">
                                {{ t('brands.noBrandsDescription') }}
                            </p>
                        </div>
                    </div>
                    <RouterLink :to="{ name: 'brand.create' }">
                        <Button>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ t('brands.createNew') }}
                        </Button>
                    </RouterLink>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 max-w-7xl mx-auto">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty state -->
            <div
                v-else-if="brandsStore.brands.length === 0"
                class="text-center py-20"
            >
                <div class="mx-auto w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ t('brands.noBrands') }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">
                    {{ t('brands.noBrandsDescription') }}
                </p>
                <div class="mt-8">
                    <RouterLink :to="{ name: 'brand.create' }">
                        <Button size="lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ t('brands.createNew') }}
                        </Button>
                    </RouterLink>
                </div>
            </div>

            <!-- Brands Grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="brand in brandsStore.brands"
                    :key="brand.id"
                    @click="handleSelectBrand(brand)"
                    class="group relative bg-white rounded-xl border-2 overflow-hidden cursor-pointer transition-all duration-200"
                    :class="[
                        brandsStore.currentBrand?.id === brand.id
                            ? 'border-blue-500 ring-2 ring-blue-100 shadow-md'
                            : 'border-gray-200 hover:border-gray-300 hover:shadow-lg'
                    ]"
                >
                    <!-- Current brand indicator -->
                    <div
                        v-if="brandsStore.currentBrand?.id === brand.id"
                        class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"
                    />

                    <div class="p-6">
                        <!-- Header with name and badge -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <!-- Brand Avatar -->
                                    <div
                                        class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-bold text-white shadow-sm"
                                        :class="brandsStore.currentBrand?.id === brand.id ? 'bg-gradient-to-br from-blue-500 to-indigo-600' : 'bg-gradient-to-br from-gray-400 to-gray-500'"
                                    >
                                        {{ brand.name?.charAt(0)?.toUpperCase() || 'B' }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900 truncate">
                                            {{ brand.name }}
                                        </h3>
                                        <p v-if="brand.industry" class="text-sm text-gray-500 truncate">
                                            {{ t(`brands.industries.${brand.industry}`) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <span
                                v-if="brandsStore.currentBrand?.id === brand.id"
                                class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
                            >
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ t('brands.currentBrand') }}
                            </span>
                        </div>

                        <!-- Description -->
                        <p v-if="brand.description" class="text-sm text-gray-600 line-clamp-2 mb-4">
                            {{ brand.description }}
                        </p>

                        <!-- Platforms -->
                        <div v-if="getEnabledPlatforms(brand).length > 0" class="flex items-center gap-2 mb-4">
                            <span class="text-xs text-gray-400 uppercase tracking-wide">{{ t('posts.platforms.title') }}</span>
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-for="platform in getEnabledPlatforms(brand)"
                                    :key="platform"
                                    class="p-1.5 rounded-lg bg-gray-100"
                                    :class="platformColors[platform]"
                                    :title="t(`posts.platforms.${platform}`)"
                                    v-html="platformIcons[platform]"
                                />
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="font-medium text-gray-700">{{ brand.posts_count ?? 0 }}</span>
                                {{ t('posts.title').toLowerCase() }}
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="font-medium text-gray-700">{{ brand.templates_count ?? 0 }}</span>
                                {{ t('graphics.templates.title').toLowerCase() }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer with actions -->
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <span
                            v-if="brandsStore.currentBrand?.id !== brand.id"
                            class="text-sm text-gray-500"
                        >
                            {{ t('brands.switchBrand') }}
                        </span>
                        <span v-else class="text-sm text-blue-600 font-medium">
                            {{ t('brands.currentBrand') }}
                        </span>

                        <div class="flex items-center gap-1">
                            <button
                                @click="handleEdit(brand, $event)"
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg transition-colors"
                                :title="t('brands.editBrand')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                @click="handleDelete(brand, $event)"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                :title="t('brands.deleteBrand')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add new brand card -->
                <RouterLink
                    :to="{ name: 'brand.create' }"
                    class="group relative bg-white rounded-xl border-2 border-dashed border-gray-300 overflow-hidden cursor-pointer transition-all duration-200 hover:border-blue-400 hover:bg-blue-50/50 min-h-[280px] flex items-center justify-center"
                >
                    <div class="text-center p-6">
                        <div class="mx-auto w-14 h-14 bg-gray-100 group-hover:bg-blue-100 rounded-xl flex items-center justify-center mb-4 transition-colors">
                            <svg class="w-7 h-7 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-gray-900 group-hover:text-blue-700 transition-colors">
                            {{ t('brands.createNew') }}
                        </h3>
                    </div>
                </RouterLink>
            </div>
        </div>
    </div>
</template>
