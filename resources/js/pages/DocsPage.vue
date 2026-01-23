<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import DocsCodeBlock from '@/components/docs/DocsCodeBlock.vue';
import DocsEndpoint from '@/components/docs/DocsEndpoint.vue';
import DocsTable from '@/components/docs/DocsTable.vue';
import DocsTip from '@/components/docs/DocsTip.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

// Current section from URL
const currentSection = computed(() => route.params.section || 'overview');

// Sidebar sections
const sidebarSections = computed(() => [
    {
        title: t('docs.sidebar.gettingStarted'),
        items: [
            { id: 'overview', label: t('docs.sidebar.overview'), icon: 'book' },
            { id: 'authentication', label: t('docs.sidebar.authentication'), icon: 'key' },
            { id: 'quick-start', label: t('docs.sidebar.quickStart'), icon: 'rocket' },
        ],
    },
    {
        title: t('docs.sidebar.resources'),
        items: [
            { id: 'bases', label: t('docs.sidebar.bases'), icon: 'database' },
            { id: 'tables', label: t('docs.sidebar.tables'), icon: 'table' },
            { id: 'fields', label: t('docs.sidebar.fields'), icon: 'columns' },
            { id: 'rows', label: t('docs.sidebar.rows'), icon: 'rows' },
            { id: 'cells', label: t('docs.sidebar.cells'), icon: 'cell' },
            { id: 'attachments', label: t('docs.sidebar.attachments'), icon: 'attachment' },
        ],
    },
    {
        title: t('docs.sidebar.advanced'),
        items: [
            { id: 'filtering', label: t('docs.sidebar.filtering'), icon: 'filter' },
            { id: 'sorting', label: t('docs.sidebar.sorting'), icon: 'sort' },
            { id: 'pagination', label: t('docs.sidebar.pagination'), icon: 'pages' },
            { id: 'errors', label: t('docs.sidebar.errors'), icon: 'alert' },
        ],
    },
]);

// Navigate to section
const navigateToSection = (sectionId) => {
    router.push({ name: 'docs', params: { section: sectionId } });
};

// Mobile sidebar
const showMobileSidebar = ref(false);

// On this page navigation
const pageAnchors = ref([]);
const activeAnchor = ref('');

onMounted(() => {
    updatePageAnchors();
});

watch(currentSection, () => {
    nextTick(() => {
        updatePageAnchors();
        window.scrollTo(0, 0);
    });
});

const updatePageAnchors = () => {
    const headings = document.querySelectorAll('.docs-content h2[id], .docs-content h3[id]');
    pageAnchors.value = Array.from(headings).map((h) => ({
        id: h.id,
        text: h.textContent,
        level: h.tagName === 'H2' ? 2 : 3,
    }));
};

const scrollToAnchor = (id) => {
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        activeAnchor.value = id;
    }
};

// Base URL for examples
const baseUrl = computed(() => window.location.origin + '/api/v1');
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page title -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('docs.title') }}</h1>
                    <p class="text-gray-500 mt-1">{{ t('docs.subtitle') }}</p>
                </div>

                <div class="flex gap-8">
                <!-- Sidebar -->
                    <aside class="hidden lg:block w-64 flex-shrink-0">
                        <nav class="sticky top-8 space-y-6 pr-4">
                            <div v-for="section in sidebarSections" :key="section.title">
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                                    {{ section.title }}
                                </h3>
                                <ul class="space-y-1">
                                    <li v-for="item in section.items" :key="item.id">
                                        <button
                                            @click="navigateToSection(item.id)"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition-colors"
                                            :class="currentSection === item.id
                                                ? 'bg-blue-50 text-blue-700 font-medium'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                                        >
                                            <span class="w-5 h-5 flex items-center justify-center opacity-60">
                                                <!-- Icons -->
                                                <svg v-if="item.icon === 'book'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'key'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'rocket'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'database'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'table'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'columns'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'rows'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'cell'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'attachment'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'filter'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'sort'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'pages'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                                <svg v-else-if="item.icon === 'alert'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                            </span>
                                            {{ item.label }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </nav>
                    </aside>

                    <!-- Main content -->
                    <main class="flex-1 min-w-0">
                        <div class="docs-content bg-white rounded-xl shadow-sm border border-gray-200 p-10">
                            <!-- Overview Section -->
                            <template v-if="currentSection === 'overview'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.overview.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.overview.description') }}</p>

                                <h2 id="base-url" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.overview.baseUrl') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.overview.baseUrlDescription') }}</p>
                                <DocsCodeBlock language="text" :code="baseUrl" />

                                <h2 id="versioning" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.overview.versioning') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.overview.versioningDescription') }}</p>

                                <h2 id="response-format" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.overview.responseFormat') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.overview.responseFormatDescription') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;bas_abc123&quot;,
    &quot;name&quot;: &quot;My Database&quot;,
    ...
  }
}`" />

                                <h2 id="content-type" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.overview.contentType') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.overview.contentTypeDescription') }}</p>
                                <DocsCodeBlock language="http" :code="`Content-Type: application/json
Accept: application/json`" />
                            </template>

                            <!-- Authentication Section -->
                            <template v-else-if="currentSection === 'authentication'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.auth.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.auth.description') }}</p>

                                <h2 id="get-token" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.auth.getToken') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.auth.getTokenDescription') }}</p>

                                <h2 id="using-token" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.auth.usingToken') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.auth.usingTokenDescription') }}</p>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/bases' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Accept: application/json'`" />

                                <h2 id="token-security" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.auth.tokenSecurity') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.auth.tokenSecurityDescription') }}</p>
                                <DocsTip type="warning">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>{{ t('docs.auth.tokenSecurityTips.tip1') }}</li>
                                        <li>{{ t('docs.auth.tokenSecurityTips.tip2') }}</li>
                                        <li>{{ t('docs.auth.tokenSecurityTips.tip3') }}</li>
                                        <li>{{ t('docs.auth.tokenSecurityTips.tip4') }}</li>
                                    </ul>
                                </DocsTip>
                            </template>

                            <!-- Quick Start Section -->
                            <template v-else-if="currentSection === 'quick-start'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.quickStart.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.quickStart.description') }}</p>

                                <h2 id="step-1" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.quickStart.step1') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.quickStart.step1Description') }}</p>

                                <h2 id="step-2" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.quickStart.step2') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.quickStart.step2Description') }}</p>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/bases' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Accept: application/json'`" />

                                <p class="text-gray-600 mt-4 mb-2">{{ t('docs.response') }}:</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;bas_abc123&quot;,
      &quot;name&quot;: &quot;My First Database&quot;,
      &quot;description&quot;: &quot;Sample database&quot;,
      &quot;tables_count&quot;: 3,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ]
}`" />

                                <h2 id="step-3" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.quickStart.step3') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.quickStart.step3Description') }}</p>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/bases/bas_abc123/tables' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Accept: application/json'`" />

                                <DocsTip type="success">
                                    <strong>{{ t('docs.quickStart.congratulations') }}</strong>
                                    <p>{{ t('docs.quickStart.congratulationsDescription') }}</p>
                                </DocsTip>
                            </template>

                            <!-- Bases Section -->
                            <template v-else-if="currentSection === 'bases'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.bases.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.bases.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['id', t('docs.bases.fields.id')],
                                    ['name', t('docs.bases.fields.name')],
                                    ['description', t('docs.bases.fields.description')],
                                    ['icon', t('docs.bases.fields.icon')],
                                    ['color', t('docs.bases.fields.color')],
                                    ['tables_count', t('docs.bases.fields.tablesCount')],
                                    ['created_at', t('docs.bases.fields.createdAt')],
                                    ['updated_at', t('docs.bases.fields.updatedAt')],
                                ]" />

                                <h2 id="list-bases" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.endpoints.list') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.endpoints.listDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/bases`" />

                                <h2 id="get-base" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.endpoints.get') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.endpoints.getDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/bases/{base_id}`" />

                                <h2 id="create-base" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.endpoints.create') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.endpoints.createDescription') }}</p>
                                <DocsEndpoint method="POST" :path="`${baseUrl}/bases`" />
                                <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;My New Database&quot;,
  &quot;description&quot;: &quot;Optional description&quot;,
  &quot;icon&quot;: &quot;📊&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;
}`" />

                                <h2 id="update-base" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.endpoints.update') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.endpoints.updateDescription') }}</p>
                                <DocsEndpoint method="PUT" :path="`${baseUrl}/bases/{base_id}`" />

                                <h2 id="delete-base" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.bases.endpoints.delete') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.bases.endpoints.deleteDescription') }}</p>
                                <DocsEndpoint method="DELETE" :path="`${baseUrl}/bases/{base_id}`" />
                            </template>

                            <!-- Tables Section -->
                            <template v-else-if="currentSection === 'tables'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.tables.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.tables.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['id', t('docs.tables.fields.id')],
                                    ['base_id', t('docs.tables.fields.baseId')],
                                    ['name', t('docs.tables.fields.name')],
                                    ['description', t('docs.tables.fields.description')],
                                    ['icon', t('docs.tables.fields.icon')],
                                    ['fields_count', t('docs.tables.fields.fieldsCount')],
                                    ['rows_count', t('docs.tables.fields.rowsCount')],
                                    ['created_at', t('docs.tables.fields.createdAt')],
                                    ['updated_at', t('docs.tables.fields.updatedAt')],
                                ]" />

                                <h2 id="list-tables" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.endpoints.list') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.endpoints.listDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/bases/{base_id}/tables`" />

                                <h2 id="get-table" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.endpoints.get') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.endpoints.getDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/tables/{table_id}`" />

                                <h2 id="create-table" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.endpoints.create') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.endpoints.createDescription') }}</p>
                                <DocsEndpoint method="POST" :path="`${baseUrl}/bases/{base_id}/tables`" />
                                <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Contacts&quot;,
  &quot;description&quot;: &quot;Customer contact list&quot;
}`" />

                                <h2 id="update-table" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.endpoints.update') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.endpoints.updateDescription') }}</p>
                                <DocsEndpoint method="PUT" :path="`${baseUrl}/tables/{table_id}`" />

                                <h2 id="delete-table" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.tables.endpoints.delete') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.tables.endpoints.deleteDescription') }}</p>
                                <DocsEndpoint method="DELETE" :path="`${baseUrl}/tables/{table_id}`" />
                            </template>

                            <!-- Fields Section -->
                            <template v-else-if="currentSection === 'fields'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.fields.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.fields.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['id', t('docs.fields.attributes.id')],
                                    ['table_id', t('docs.fields.attributes.tableId')],
                                    ['name', t('docs.fields.attributes.name')],
                                    ['type', t('docs.fields.attributes.type')],
                                    ['options', t('docs.fields.attributes.options')],
                                    ['is_primary', t('docs.fields.attributes.isPrimary')],
                                    ['position', t('docs.fields.attributes.position')],
                                ]" />

                                <h2 id="field-types" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.types.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.types.description') }}</p>
                                <DocsTable :headers="['Type', t('common.description')]" :rows="[
                                    ['text', t('docs.fields.types.text')],
                                    ['number', t('docs.fields.types.number')],
                                    ['date', t('docs.fields.types.date')],
                                    ['datetime', t('docs.fields.types.datetime')],
                                    ['checkbox', t('docs.fields.types.checkbox')],
                                    ['select', t('docs.fields.types.select')],
                                    ['multi_select', t('docs.fields.types.multiSelect')],
                                    ['url', t('docs.fields.types.url')],
                                    ['attachment', t('docs.fields.types.attachment')],
                                    ['json', t('docs.fields.types.json')],
                                ]" />

                                <h2 id="list-fields" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.endpoints.list') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.endpoints.listDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/tables/{table_id}/fields`" />

                                <h2 id="create-field" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.endpoints.create') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.endpoints.createDescription') }}</p>
                                <DocsEndpoint method="POST" :path="`${baseUrl}/tables/{table_id}/fields`" />
                                <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Status&quot;,
  &quot;type&quot;: &quot;select&quot;,
  &quot;options&quot;: {
    &quot;choices&quot;: [
      { &quot;name&quot;: &quot;New&quot;, &quot;color&quot;: &quot;#3B82F6&quot; },
      { &quot;name&quot;: &quot;In Progress&quot;, &quot;color&quot;: &quot;#F59E0B&quot; },
      { &quot;name&quot;: &quot;Done&quot;, &quot;color&quot;: &quot;#10B981&quot; }
    ]
  }
}`" />

                                <h2 id="update-field" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.endpoints.update') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.endpoints.updateDescription') }}</p>
                                <DocsEndpoint method="PUT" :path="`${baseUrl}/fields/{field_id}`" />

                                <h2 id="delete-field" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.fields.endpoints.delete') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.fields.endpoints.deleteDescription') }}</p>
                                <DocsEndpoint method="DELETE" :path="`${baseUrl}/fields/{field_id}`" />
                            </template>

                            <!-- Rows Section -->
                            <template v-else-if="currentSection === 'rows'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.rows.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.rows.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['id', t('docs.rows.attributes.id')],
                                    ['table_id', t('docs.rows.attributes.tableId')],
                                    ['cells', t('docs.rows.attributes.cells')],
                                    ['position', t('docs.rows.attributes.position')],
                                    ['created_at', t('docs.rows.attributes.createdAt')],
                                    ['updated_at', t('docs.rows.attributes.updatedAt')],
                                ]" />

                                <h2 id="list-rows" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.endpoints.list') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.endpoints.listDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/tables/{table_id}/rows`" />

                                <p class="text-gray-600 mt-4 mb-2">{{ t('docs.response') }}:</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;row_abc123&quot;,
      &quot;cells&quot;: {
        &quot;fld_name&quot;: &quot;John Doe&quot;,
        &quot;fld_email&quot;: &quot;john@example.com&quot;,
        &quot;fld_status&quot;: { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;Active&quot;, &quot;color&quot;: &quot;#10B981&quot; }
      },
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: {
    &quot;current_page&quot;: 1,
    &quot;per_page&quot;: 50,
    &quot;total&quot;: 150
  }
}`" />

                                <h2 id="get-row" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.endpoints.get') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.endpoints.getDescription') }}</p>
                                <DocsEndpoint method="GET" :path="`${baseUrl}/rows/{row_id}`" />

                                <h2 id="create-row" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.endpoints.create') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.endpoints.createDescription') }}</p>
                                <DocsEndpoint method="POST" :path="`${baseUrl}/tables/{table_id}/rows`" />
                                <DocsCodeBlock language="json" :code="`{
  &quot;cells&quot;: {
    &quot;fld_name&quot;: &quot;Jane Smith&quot;,
    &quot;fld_email&quot;: &quot;jane@example.com&quot;,
    &quot;fld_status&quot;: &quot;opt_1&quot;
  }
}`" />

                                <h2 id="update-row" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.endpoints.update') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.endpoints.updateDescription') }}</p>
                                <DocsEndpoint method="PUT" :path="`${baseUrl}/rows/{row_id}`" />

                                <h2 id="delete-row" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.rows.endpoints.delete') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.rows.endpoints.deleteDescription') }}</p>
                                <DocsEndpoint method="DELETE" :path="`${baseUrl}/rows/{row_id}`" />
                            </template>

                            <!-- Cells Section -->
                            <template v-else-if="currentSection === 'cells'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.cells.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.cells.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.cells.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.cells.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['row_id', t('docs.cells.attributes.rowId')],
                                    ['field_id', t('docs.cells.attributes.fieldId')],
                                    ['value', t('docs.cells.attributes.value')],
                                ]" />

                                <h2 id="value-formats" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.cells.valueFormats.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.cells.valueFormats.description') }}</p>
                                <DocsTable :headers="['Type', 'Format']" :rows="[
                                    ['text', t('docs.cells.valueFormats.text')],
                                    ['number', t('docs.cells.valueFormats.number')],
                                    ['date', t('docs.cells.valueFormats.date')],
                                    ['datetime', t('docs.cells.valueFormats.datetime')],
                                    ['checkbox', t('docs.cells.valueFormats.checkbox')],
                                    ['select', t('docs.cells.valueFormats.select')],
                                    ['multi_select', t('docs.cells.valueFormats.multiSelect')],
                                    ['url', t('docs.cells.valueFormats.url')],
                                    ['attachment', t('docs.cells.valueFormats.attachment')],
                                    ['json', t('docs.cells.valueFormats.json')],
                                ]" />

                                <h2 id="update-cell" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.cells.endpoints.update') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.cells.endpoints.updateDescription') }}</p>
                                <DocsEndpoint method="PUT" :path="`${baseUrl}/rows/{row_id}/cells/{field_id}`" />
                                <DocsCodeBlock language="json" :code="`{
  &quot;value&quot;: &quot;Updated value&quot;
}`" />
                            </template>

                            <!-- Attachments Section -->
                            <template v-else-if="currentSection === 'attachments'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.attachments.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.attachments.description') }}</p>

                                <h2 id="object" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.attachments.object') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.attachments.objectDescription') }}</p>
                                <DocsTable :headers="[t('docs.parameters'), t('common.description')]" :rows="[
                                    ['id', t('docs.attachments.attributes.id')],
                                    ['filename', t('docs.attachments.attributes.filename')],
                                    ['mime_type', t('docs.attachments.attributes.mimeType')],
                                    ['size', t('docs.attachments.attributes.size')],
                                    ['url', t('docs.attachments.attributes.url')],
                                    ['thumbnail_url', t('docs.attachments.attributes.thumbnailUrl')],
                                ]" />

                                <h2 id="upload" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.attachments.endpoints.upload') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.attachments.endpoints.uploadDescription') }}</p>
                                <DocsEndpoint method="POST" :path="`${baseUrl}/rows/{row_id}/cells/{field_id}/attachments`" />
                                <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/rows/{row_id}/cells/{field_id}/attachments' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -F 'file=@/path/to/document.pdf'`" />

                                <h2 id="delete-attachment" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.attachments.endpoints.delete') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.attachments.endpoints.deleteDescription') }}</p>
                                <DocsEndpoint method="DELETE" :path="`${baseUrl}/attachments/{attachment_id}`" />

                                <h2 id="limits" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.attachments.limits.title') }}</h2>
                                <DocsTip type="info">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>{{ t('docs.attachments.limits.maxFileSize') }}</li>
                                        <li>{{ t('docs.attachments.limits.allowedTypes') }}</li>
                                    </ul>
                                </DocsTip>
                            </template>

                            <!-- Filtering Section -->
                            <template v-else-if="currentSection === 'filtering'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.filtering.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.filtering.description') }}</p>

                                <h2 id="syntax" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.filtering.syntax') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.filtering.syntaxDescription') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;filters&quot;: {
    &quot;conjunction&quot;: &quot;and&quot;,
    &quot;conditions&quot;: [
      {
        &quot;field_id&quot;: &quot;fld_name&quot;,
        &quot;operator&quot;: &quot;contains&quot;,
        &quot;value&quot;: &quot;John&quot;
      }
    ]
  }
}`" />

                                <h2 id="conjunction" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.filtering.conjunction') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.filtering.conjunctionDescription') }}</p>
                                <ul class="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">and</code> - {{ t('docs.filtering.conjunctionAnd').split(' - ')[1] }}</li>
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">or</code> - {{ t('docs.filtering.conjunctionOr').split(' - ')[1] }}</li>
                                </ul>

                                <h2 id="operators" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.filtering.operators.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.filtering.operators.description') }}</p>

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.textOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['equals', t('filter.operators.equals')],
                                    ['not_equals', t('filter.operators.not_equals')],
                                    ['contains', t('filter.operators.contains')],
                                    ['not_contains', t('filter.operators.not_contains')],
                                    ['starts_with', t('filter.operators.starts_with')],
                                    ['ends_with', t('filter.operators.ends_with')],
                                    ['is_empty', t('filter.operators.is_empty')],
                                    ['is_not_empty', t('filter.operators.is_not_empty')],
                                ]" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.numberOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['equals', t('filter.operators.equals')],
                                    ['not_equals', t('filter.operators.not_equals')],
                                    ['greater_than', t('filter.operators.greater_than')],
                                    ['less_than', t('filter.operators.less_than')],
                                    ['greater_or_equal', t('filter.operators.greater_or_equal')],
                                    ['less_or_equal', t('filter.operators.less_or_equal')],
                                    ['between', t('filter.operators.between')],
                                    ['is_empty', t('filter.operators.is_empty')],
                                    ['is_not_empty', t('filter.operators.is_not_empty')],
                                ]" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.dateOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['equals', t('filter.operators.equals')],
                                    ['not_equals', t('filter.operators.not_equals')],
                                    ['before', t('filter.operators.before')],
                                    ['after', t('filter.operators.after')],
                                    ['on_or_before', t('filter.operators.on_or_before')],
                                    ['on_or_after', t('filter.operators.on_or_after')],
                                    ['between', t('filter.operators.between')],
                                    ['is_empty', t('filter.operators.is_empty')],
                                    ['is_not_empty', t('filter.operators.is_not_empty')],
                                ]" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.checkboxOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['is_true', t('filter.operators.is_true')],
                                    ['is_false', t('filter.operators.is_false')],
                                ]" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.selectOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['equals', t('filter.operators.equals')],
                                    ['not_equals', t('filter.operators.not_equals')],
                                    ['is_any_of', t('filter.operators.is_any_of')],
                                    ['is_none_of', t('filter.operators.is_none_of')],
                                    ['is_empty', t('filter.operators.is_empty')],
                                    ['is_not_empty', t('filter.operators.is_not_empty')],
                                ]" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.operators.multiSelectOperators') }}</h3>
                                <DocsTable :headers="['Operator', t('common.description')]" :rows="[
                                    ['contains_any', t('filter.operators.contains_any')],
                                    ['contains_all', t('filter.operators.contains_all')],
                                    ['is_empty', t('filter.operators.is_empty')],
                                    ['is_not_empty', t('filter.operators.is_not_empty')],
                                ]" />

                                <h2 id="examples" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.filtering.examples.title') }}</h2>

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.examples.simpleFilter') }}</h3>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/tables/{table_id}/rows?filters={&quot;conditions&quot;:[{&quot;field_id&quot;:&quot;fld_name&quot;,&quot;operator&quot;:&quot;contains&quot;,&quot;value&quot;:&quot;John&quot;}]}' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.examples.multipleConditions') }}</h3>
                                <DocsCodeBlock language="json" :code="`{
  &quot;filters&quot;: {
    &quot;conjunction&quot;: &quot;and&quot;,
    &quot;conditions&quot;: [
      { &quot;field_id&quot;: &quot;fld_status&quot;, &quot;operator&quot;: &quot;equals&quot;, &quot;value&quot;: &quot;opt_active&quot; },
      { &quot;field_id&quot;: &quot;fld_price&quot;, &quot;operator&quot;: &quot;greater_than&quot;, &quot;value&quot;: 100 }
    ]
  }
}`" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.filtering.examples.rangeFilter') }}</h3>
                                <DocsCodeBlock language="json" :code="`{
  &quot;filters&quot;: {
    &quot;conditions&quot;: [
      {
        &quot;field_id&quot;: &quot;fld_date&quot;,
        &quot;operator&quot;: &quot;between&quot;,
        &quot;value&quot;: [&quot;2024-01-01&quot;, &quot;2024-12-31&quot;]
      }
    ]
  }
}`" />
                            </template>

                            <!-- Sorting Section -->
                            <template v-else-if="currentSection === 'sorting'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.sorting.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.sorting.description') }}</p>

                                <h2 id="syntax" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.sorting.syntax') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.sorting.syntaxDescription') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;sort&quot;: [
    { &quot;field_id&quot;: &quot;fld_name&quot;, &quot;direction&quot;: &quot;asc&quot; }
  ]
}`" />

                                <h2 id="directions" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.sorting.directions.title') }}</h2>
                                <ul class="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">asc</code> - {{ t('docs.sorting.directions.asc').split(' - ')[1] }}</li>
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">desc</code> - {{ t('docs.sorting.directions.desc').split(' - ')[1] }}</li>
                                </ul>

                                <h2 id="multi-sort" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.sorting.multiSort') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.sorting.multiSortDescription') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;sort&quot;: [
    { &quot;field_id&quot;: &quot;fld_status&quot;, &quot;direction&quot;: &quot;asc&quot; },
    { &quot;field_id&quot;: &quot;fld_name&quot;, &quot;direction&quot;: &quot;asc&quot; }
  ]
}`" />

                                <h2 id="null-handling" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.sorting.nullHandling') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.sorting.nullHandlingDescription') }}</p>

                                <h2 id="examples" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.sorting.examples.title') }}</h2>

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.sorting.examples.simpleSort') }}</h3>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/tables/{table_id}/rows?sort=[{&quot;field_id&quot;:&quot;fld_name&quot;,&quot;direction&quot;:&quot;asc&quot;}]' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.sorting.examples.descSort') }}</h3>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/tables/{table_id}/rows?sort=[{&quot;field_id&quot;:&quot;fld_date&quot;,&quot;direction&quot;:&quot;desc&quot;}]' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />
                            </template>

                            <!-- Pagination Section -->
                            <template v-else-if="currentSection === 'pagination'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.paginationSection.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.paginationSection.description') }}</p>

                                <h2 id="parameters" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.paginationSection.parameters.title') }}</h2>
                                <ul class="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">page</code> - {{ t('docs.paginationSection.parameters.page').split(' - ')[1] }}</li>
                                    <li><code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm">per_page</code> - {{ t('docs.paginationSection.parameters.perPage').split(' - ')[1] }}</li>
                                </ul>

                                <h2 id="response" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.paginationSection.response.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.paginationSection.response.description') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [...],
  &quot;meta&quot;: {
    &quot;current_page&quot;: 1,
    &quot;from&quot;: 1,
    &quot;last_page&quot;: 10,
    &quot;per_page&quot;: 50,
    &quot;to&quot;: 50,
    &quot;total&quot;: 500
  },
  &quot;links&quot;: {
    &quot;first&quot;: &quot;${baseUrl}/tables/{id}/rows?page=1&quot;,
    &quot;last&quot;: &quot;${baseUrl}/tables/{id}/rows?page=10&quot;,
    &quot;prev&quot;: null,
    &quot;next&quot;: &quot;${baseUrl}/tables/{id}/rows?page=2&quot;
  }
}`" />

                                <h2 id="examples" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.paginationSection.examples.title') }}</h2>

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.paginationSection.examples.basicPagination') }}</h3>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/tables/{table_id}/rows?page=2' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />

                                <h3 class="text-lg font-medium text-gray-800 mt-6 mb-3">{{ t('docs.paginationSection.examples.customPerPage') }}</h3>
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/tables/{table_id}/rows?page=1&per_page=25' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />
                            </template>

                            <!-- Errors Section -->
                            <template v-else-if="currentSection === 'errors'">
                                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ t('docs.errorsSection.title') }}</h1>
                                <p class="text-lg text-gray-600 mb-8">{{ t('docs.errorsSection.description') }}</p>

                                <h2 id="format" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.errorsSection.format') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.errorsSection.formatDescription') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;name&quot;: [&quot;The name field is required.&quot;]
  }
}`" />

                                <h2 id="codes" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.errorsSection.codes.title') }}</h2>
                                <DocsTable :headers="['Code', t('common.description')]" :rows="[
                                    ['400', t('docs.errorsSection.codes.400').split(' - ')[1]],
                                    ['401', t('docs.errorsSection.codes.401').split(' - ')[1]],
                                    ['403', t('docs.errorsSection.codes.403').split(' - ')[1]],
                                    ['404', t('docs.errorsSection.codes.404').split(' - ')[1]],
                                    ['422', t('docs.errorsSection.codes.422').split(' - ')[1]],
                                    ['429', t('docs.errorsSection.codes.429').split(' - ')[1]],
                                    ['500', t('docs.errorsSection.codes.500').split(' - ')[1]],
                                ]" />

                                <h2 id="validation" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.errorsSection.validation.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.errorsSection.validation.description') }}</p>
                                <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;name&quot;: [&quot;The name field is required.&quot;],
    &quot;email&quot;: [
      &quot;The email field is required.&quot;,
      &quot;The email must be a valid email address.&quot;
    ]
  }
}`" />

                                <h2 id="handling" class="text-xl font-semibold text-gray-900 mt-8 mb-4">{{ t('docs.errorsSection.handling.title') }}</h2>
                                <p class="text-gray-600 mb-4">{{ t('docs.errorsSection.handling.description') }}</p>
                                <DocsCodeBlock language="javascript" :code="`try {
  const response = await fetch('${baseUrl}/bases', {
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN',
      'Accept': 'application/json'
    }
  });

  if (!response.ok) {
    const error = await response.json();

    switch (response.status) {
      case 401:
        console.error('Invalid or expired token');
        break;
      case 422:
        console.error('Validation errors:', error.errors);
        break;
      case 429:
        console.error('Rate limit exceeded, retry later');
        break;
      default:
        console.error('API error:', error.message);
    }
    return;
  }

  const data = await response.json();
  console.log('Success:', data);
} catch (e) {
  console.error('Network error:', e);
}`" />
                            </template>

                            <!-- Default / Not found -->
                            <template v-else>
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ t('common.noData') }}</h2>
                                    <button @click="navigateToSection('overview')" class="text-blue-600 hover:text-blue-700">
                                        {{ t('docs.sidebar.overview') }} →
                                    </button>
                                </div>
                            </template>
                        </div>
                    </main>

                    <!-- Right sidebar - On this page -->
                    <aside class="hidden xl:block w-56 flex-shrink-0">
                        <nav v-if="pageAnchors.length > 0" class="sticky top-8 pl-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                                {{ t('docs.onThisPage') }}
                            </h3>
                            <ul class="space-y-2">
                                <li v-for="anchor in pageAnchors" :key="anchor.id">
                                    <button
                                        @click="scrollToAnchor(anchor.id)"
                                        class="text-sm text-gray-500 hover:text-gray-900 transition-colors text-left"
                                        :class="{
                                            'text-blue-600': activeAnchor === anchor.id,
                                            'pl-3': anchor.level === 3
                                        }"
                                    >
                                        {{ anchor.text }}
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    </aside>
                </div>
            </div>
        </div>
</template>

<style scoped>
.docs-content h2 {
    scroll-margin-top: 2rem;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.docs-content h3 {
    scroll-margin-top: 2rem;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.docs-content h1 {
    margin-bottom: 1rem;
}

.docs-content p {
    margin-bottom: 1rem;
    line-height: 1.6;
}

.docs-content > * + * {
    margin-top: 1.5rem;
}

.docs-content h2 + p {
    margin-top: 0.5rem;
}

.docs-content h1 + p {
    margin-top: 0.5rem;
}

.docs-content h3 + p {
    margin-top: 0.5rem;
}
</style>
