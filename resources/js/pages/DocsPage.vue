<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import DocsCodeBlock from '@/components/docs/DocsCodeBlock.vue';

const { t } = useI18n();

// Current tab
const currentTab = ref('database');

// Tabs
const tabs = computed(() => [
    { id: 'database', label: t('docs.tabs.database') },
    { id: 'templates', label: t('docs.tabs.templates') },
    { id: 'posts', label: t('docs.tabs.posts') },
]);

// Sidebar sections for each tab
const sidebarSections = computed(() => {
    if (currentTab.value === 'database') {
        return [
            { id: 'bases', label: t('docs.sidebar.bases') },
            { id: 'tables', label: t('docs.sidebar.tables') },
            { id: 'fields', label: t('docs.sidebar.fields') },
            { id: 'rows', label: t('docs.sidebar.rows') },
            { id: 'cells', label: t('docs.sidebar.cells') },
            { id: 'attachments', label: t('docs.sidebar.attachments') },
        ];
    } else if (currentTab.value === 'templates') {
        return [
            { id: 'templates-crud', label: 'Templates CRUD' },
            { id: 'layers', label: t('docs.templates.layers.title') },
            { id: 'generation', label: t('docs.graphicsGeneration.title') },
            { id: 'generated-images', label: t('docs.graphicsGeneration.images.title') },
        ];
    } else if (currentTab.value === 'posts') {
        return [
            { id: 'posts-crud', label: 'Posts CRUD' },
            { id: 'calendar-views', label: 'Calendar & Views' },
            { id: 'workflow', label: 'Workflow (n8n)' },
            { id: 'media', label: 'Media' },
        ];
    }
    return [];
});

// Active section for highlighting
const activeSection = ref('');

// Scroll to section
const scrollToSection = (sectionId) => {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        activeSection.value = sectionId;
    }
};

// Reset active section when tab changes
watch(currentTab, () => {
    activeSection.value = '';
    nextTick(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

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

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-8">
                <nav class="flex gap-8">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="currentTab = tab.id"
                        :class="currentTab === tab.id
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Content with sidebar -->
            <div class="flex gap-8">
                <!-- Left sidebar navigation -->
                <aside class="hidden lg:block w-48 flex-shrink-0">
                    <nav class="sticky top-8">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                            {{ t('docs.onThisPage') }}
                        </h3>
                        <ul class="space-y-1">
                            <li v-for="section in sidebarSections" :key="section.id">
                                <button
                                    @click="scrollToSection(section.id)"
                                    class="w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="activeSection === section.id
                                        ? 'bg-blue-50 text-blue-700 font-medium'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                                >
                                    {{ section.label }}
                                </button>
                            </li>
                        </ul>
                    </nav>
                </aside>

                <!-- Main content -->
                <div class="flex-1 min-w-0 bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                <!-- ===== DATABASE TAB ===== -->
                <template v-if="currentTab === 'database'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.tabs.database') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.sidebar.dataDescription') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- ========== BASES ========== -->
                    <h2 id="bases" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.bases.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.bases.description') }}</p>

                    <!-- List Bases -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/bases</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.bases.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.perPage') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;bas_abc123&quot;,
      &quot;name&quot;: &quot;My Database&quot;,
      &quot;description&quot;: &quot;Sample database&quot;,
      &quot;icon&quot;: &quot;📊&quot;,
      &quot;color&quot;: &quot;#3B82F6&quot;,
      &quot;tables_count&quot;: 3,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
      &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: {
    &quot;current_page&quot;: 1,
    &quot;per_page&quot;: 50,
    &quot;total&quot;: 10
  }
}`" />
                        </div>
                    </div>

                    <!-- Get Base -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/bases/{base_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.bases.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">base_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;bas_abc123&quot;,
    &quot;name&quot;: &quot;My Database&quot;,
    &quot;description&quot;: &quot;Sample database&quot;,
    &quot;icon&quot;: &quot;📊&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;tables_count&quot;: 3,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 404</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;Base not found.&quot;
}`" />
                        </div>
                    </div>

                    <!-- Create Base -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/bases</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.bases.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">icon</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.icon') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.color') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;My New Database&quot;,
  &quot;description&quot;: &quot;Optional description&quot;,
  &quot;icon&quot;: &quot;📊&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;bas_xyz789&quot;,
    &quot;name&quot;: &quot;My New Database&quot;,
    &quot;description&quot;: &quot;Optional description&quot;,
    &quot;icon&quot;: &quot;📊&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;tables_count&quot;: 0,
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 422</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;name&quot;: [&quot;The name field is required.&quot;]
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Base -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/bases/{base_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.bases.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">base_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">icon</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.icon') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.bases.fields.color') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Name&quot;,
  &quot;color&quot;: &quot;#10B981&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;bas_abc123&quot;,
    &quot;name&quot;: &quot;Updated Name&quot;,
    &quot;description&quot;: &quot;Sample database&quot;,
    &quot;icon&quot;: &quot;📊&quot;,
    &quot;color&quot;: &quot;#10B981&quot;,
    &quot;tables_count&quot;: 3,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Base -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/bases/{base_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.bases.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">base_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== TABLES ========== -->
                    <h2 id="tables" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.tables.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.tables.description') }}</p>

                    <!-- List Tables -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/bases/{base_id}/tables</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.tables.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">base_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;tbl_abc123&quot;,
      &quot;base_id&quot;: &quot;bas_abc123&quot;,
      &quot;name&quot;: &quot;Contacts&quot;,
      &quot;description&quot;: &quot;Customer contacts&quot;,
      &quot;icon&quot;: &quot;👥&quot;,
      &quot;fields_count&quot;: 5,
      &quot;rows_count&quot;: 150,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
      &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Get Table -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/tables/{table_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.tables.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">table_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.tables.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;tbl_abc123&quot;,
    &quot;base_id&quot;: &quot;bas_abc123&quot;,
    &quot;name&quot;: &quot;Contacts&quot;,
    &quot;description&quot;: &quot;Customer contacts&quot;,
    &quot;icon&quot;: &quot;👥&quot;,
    &quot;fields_count&quot;: 5,
    &quot;rows_count&quot;: 150,
    &quot;fields&quot;: [
      { &quot;id&quot;: &quot;fld_name&quot;, &quot;name&quot;: &quot;Name&quot;, &quot;type&quot;: &quot;text&quot;, &quot;is_primary&quot;: true },
      { &quot;id&quot;: &quot;fld_email&quot;, &quot;name&quot;: &quot;Email&quot;, &quot;type&quot;: &quot;text&quot; },
      { &quot;id&quot;: &quot;fld_status&quot;, &quot;name&quot;: &quot;Status&quot;, &quot;type&quot;: &quot;select&quot; }
    ],
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Create Table -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/bases/{base_id}/tables</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.tables.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">base_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.bases.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.tables.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.tables.fields.description') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Contacts&quot;,
  &quot;description&quot;: &quot;Customer contact list&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;tbl_xyz789&quot;,
    &quot;base_id&quot;: &quot;bas_abc123&quot;,
    &quot;name&quot;: &quot;Contacts&quot;,
    &quot;description&quot;: &quot;Customer contact list&quot;,
    &quot;fields_count&quot;: 1,
    &quot;rows_count&quot;: 0,
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Table -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/tables/{table_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.tables.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Table Name&quot;,
  &quot;description&quot;: &quot;Updated description&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated table object</p>
                        </div>
                    </div>

                    <!-- Delete Table -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/tables/{table_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.tables.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== FIELDS ========== -->
                    <h2 id="fields" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.fields.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.fields.description') }}</p>

                    <!-- Field Types Reference -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <h4 class="font-medium mb-3">{{ t('docs.fields.types.title') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div><code class="bg-white px-2 py-1 rounded">text</code> - {{ t('field.types.text') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">number</code> - {{ t('field.types.number') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">date</code> - {{ t('field.types.date') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">datetime</code> - {{ t('field.types.datetime') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">checkbox</code> - {{ t('field.types.checkbox') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">select</code> - {{ t('field.types.select') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">multi_select</code> - {{ t('field.types.multi_select') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">url</code> - {{ t('field.types.url') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">attachment</code> - {{ t('field.types.attachment') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">json</code> - JSON</div>
                        </div>
                    </div>

                    <!-- List Fields -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/tables/{table_id}/fields</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.fields.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;fld_name&quot;,
      &quot;table_id&quot;: &quot;tbl_abc123&quot;,
      &quot;name&quot;: &quot;Name&quot;,
      &quot;type&quot;: &quot;text&quot;,
      &quot;is_primary&quot;: true,
      &quot;position&quot;: 0
    },
    {
      &quot;id&quot;: &quot;fld_status&quot;,
      &quot;table_id&quot;: &quot;tbl_abc123&quot;,
      &quot;name&quot;: &quot;Status&quot;,
      &quot;type&quot;: &quot;select&quot;,
      &quot;options&quot;: {
        &quot;choices&quot;: [
          { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;New&quot;, &quot;color&quot;: &quot;#3B82F6&quot; },
          { &quot;id&quot;: &quot;opt_2&quot;, &quot;name&quot;: &quot;Done&quot;, &quot;color&quot;: &quot;#10B981&quot; }
        ]
      },
      &quot;is_primary&quot;: false,
      &quot;position&quot;: 1
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Create Field -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/tables/{table_id}/fields</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.fields.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.fields.attributes.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">type</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.fields.attributes.type') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">options</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.fields.attributes.options') }}</td></tr>
                            </table>
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
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns created field object</p>
                        </div>
                    </div>

                    <!-- Update Field -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/fields/{field_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.fields.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Field Name&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated field object</p>
                        </div>
                    </div>

                    <!-- Delete Field -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/fields/{field_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.fields.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== ROWS ========== -->
                    <h2 id="rows" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.rows.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.rows.description') }}</p>

                    <!-- List Rows -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/tables/{table_id}/rows</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.rows.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.perPage') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">filters</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>JSON filter object</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">sort</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>JSON sort array</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;row_abc123&quot;,
      &quot;cells&quot;: {
        &quot;fld_name&quot;: &quot;John Doe&quot;,
        &quot;fld_email&quot;: &quot;john@example.com&quot;,
        &quot;fld_status&quot;: { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;Active&quot;, &quot;color&quot;: &quot;#10B981&quot; }
      },
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
      &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: {
    &quot;current_page&quot;: 1,
    &quot;per_page&quot;: 50,
    &quot;total&quot;: 150
  }
}`" />
                        </div>
                    </div>

                    <!-- Get Row -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/rows/{row_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.rows.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;row_abc123&quot;,
    &quot;table_id&quot;: &quot;tbl_abc123&quot;,
    &quot;cells&quot;: {
      &quot;fld_name&quot;: &quot;John Doe&quot;,
      &quot;fld_email&quot;: &quot;john@example.com&quot;
    },
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Create Row -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/tables/{table_id}/rows</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.rows.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;cells&quot;: {
    &quot;fld_name&quot;: &quot;Jane Smith&quot;,
    &quot;fld_email&quot;: &quot;jane@example.com&quot;,
    &quot;fld_status&quot;: &quot;opt_1&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns created row object with ID</p>
                        </div>
                    </div>

                    <!-- Update Row -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/rows/{row_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.rows.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;cells&quot;: {
    &quot;fld_status&quot;: &quot;opt_2&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated row object</p>
                        </div>
                    </div>

                    <!-- Delete Row -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/rows/{row_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.rows.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== CELLS ========== -->
                    <h2 id="cells" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.cells.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.cells.description') }}</p>

                    <!-- Update Cell -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/rows/{row_id}/cells/{field_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.cells.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">row_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.cells.attributes.rowId') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">field_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.cells.attributes.fieldId') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;value&quot;: &quot;Updated value&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated cell value</p>
                        </div>
                    </div>

                    <!-- ========== ATTACHMENTS ========== -->
                    <h2 id="attachments" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.attachments.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.attachments.description') }}</p>

                    <!-- Upload Attachment -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/rows/{row_id}/cells/{field_id}/attachments</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.attachments.endpoints.uploadDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request (multipart/form-data)</h4>
                            <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/rows/{row_id}/cells/{field_id}/attachments' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -F 'file=@/path/to/document.pdf'`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;att_xyz789&quot;,
    &quot;filename&quot;: &quot;document.pdf&quot;,
    &quot;mime_type&quot;: &quot;application/pdf&quot;,
    &quot;size&quot;: 102400,
    &quot;url&quot;: &quot;https://example.com/storage/document.pdf&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Attachment -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/attachments/{attachment_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.attachments.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>
                </template>

                <!-- ===== TEMPLATES TAB ===== -->
                <template v-else-if="currentTab === 'templates'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.tabs.templates') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.templates.description') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- Workflow -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-8">
                        <h4 class="font-medium mb-3">{{ t('docs.templates.workflow.title') }}</h4>
                        <ol class="list-decimal list-inside text-gray-600 space-y-1 text-sm">
                            <li>{{ t('docs.templates.workflow.step1') }}</li>
                            <li>{{ t('docs.templates.workflow.step2') }}</li>
                            <li>{{ t('docs.templates.workflow.step3') }}</li>
                            <li>{{ t('docs.templates.workflow.step4') }}</li>
                        </ol>
                    </div>

                    <!-- ========== TEMPLATES CRUD ========== -->
                    <h2 id="templates-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.templates.title') }} CRUD</h2>

                    <!-- List Templates -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/templates</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.perPage') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">in_library</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Filter by library status (true/false)</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;name&quot;: &quot;Instagram Post&quot;,
      &quot;description&quot;: &quot;1080x1080 template&quot;,
      &quot;canvas_width&quot;: 1080,
      &quot;canvas_height&quot;: 1080,
      &quot;canvas_background_color&quot;: &quot;#ffffff&quot;,
      &quot;thumbnail_url&quot;: &quot;https://example.com/thumb.png&quot;,
      &quot;in_library&quot;: true,
      &quot;layers_count&quot;: 5,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
      &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: { &quot;current_page&quot;: 1, &quot;per_page&quot;: 50, &quot;total&quot;: 10 }
}`" />
                        </div>
                    </div>

                    <!-- Get Template -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/templates/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Instagram Post&quot;,
    &quot;canvas_width&quot;: 1080,
    &quot;canvas_height&quot;: 1080,
    &quot;canvas_background_color&quot;: &quot;#ffffff&quot;,
    &quot;layers&quot;: [
      {
        &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
        &quot;name&quot;: &quot;Header Text&quot;,
        &quot;type&quot;: &quot;text&quot;,
        &quot;semantic_tag&quot;: &quot;header&quot;,
        &quot;properties&quot;: {
          &quot;x&quot;: 100, &quot;y&quot;: 200,
          &quot;text&quot;: &quot;Hello World&quot;,
          &quot;fontSize&quot;: 48,
          &quot;fill&quot;: &quot;#000000&quot;
        },
        &quot;visible&quot;: true,
        &quot;locked&quot;: false,
        &quot;order&quot;: 0
      }
    ]
  }
}`" />
                        </div>
                    </div>

                    <!-- Create Template -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/templates</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">canvas_width</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.fields.canvasWidth') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">canvas_height</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.fields.canvasHeight') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">canvas_background_color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.fields.canvasBackgroundColor') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Instagram Post&quot;,
  &quot;description&quot;: &quot;1080x1080 post template&quot;,
  &quot;canvas_width&quot;: 1080,
  &quot;canvas_height&quot;: 1080,
  &quot;canvas_background_color&quot;: &quot;#ffffff&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns created template object</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 422</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;canvas_width&quot;: [&quot;The canvas width field is required.&quot;]
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Template -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/templates/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Template Name&quot;,
  &quot;canvas_background_color&quot;: &quot;#f0f0f0&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated template object</p>
                        </div>
                    </div>

                    <!-- Delete Template -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/templates/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- Duplicate Template -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/templates/{id}/duplicate</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.duplicateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns duplicated template object with new ID</p>
                        </div>
                    </div>

                    <!-- ========== LAYERS ========== -->
                    <h2 id="layers" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.templates.layers.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.templates.layers.description') }}</p>

                    <!-- Layer Types Reference -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <h4 class="font-medium mb-3">{{ t('docs.templates.layers.types.title') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div><code class="bg-white px-2 py-1 rounded">text</code> - {{ t('docs.templates.layers.types.text') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">textbox</code> - {{ t('docs.templates.layers.types.textbox') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">image</code> - {{ t('docs.templates.layers.types.image') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">rectangle</code> - {{ t('docs.templates.layers.types.rectangle') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">ellipse</code> - {{ t('docs.templates.layers.types.ellipse') }}</div>
                            <div><code class="bg-white px-2 py-1 rounded">group</code> - {{ t('docs.templates.layers.types.group') }}</div>
                        </div>
                    </div>

                    <!-- Semantic Tags Reference -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                        <h4 class="font-medium text-purple-800 mb-3">{{ t('docs.templates.semanticTags.title') }}</h4>
                        <p class="text-purple-700 text-sm mb-3">{{ t('docs.templates.semanticTags.description') }}</p>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="font-medium text-purple-800 mb-1">{{ t('docs.templates.semanticTags.content.title') }}:</p>
                                <code class="text-purple-600">header, subtitle, paragraph, social_handle, main_image, logo, cta</code>
                            </div>
                            <div>
                                <p class="font-medium text-purple-800 mb-1">{{ t('docs.templates.semanticTags.style.title') }}:</p>
                                <code class="text-purple-600">primary_color, secondary_color</code>
                            </div>
                        </div>
                    </div>

                    <!-- List Layers -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/templates/{id}/layers</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
      &quot;name&quot;: &quot;Header Text&quot;,
      &quot;type&quot;: &quot;text&quot;,
      &quot;semantic_tag&quot;: &quot;header&quot;,
      &quot;properties&quot;: {
        &quot;x&quot;: 100, &quot;y&quot;: 200,
        &quot;text&quot;: &quot;Hello World&quot;,
        &quot;fontSize&quot;: 48
      },
      &quot;visible&quot;: true,
      &quot;locked&quot;: false,
      &quot;order&quot;: 0
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Add Layer -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/templates/{id}/layers</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.addLayerDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.layers.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">type</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.layers.fields.type') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">properties</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.templates.layers.fields.properties') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">semantic_tag</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.layers.fields.semanticTag') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Header Text&quot;,
  &quot;type&quot;: &quot;text&quot;,
  &quot;properties&quot;: {
    &quot;x&quot;: 100,
    &quot;y&quot;: 200,
    &quot;text&quot;: &quot;Hello World&quot;,
    &quot;fontSize&quot;: 48,
    &quot;fontFamily&quot;: &quot;Inter&quot;,
    &quot;fill&quot;: &quot;#000000&quot;
  },
  &quot;semantic_tag&quot;: &quot;header&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns created layer object</p>
                        </div>
                    </div>

                    <!-- Update Layer -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/layers/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.updateLayerDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;properties&quot;: {
    &quot;x&quot;: 150,
    &quot;y&quot;: 250,
    &quot;fill&quot;: &quot;#FF0000&quot;
  },
  &quot;semantic_tag&quot;: &quot;subtitle&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated layer object</p>
                        </div>
                    </div>

                    <!-- Delete Layer -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/layers/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.templates.endpoints.deleteLayerDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== GENERATION ========== -->
                    <h2 id="generation" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.graphicsGeneration.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.graphicsGeneration.description') }}</p>

                    <!-- Generate Preview -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/library/templates/preview</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.graphicsGeneration.preview.description') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">template_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.graphicsGeneration.preview.parameters.templateId') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">data</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.graphicsGeneration.preview.parameters.data') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">format</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.graphicsGeneration.preview.parameters.format') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">scale</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.graphicsGeneration.preview.parameters.scale') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;template_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;data&quot;: {
    &quot;header&quot;: &quot;Big Sale!&quot;,
    &quot;subtitle&quot;: &quot;Only this week&quot;,
    &quot;main_image&quot;: &quot;https://example.com/product.jpg&quot;,
    &quot;logo&quot;: &quot;https://example.com/logo.png&quot;,
    &quot;primary_color&quot;: &quot;#FF5733&quot;,
    &quot;secondary_color&quot;: &quot;#333333&quot;
  },
  &quot;format&quot;: &quot;png&quot;,
  &quot;scale&quot;: 1
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;image&quot;: &quot;data:image/png;base64,iVBORw0KGgo...&quot;,
    &quot;width&quot;: 1080,
    &quot;height&quot;: 1080,
    &quot;format&quot;: &quot;png&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 404</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;Template not found.&quot;
}`" />
                        </div>
                    </div>

                    <!-- ========== GENERATED IMAGES ========== -->
                    <h2 id="generated-images" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.graphicsGeneration.images.title') }}</h2>

                    <!-- List Generated Images -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/templates/{id}/images</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.graphicsGeneration.images.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
      &quot;url&quot;: &quot;https://example.com/generated/image1.png&quot;,
      &quot;width&quot;: 1080,
      &quot;height&quot;: 1080,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Save Generated Image -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/templates/{id}/images</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.graphicsGeneration.images.saveDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;image&quot;: &quot;data:image/png;base64,iVBORw0KGgo...&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns saved image object with URL</p>
                        </div>
                    </div>

                    <!-- Delete Generated Image -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/generated-images/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.graphicsGeneration.images.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>
                </template>

                <!-- ===== POSTS TAB ===== -->
                <template v-else-if="currentTab === 'posts'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.tabs.posts') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.postsApi.description') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- Workflow -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-8">
                        <h4 class="font-medium mb-3">{{ t('docs.postsApi.workflow.title') }}</h4>
                        <ol class="list-decimal list-inside text-gray-600 space-y-1 text-sm">
                            <li>{{ t('docs.postsApi.workflow.step1') }}</li>
                            <li>{{ t('docs.postsApi.workflow.step2') }}</li>
                            <li>{{ t('docs.postsApi.workflow.step3') }}</li>
                            <li>{{ t('docs.postsApi.workflow.step4') }}</li>
                            <li>{{ t('docs.postsApi.workflow.step5') }}</li>
                        </ol>
                    </div>

                    <!-- Post Statuses Reference -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                        <h4 class="font-medium text-yellow-800 mb-3">{{ t('docs.postsApi.statuses.title') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div><code class="bg-white px-2 py-1 rounded">draft</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">pending_approval</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">approved</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">scheduled</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">published</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">failed</code></div>
                        </div>
                    </div>

                    <!-- ========== POSTS CRUD ========== -->
                    <h2 id="posts-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.postsApi.title') }} CRUD</h2>

                    <!-- List Posts -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.perPage') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">status</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Filter by status</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;title&quot;: &quot;Weekly Update&quot;,
      &quot;main_caption&quot;: &quot;Check out our latest news...&quot;,
      &quot;status&quot;: &quot;pending_approval&quot;,
      &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
      &quot;published_at&quot;: null,
      &quot;media_count&quot;: 2,
      &quot;platform_posts&quot;: [
        { &quot;platform&quot;: &quot;facebook&quot;, &quot;status&quot;: &quot;pending&quot; },
        { &quot;platform&quot;: &quot;instagram&quot;, &quot;status&quot;: &quot;pending&quot; }
      ],
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: { &quot;current_page&quot;: 1, &quot;per_page&quot;: 50, &quot;total&quot;: 25 }
}`" />
                        </div>
                    </div>

                    <!-- Get Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.postsApi.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Weekly Update&quot;,
    &quot;main_caption&quot;: &quot;Check out our latest news...&quot;,
    &quot;status&quot;: &quot;pending_approval&quot;,
    &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;platform_posts&quot;: [
      {
        &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
        &quot;platform&quot;: &quot;facebook&quot;,
        &quot;caption&quot;: &quot;Custom Facebook caption...&quot;,
        &quot;status&quot;: &quot;pending&quot;,
        &quot;external_id&quot;: null,
        &quot;external_url&quot;: null
      }
    ],
    &quot;media&quot;: [
      {
        &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
        &quot;url&quot;: &quot;https://example.com/media/image1.jpg&quot;,
        &quot;type&quot;: &quot;image&quot;
      }
    ]
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 404</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;Post not found.&quot;
}`" />
                        </div>
                    </div>

                    <!-- Create Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.postsApi.fields.title') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">main_caption</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.postsApi.fields.mainCaption') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">platforms</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Array: facebook, instagram, youtube</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">scheduled_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.postsApi.fields.scheduledAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">status</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>draft, pending_approval (default: draft)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Weekly Update&quot;,
  &quot;main_caption&quot;: &quot;Check out our latest news and updates!&quot;,
  &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
  &quot;platforms&quot;: [&quot;facebook&quot;, &quot;instagram&quot;],
  &quot;status&quot;: &quot;pending_approval&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns created post object with ID</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 422</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;main_caption&quot;: [&quot;The main caption field is required.&quot;],
    &quot;platforms&quot;: [&quot;The platforms field is required.&quot;]
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/posts/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Updated Title&quot;,
  &quot;main_caption&quot;: &quot;Updated caption content...&quot;,
  &quot;scheduled_at&quot;: &quot;2024-01-25T10:00:00Z&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns updated post object</p>
                        </div>
                    </div>

                    <!-- Delete Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/posts/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== CALENDAR & VIEWS ========== -->
                    <h2 id="calendar-views" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">Calendar & Views</h2>

                    <!-- Calendar View -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/calendar</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.calendarDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">start_date</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Start date (YYYY-MM-DD)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">end_date</td><td class="text-red-500">{{ t('docs.required') }}</td><td>End date (YYYY-MM-DD)</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;title&quot;: &quot;Weekly Update&quot;,
      &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
      &quot;status&quot;: &quot;approved&quot;,
      &quot;platforms&quot;: [&quot;facebook&quot;, &quot;instagram&quot;]
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Verified Posts (n8n) -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/verified</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.verifiedDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns posts with status "approved" ready for publication</p>
                        </div>
                    </div>

                    <!-- Pending Approval -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/pending-approval</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">Posts pending user approval</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns posts with status "pending_approval"</p>
                        </div>
                    </div>

                    <!-- ========== WORKFLOW (n8n) ========== -->
                    <h2 id="workflow" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">Workflow (n8n)</h2>

                    <!-- Approve Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/approve</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.approveDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;status&quot;: &quot;approved&quot;,
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Reject Post -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/reject</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.rejectDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;reason&quot;: &quot;Please update the image quality&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns post with status "draft"</p>
                        </div>
                    </div>

                    <!-- Mark Published -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/mark-published</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.markPublishedDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">platform</td><td class="text-red-500">{{ t('docs.required') }}</td><td>facebook, instagram, youtube</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">external_id</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Platform post ID</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">external_url</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>URL to published post</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;platform&quot;: &quot;facebook&quot;,
  &quot;external_id&quot;: &quot;123456789&quot;,
  &quot;external_url&quot;: &quot;https://facebook.com/posts/123456789&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns post with platform_post status "published"</p>
                        </div>
                    </div>

                    <!-- Mark Failed -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/mark-failed</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.postsApi.endpoints.markFailedDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">platform</td><td class="text-red-500">{{ t('docs.required') }}</td><td>facebook, instagram, youtube</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">error_message</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Error description</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;platform&quot;: &quot;instagram&quot;,
  &quot;error_message&quot;: &quot;Media aspect ratio is not supported&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <p class="text-gray-500 text-sm">Returns post with platform_post status "failed"</p>
                        </div>
                    </div>

                    <!-- ========== MEDIA ========== -->
                    <h2 id="media" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">Media</h2>

                    <!-- List Media -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/{id}/media</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">List media files attached to a post</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
      &quot;url&quot;: &quot;https://example.com/media/image1.jpg&quot;,
      &quot;type&quot;: &quot;image&quot;,
      &quot;mime_type&quot;: &quot;image/jpeg&quot;,
      &quot;size&quot;: 102400
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Upload Media -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/media</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">Upload media file to a post</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request (multipart/form-data)</h4>
                            <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/posts/{id}/media' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -F 'file=@/path/to/image.jpg'`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <p class="text-gray-500 text-sm">Returns uploaded media object</p>
                        </div>
                    </div>

                    <!-- Delete Media -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/posts/{post_id}/media/{media_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">Delete media file from a post</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>
                </template>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
table td {
    padding: 0.25rem 0.5rem;
}
table td:first-child {
    white-space: nowrap;
}
table td:nth-child(2) {
    white-space: nowrap;
    padding-left: 1rem;
    padding-right: 1rem;
}
</style>
