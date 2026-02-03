<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import DocsCodeBlock from '@/components/docs/DocsCodeBlock.vue';

const { t } = useI18n();

// Current tab
const currentTab = ref('general');

// Tabs
const tabs = computed(() => [
    { id: 'general', label: t('docs.tabs.general') },
    { id: 'database', label: t('docs.tabs.database') },
    { id: 'templates', label: t('docs.tabs.templates') },
    { id: 'posts', label: t('docs.tabs.posts') },
    { id: 'calendar', label: t('docs.tabs.calendar') },
    { id: 'boards', label: t('docs.tabs.boards') },
    { id: 'automation', label: t('docs.tabs.automation') },
]);

// Sidebar sections for each tab
const sidebarSections = computed(() => {
    if (currentTab.value === 'general') {
        return [
            { id: 'about', label: t('docs.general.about') },
            { id: 'api-intro', label: t('docs.general.apiIntro.title') },
            { id: 'authentication', label: t('docs.general.authentication') },
            { id: 'quick-start', label: t('docs.general.quickStart') },
            { id: 'errors', label: t('docs.general.errors.title') },
            { id: 'rate-limits', label: t('docs.general.rateLimit.title') },
        ];
    } else if (currentTab.value === 'database') {
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
    } else if (currentTab.value === 'calendar') {
        return [
            { id: 'events-overview', label: t('docs.calendar.overview') },
            { id: 'events-crud', label: t('docs.calendar.eventsCrud') },
            { id: 'events-calendar', label: t('docs.calendar.calendarView') },
            { id: 'events-reschedule', label: t('docs.calendar.reschedule') },
        ];
    } else if (currentTab.value === 'boards') {
        return [
            { id: 'boards-overview', label: t('docs.boards.overview') },
            { id: 'boards-crud', label: t('docs.boards.boardsCrud') },
            { id: 'columns-crud', label: t('docs.boards.columnsCrud') },
            { id: 'cards-crud', label: t('docs.boards.cardsCrud') },
            { id: 'cards-move', label: t('docs.boards.movingCards') },
        ];
    } else if (currentTab.value === 'automation') {
        return [
            { id: 'automation-overview', label: t('docs.automationApi.howItWorks') },
            { id: 'create-post', label: t('docs.automationApi.createPost.title') },
            { id: 'webhook-reference', label: t('docs.automationApi.webhookReference') },
            { id: 'automation-endpoints', label: 'Endpoints' },
            { id: 'n8n-integration', label: t('docs.automationApi.n8nExample.title') },
            { id: 'automation-curl', label: t('docs.automationApi.curlExamples') },
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
                <!-- ===== GENERAL TAB ===== -->
                <template v-if="currentTab === 'general'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.general.title') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.general.description') }}</p>

                    <!-- ========== ABOUT ========== -->
                    <h2 id="about" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.about') }}</h2>

                    <!-- Features -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-8">
                        <h3 class="font-semibold text-blue-900 mb-4">{{ t('docs.general.features.title') }}</h3>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">1</span>
                                <span class="text-blue-800">{{ t('docs.general.features.database') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">2</span>
                                <span class="text-blue-800">{{ t('docs.general.features.templates') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">3</span>
                                <span class="text-blue-800">{{ t('docs.general.features.posts') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">4</span>
                                <span class="text-blue-800">{{ t('docs.general.features.calendar') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">5</span>
                                <span class="text-blue-800">{{ t('docs.general.features.ai') }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- ========== API INTRO ========== -->
                    <h2 id="api-intro" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.apiIntro.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.general.apiIntro.description') }}</p>

                    <!-- Base URL -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-2">{{ t('docs.general.apiIntro.baseUrl') }}</h4>
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.apiIntro.baseUrlDesc') }}</p>
                        <DocsCodeBlock language="text" :code="baseUrl" />
                        <p class="text-gray-500 text-sm mt-2">{{ t('docs.general.apiIntro.versioning') }}</p>
                    </div>

                    <!-- Content Type -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-2">{{ t('docs.general.apiIntro.contentType') }}</h4>
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.apiIntro.contentTypeDesc') }}</p>
                        <DocsCodeBlock language="text" :code="`Content-Type: application/json
Accept: application/json`" />
                    </div>

                    <!-- Response Format -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-2">{{ t('docs.general.apiIntro.responseFormat') }}</h4>
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.apiIntro.responseFormatDesc') }}</p>
                        <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Example&quot;,
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
                    </div>

                    <!-- ========== AUTHENTICATION ========== -->
                    <h2 id="authentication" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.authSection.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.general.authSection.description') }}</p>

                    <!-- Getting Token -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">{{ t('docs.general.authSection.getToken') }}</h4>
                        <ol class="list-decimal list-inside text-gray-600 space-y-2 ml-2">
                            <li>{{ t('docs.general.authSection.getTokenSteps.step1') }}</li>
                            <li>{{ t('docs.general.authSection.getTokenSteps.step2') }}</li>
                            <li>{{ t('docs.general.authSection.getTokenSteps.step3') }}</li>
                            <li>{{ t('docs.general.authSection.getTokenSteps.step4') }}</li>
                        </ol>
                    </div>

                    <!-- Using Token -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-2">{{ t('docs.general.authSection.usingToken') }}</h4>
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.authSection.usingTokenDesc') }}</p>
                        <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/bases' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Accept: application/json'`" />
                    </div>

                    <!-- Security Tips -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <h4 class="font-medium text-yellow-800 mb-3">{{ t('docs.general.authSection.security') }}</h4>
                        <ul class="space-y-2 text-yellow-700 text-sm">
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ t('docs.general.authSection.securityTips.tip1') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ t('docs.general.authSection.securityTips.tip2') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ t('docs.general.authSection.securityTips.tip3') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ t('docs.general.authSection.securityTips.tip4') }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- ========== QUICK START ========== -->
                    <h2 id="quick-start" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.quickStartSection.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.general.quickStartSection.description') }}</p>

                    <div class="space-y-6">
                        <!-- Step 1 -->
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">1</span>
                                <h4 class="font-medium text-gray-900">{{ t('docs.general.quickStartSection.step1Title') }}</h4>
                            </div>
                            <p class="text-gray-600 text-sm ml-11">{{ t('docs.general.quickStartSection.step1Desc') }}</p>
                        </div>

                        <!-- Step 2 -->
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</span>
                                <h4 class="font-medium text-gray-900">{{ t('docs.general.quickStartSection.step2Title') }}</h4>
                            </div>
                            <p class="text-gray-600 text-sm ml-11 mb-3">{{ t('docs.general.quickStartSection.step2Desc') }}</p>
                            <div class="ml-11">
                                <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/bases' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Accept: application/json'`" />
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</span>
                                <h4 class="font-medium text-gray-900">{{ t('docs.general.quickStartSection.step3Title') }}</h4>
                            </div>
                            <p class="text-gray-600 text-sm ml-11">{{ t('docs.general.quickStartSection.step3Desc') }}</p>
                        </div>
                    </div>

                    <!-- ========== ERRORS ========== -->
                    <h2 id="errors" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.errors.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.general.errors.description') }}</p>

                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 pr-4 font-medium text-gray-900">Code</th>
                                    <th class="text-left py-2 font-medium text-gray-900">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr><td class="py-2 pr-4"><code class="text-green-600">200</code></td><td class="py-2">{{ t('docs.general.errors.codes.200') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-green-600">201</code></td><td class="py-2">{{ t('docs.general.errors.codes.201') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-green-600">204</code></td><td class="py-2">{{ t('docs.general.errors.codes.204') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-yellow-600">400</code></td><td class="py-2">{{ t('docs.general.errors.codes.400') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-red-600">401</code></td><td class="py-2">{{ t('docs.general.errors.codes.401') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-red-600">403</code></td><td class="py-2">{{ t('docs.general.errors.codes.403') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-red-600">404</code></td><td class="py-2">{{ t('docs.general.errors.codes.404') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-red-600">422</code></td><td class="py-2">{{ t('docs.general.errors.codes.422') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-orange-600">429</code></td><td class="py-2">{{ t('docs.general.errors.codes.429') }}</td></tr>
                                <tr><td class="py-2 pr-4"><code class="text-red-600">500</code></td><td class="py-2">{{ t('docs.general.errors.codes.500') }}</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-6">
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.errors.format') }}</p>
                        <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;field_name&quot;: [
      &quot;The field name is required.&quot;
    ]
  }
}`" />
                    </div>

                    <!-- ========== RATE LIMITS ========== -->
                    <h2 id="rate-limits" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.general.rateLimit.title') }}</h2>
                    <p class="text-gray-600 mb-4">{{ t('docs.general.rateLimit.description') }}</p>

                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <p class="text-gray-700 font-medium">{{ t('docs.general.rateLimit.limit') }}</p>
                    </div>

                    <div class="mb-6">
                        <p class="text-gray-600 text-sm mb-2">{{ t('docs.general.rateLimit.headers') }}</p>
                        <DocsCodeBlock language="text" :code="`X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1705764000`" />
                    </div>

                    <p class="text-gray-600 text-sm">{{ t('docs.general.rateLimit.exceeded') }}</p>
                </template>

                <!-- ===== DATABASE TAB ===== -->
                <template v-else-if="currentTab === 'database'">
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">table_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Table ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Table name</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Table description</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Table Name&quot;,
  &quot;description&quot;: &quot;Updated description&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;tbl_abc123&quot;,
    &quot;name&quot;: &quot;Updated Table Name&quot;,
    &quot;description&quot;: &quot;Updated description&quot;,
    &quot;fields_count&quot;: 5,
    &quot;rows_count&quot;: 42,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;fld_status&quot;,
    &quot;table_id&quot;: &quot;tbl_abc123&quot;,
    &quot;name&quot;: &quot;Status&quot;,
    &quot;type&quot;: &quot;select&quot;,
    &quot;options&quot;: {
      &quot;choices&quot;: [
        { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;New&quot;, &quot;color&quot;: &quot;#3B82F6&quot; },
        { &quot;id&quot;: &quot;opt_2&quot;, &quot;name&quot;: &quot;In Progress&quot;, &quot;color&quot;: &quot;#F59E0B&quot; },
        { &quot;id&quot;: &quot;opt_3&quot;, &quot;name&quot;: &quot;Done&quot;, &quot;color&quot;: &quot;#10B981&quot; }
      ]
    },
    &quot;is_primary&quot;: false,
    &quot;position&quot;: 3
  }
}`" />
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
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.fields.attributes.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">options</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.fields.attributes.options') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Field Name&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;fld_status&quot;,
    &quot;table_id&quot;: &quot;tbl_abc123&quot;,
    &quot;name&quot;: &quot;Updated Field Name&quot;,
    &quot;type&quot;: &quot;select&quot;,
    &quot;options&quot;: {
      &quot;choices&quot;: [
        { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;New&quot;, &quot;color&quot;: &quot;#3B82F6&quot; }
      ]
    },
    &quot;is_primary&quot;: false,
    &quot;position&quot;: 3
  }
}`" />
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">table_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Table ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">cells</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Object with field_id: value pairs</td></tr>
                            </table>
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;row_def456&quot;,
    &quot;table_id&quot;: &quot;tbl_abc123&quot;,
    &quot;cells&quot;: {
      &quot;fld_name&quot;: &quot;Jane Smith&quot;,
      &quot;fld_email&quot;: &quot;jane@example.com&quot;,
      &quot;fld_status&quot;: { &quot;id&quot;: &quot;opt_1&quot;, &quot;name&quot;: &quot;Active&quot;, &quot;color&quot;: &quot;#10B981&quot; }
    },
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">row_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Row ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">cells</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Object with field_id: value pairs to update</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;cells&quot;: {
    &quot;fld_status&quot;: &quot;opt_2&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;row_abc123&quot;,
    &quot;table_id&quot;: &quot;tbl_abc123&quot;,
    &quot;cells&quot;: {
      &quot;fld_name&quot;: &quot;John Doe&quot;,
      &quot;fld_email&quot;: &quot;john@example.com&quot;,
      &quot;fld_status&quot;: { &quot;id&quot;: &quot;opt_2&quot;, &quot;name&quot;: &quot;Done&quot;, &quot;color&quot;: &quot;#10B981&quot; }
    },
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;row_id&quot;: &quot;row_abc123&quot;,
    &quot;field_id&quot;: &quot;fld_name&quot;,
    &quot;value&quot;: &quot;Updated value&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQZ...&quot;,
    &quot;name&quot;: &quot;Instagram Post&quot;,
    &quot;description&quot;: &quot;1080x1080 post template&quot;,
    &quot;canvas_width&quot;: 1080,
    &quot;canvas_height&quot;: 1080,
    &quot;canvas_background_color&quot;: &quot;#ffffff&quot;,
    &quot;thumbnail_url&quot;: null,
    &quot;in_library&quot;: false,
    &quot;layers_count&quot;: 0,
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Template public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">canvas_background_color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.fields.canvasBackgroundColor') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">in_library</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Add to library (boolean)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Template Name&quot;,
  &quot;canvas_background_color&quot;: &quot;#f0f0f0&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Updated Template Name&quot;,
    &quot;canvas_width&quot;: 1080,
    &quot;canvas_height&quot;: 1080,
    &quot;canvas_background_color&quot;: &quot;#f0f0f0&quot;,
    &quot;thumbnail_url&quot;: &quot;https://example.com/thumb.png&quot;,
    &quot;in_library&quot;: true,
    &quot;layers_count&quot;: 5,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQY...&quot;,
    &quot;name&quot;: &quot;Instagram Post (copy)&quot;,
    &quot;canvas_width&quot;: 1080,
    &quot;canvas_height&quot;: 1080,
    &quot;canvas_background_color&quot;: &quot;#ffffff&quot;,
    &quot;in_library&quot;: false,
    &quot;layers_count&quot;: 5,
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQX...&quot;,
    &quot;name&quot;: &quot;Header Text&quot;,
    &quot;type&quot;: &quot;text&quot;,
    &quot;semantic_tag&quot;: &quot;header&quot;,
    &quot;properties&quot;: {
      &quot;x&quot;: 100, &quot;y&quot;: 200,
      &quot;text&quot;: &quot;Hello World&quot;,
      &quot;fontSize&quot;: 48,
      &quot;fontFamily&quot;: &quot;Inter&quot;,
      &quot;fill&quot;: &quot;#000000&quot;
    },
    &quot;visible&quot;: true,
    &quot;locked&quot;: false,
    &quot;order&quot;: 5
  }
}`" />
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Layer public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.layers.fields.name') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">properties</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.layers.fields.properties') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">semantic_tag</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.templates.layers.fields.semanticTag') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">visible</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Visibility (boolean)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">locked</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Lock state (boolean)</td></tr>
                            </table>
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;name&quot;: &quot;Header Text&quot;,
    &quot;type&quot;: &quot;text&quot;,
    &quot;semantic_tag&quot;: &quot;subtitle&quot;,
    &quot;properties&quot;: {
      &quot;x&quot;: 150, &quot;y&quot;: 250,
      &quot;text&quot;: &quot;Hello World&quot;,
      &quot;fontSize&quot;: 48,
      &quot;fill&quot;: &quot;#FF0000&quot;
    },
    &quot;visible&quot;: true,
    &quot;locked&quot;: false,
    &quot;order&quot;: 0
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQB...&quot;,
    &quot;url&quot;: &quot;https://example.com/generated/image2.png&quot;,
    &quot;width&quot;: 1080,
    &quot;height&quot;: 1080,
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQZ...&quot;,
    &quot;title&quot;: &quot;Weekly Update&quot;,
    &quot;main_caption&quot;: &quot;Check out our latest news and updates!&quot;,
    &quot;status&quot;: &quot;pending_approval&quot;,
    &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;published_at&quot;: null,
    &quot;media_count&quot;: 0,
    &quot;platform_posts&quot;: [
      { &quot;platform&quot;: &quot;facebook&quot;, &quot;status&quot;: &quot;pending&quot; },
      { &quot;platform&quot;: &quot;instagram&quot;, &quot;status&quot;: &quot;pending&quot; }
    ],
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.postsApi.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.postsApi.fields.title') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">main_caption</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.postsApi.fields.mainCaption') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">scheduled_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.postsApi.fields.scheduledAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">status</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>draft, pending_approval</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Updated Title&quot;,
  &quot;main_caption&quot;: &quot;Updated caption content...&quot;,
  &quot;scheduled_at&quot;: &quot;2024-01-25T10:00:00Z&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Updated Title&quot;,
    &quot;main_caption&quot;: &quot;Updated caption content...&quot;,
    &quot;status&quot;: &quot;draft&quot;,
    &quot;scheduled_at&quot;: &quot;2024-01-25T10:00:00Z&quot;,
    &quot;published_at&quot;: null,
    &quot;media_count&quot;: 2,
    &quot;platform_posts&quot;: [
      { &quot;platform&quot;: &quot;facebook&quot;, &quot;status&quot;: &quot;pending&quot; }
    ],
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;title&quot;: &quot;Weekly Update&quot;,
      &quot;main_caption&quot;: &quot;Check out our latest news...&quot;,
      &quot;status&quot;: &quot;approved&quot;,
      &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
      &quot;platforms&quot;: [&quot;facebook&quot;, &quot;instagram&quot;]
    }
  ]
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
      &quot;title&quot;: &quot;Product Launch&quot;,
      &quot;main_caption&quot;: &quot;Exciting new product...&quot;,
      &quot;status&quot;: &quot;pending_approval&quot;,
      &quot;scheduled_at&quot;: &quot;2024-01-22T12:00:00Z&quot;,
      &quot;platforms&quot;: [&quot;instagram&quot;]
    }
  ]
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;status&quot;: &quot;draft&quot;,
    &quot;rejection_reason&quot;: &quot;Please update the image quality&quot;,
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Weekly Update&quot;,
    &quot;main_caption&quot;: &quot;Check out our latest news...&quot;,
    &quot;status&quot;: &quot;approved&quot;,
    &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;published_at&quot;: &quot;2024-01-20T15:01:23Z&quot;,
    &quot;media_count&quot;: 1,
    &quot;platform_posts&quot;: [
      {
        &quot;platform&quot;: &quot;facebook&quot;,
        &quot;status&quot;: &quot;published&quot;,
        &quot;external_id&quot;: &quot;123456789&quot;,
        &quot;external_url&quot;: &quot;https://facebook.com/posts/123456789&quot;,
        &quot;published_at&quot;: &quot;2024-01-20T15:01:23Z&quot;
      }
    ],
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Weekly Update&quot;,
    &quot;main_caption&quot;: &quot;Check out our latest news...&quot;,
    &quot;status&quot;: &quot;approved&quot;,
    &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;published_at&quot;: null,
    &quot;media_count&quot;: 1,
    &quot;platform_posts&quot;: [
      {
        &quot;platform&quot;: &quot;instagram&quot;,
        &quot;status&quot;: &quot;failed&quot;,
        &quot;error_message&quot;: &quot;Media aspect ratio is not supported&quot;,
        &quot;failed_at&quot;: &quot;2024-01-20T15:01:23Z&quot;
      }
    ],
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
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
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
    &quot;url&quot;: &quot;https://example.com/media/image1.jpg&quot;,
    &quot;type&quot;: &quot;image&quot;,
    &quot;mime_type&quot;: &quot;image/jpeg&quot;,
    &quot;size&quot;: 102400,
    &quot;filename&quot;: &quot;image1.jpg&quot;,
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
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

                <!-- ===== AUTOMATION TAB ===== -->
                <template v-else-if="currentTab === 'automation'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.automationApi.title') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.automationApi.howItWorksDescription') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- ========== HOW IT WORKS ========== -->
                    <h2 id="automation-overview" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.automationApi.howItWorks') }}</h2>

                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-8">
                        <ol class="list-decimal list-inside text-indigo-700 space-y-1 text-sm">
                            <li>{{ t('docs.automationApi.howItWorksSteps.step1') }}</li>
                            <li>{{ t('docs.automationApi.howItWorksSteps.step2') }}</li>
                            <li>{{ t('docs.automationApi.howItWorksSteps.step3') }}</li>
                            <li>{{ t('docs.automationApi.howItWorksSteps.step4') }}</li>
                            <li>{{ t('docs.automationApi.howItWorksSteps.step5') }}</li>
                            <li>{{ t('docs.automationApi.howItWorksSteps.step6') }}</li>
                        </ol>
                    </div>

                    <!-- ========== CREATE POST ========== -->
                    <h2 id="create-post" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.automationApi.createPost.title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.automationApi.createPost.description') }}</p>

                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1 w-40">title</td><td class="text-red-500 w-20">required</td><td>{{ t('docs.automationApi.createPost.titleField') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">main_caption</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.createPost.captionField') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">scheduled_at</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.createPost.scheduledField') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">platforms</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.createPost.platformsField') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-500 text-xs mb-2">{{ t('docs.automationApi.createPost.exampleTitle') }}</p>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;5 AI trends in marketing for 2026&quot;,
  &quot;scheduled_at&quot;: &quot;2026-02-15T10:00:00+00:00&quot;,
  &quot;platforms&quot;: [&quot;facebook&quot;, &quot;instagram&quot;]
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;title&quot;: &quot;5 AI trends in marketing for 2026&quot;,
  &quot;main_caption&quot;: null,
  &quot;status&quot;: &quot;draft&quot;,
  &quot;scheduled_at&quot;: &quot;2026-02-15T10:00:00+00:00&quot;,
  &quot;...&quot;: &quot;...&quot;
}`" />
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-8">
                        <p class="text-amber-800 text-sm">{{ t('docs.automationApi.createPost.note') }}</p>
                    </div>

                    <!-- ========== WEBHOOK REFERENCE ========== -->
                    <h2 id="webhook-reference" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.automationApi.webhookReference') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.automationApi.webhookReferenceDescription') }}</p>

                    <!-- ===== 1. TEXT GENERATION WEBHOOK ===== -->
                    <div class="border-2 border-blue-200 rounded-xl mb-8 overflow-hidden">
                        <div class="bg-blue-50 px-5 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-blue-900 text-lg">{{ t('docs.automationApi.textGenerationWebhook.title') }}</h3>
                                <code class="text-blue-600 text-xs">{{ t('docs.automationApi.textGenerationWebhook.settingKey') }}</code>
                            </div>
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">{{ t('docs.automationApi.webhookTypesTable.fallback') }}: {{ t('docs.automationApi.webhookTypesTable.textGenerationFallback') }}</span>
                        </div>
                        <div class="px-5 py-4 border-t border-blue-200">
                            <p class="text-gray-700 text-sm mb-2">{{ t('docs.automationApi.textGenerationWebhook.description') }}</p>
                            <p class="text-blue-600 text-xs italic">{{ t('docs.automationApi.textGenerationWebhook.fallbackNote') }}</p>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-blue-200 border-t border-blue-200">
                            <!-- INPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-green-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center text-xs font-bold">IN</span>
                                    {{ t('docs.automationApi.receives') }}
                                </h4>
                                <p class="text-gray-500 text-xs mb-2">{{ t('docs.automationApi.keyFields') }}:</p>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded text-blue-600">prompt</code> — {{ t('docs.automationApi.textGenerationWebhook.keyField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-blue-600">title</code> — {{ t('docs.automationApi.textGenerationWebhook.keyField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-blue-600">brand_context</code> — {{ t('docs.automationApi.textGenerationWebhook.keyField3') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;post_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;brand_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
  &quot;brand_name&quot;: &quot;My Brand&quot;,
  &quot;title&quot;: &quot;AI trends in marketing&quot;,
  &quot;main_caption&quot;: &quot;&quot;,
  &quot;image_prompt&quot;: null,
  &quot;status&quot;: &quot;draft&quot;,
  &quot;prompt&quot;: &quot;Write an engaging social media post...&quot;,
  &quot;brand_context&quot;: { &quot;...&quot;: &quot;...&quot; }
}`" />
                            </div>
                            <!-- OUTPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-orange-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs font-bold">OUT</span>
                                    {{ t('docs.automationApi.mustReturn') }}
                                </h4>
                                <ul class="text-sm text-gray-700 space-y-1 mb-3">
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">success</code> — {{ t('docs.automationApi.textGenerationWebhook.returnField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">caption</code> — {{ t('docs.automationApi.textGenerationWebhook.returnField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">title</code> — {{ t('docs.automationApi.textGenerationWebhook.returnField3') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;success&quot;: true,
  &quot;caption&quot;: &quot;Here are the top 5 AI trends...&quot;,
  &quot;title&quot;: &quot;AI Trends in Marketing 2024&quot;
}`" />
                            </div>
                        </div>
                    </div>

                    <!-- ===== 2. IMAGE GENERATION WEBHOOK ===== -->
                    <div class="border-2 border-purple-200 rounded-xl mb-8 overflow-hidden">
                        <div class="bg-purple-50 px-5 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-purple-900 text-lg">{{ t('docs.automationApi.imageGenerationWebhook.title') }}</h3>
                                <code class="text-purple-600 text-xs">{{ t('docs.automationApi.imageGenerationWebhook.settingKey') }}</code>
                            </div>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">{{ t('docs.automationApi.webhookTypesTable.fallback') }}: {{ t('docs.automationApi.webhookTypesTable.imageGenerationFallback') }}</span>
                        </div>
                        <div class="px-5 py-4 border-t border-purple-200">
                            <p class="text-gray-700 text-sm mb-2">{{ t('docs.automationApi.imageGenerationWebhook.description') }}</p>
                            <p class="text-red-600 text-xs italic">{{ t('docs.automationApi.imageGenerationWebhook.fallbackNote') }}</p>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-purple-200 border-t border-purple-200">
                            <!-- INPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-green-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center text-xs font-bold">IN</span>
                                    {{ t('docs.automationApi.receives') }}
                                </h4>
                                <p class="text-gray-500 text-xs mb-2">{{ t('docs.automationApi.keyFields') }}:</p>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded text-purple-600">title</code> — {{ t('docs.automationApi.imageGenerationWebhook.keyField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-purple-600">main_caption</code> — {{ t('docs.automationApi.imageGenerationWebhook.keyField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-purple-600">brand_context</code> — {{ t('docs.automationApi.imageGenerationWebhook.keyField3') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;post_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;title&quot;: &quot;AI trends in marketing&quot;,
  &quot;main_caption&quot;: &quot;Here are the top 5 AI trends...&quot;,
  &quot;image_prompt&quot;: null,
  &quot;status&quot;: &quot;draft&quot;,
  &quot;prompt&quot;: &quot;Create a visual description...&quot;,
  &quot;brand_context&quot;: { &quot;...&quot;: &quot;...&quot; }
}`" />
                            </div>
                            <!-- OUTPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-orange-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs font-bold">OUT</span>
                                    {{ t('docs.automationApi.mustReturn') }}
                                </h4>
                                <ul class="text-sm text-gray-700 space-y-1 mb-3">
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">success</code> — {{ t('docs.automationApi.imageGenerationWebhook.returnField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">image_base64</code> — {{ t('docs.automationApi.imageGenerationWebhook.returnField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">filename</code> — {{ t('docs.automationApi.imageGenerationWebhook.returnField3') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">image_prompt</code> — {{ t('docs.automationApi.imageGenerationWebhook.returnField4') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;success&quot;: true,
  &quot;image_base64&quot;: &quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUg...&quot;,
  &quot;filename&quot;: &quot;ai-marketing-post.png&quot;,
  &quot;image_prompt&quot;: &quot;A futuristic illustration of AI in marketing&quot;
}`" />
                            </div>
                        </div>
                    </div>

                    <!-- ===== 3. PUBLISH WEBHOOK ===== -->
                    <div class="border-2 border-emerald-200 rounded-xl mb-8 overflow-hidden">
                        <div class="bg-emerald-50 px-5 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-emerald-900 text-lg">{{ t('docs.automationApi.publishWebhook.title') }}</h3>
                                <code class="text-emerald-600 text-xs">{{ t('docs.automationApi.publishWebhook.settingKey') }}</code>
                            </div>
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">{{ t('docs.automationApi.webhookTypesTable.fallback') }}: {{ t('docs.automationApi.webhookTypesTable.publishFallback') }}</span>
                        </div>
                        <div class="px-5 py-4 border-t border-emerald-200">
                            <p class="text-gray-700 text-sm mb-2">{{ t('docs.automationApi.publishWebhook.description') }}</p>
                            <p class="text-red-600 text-xs italic">{{ t('docs.automationApi.publishWebhook.fallbackNote') }}</p>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-emerald-200 border-t border-emerald-200">
                            <!-- INPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-green-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center text-xs font-bold">IN</span>
                                    {{ t('docs.automationApi.receives') }}
                                </h4>
                                <p class="text-gray-500 text-xs mb-2">{{ t('docs.automationApi.keyFields') }}:</p>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded text-emerald-600">main_caption</code> — {{ t('docs.automationApi.publishWebhook.keyField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-emerald-600">image_prompt</code> — {{ t('docs.automationApi.publishWebhook.keyField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-emerald-600">scheduled_at</code> — {{ t('docs.automationApi.publishWebhook.keyField3') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-emerald-600">title</code> — {{ t('docs.automationApi.publishWebhook.keyField4') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-emerald-600">brand_context.enabled_platforms</code> — {{ t('docs.automationApi.publishWebhook.keyField5') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;post_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;brand_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
  &quot;brand_name&quot;: &quot;My Brand&quot;,
  &quot;title&quot;: &quot;AI trends in marketing&quot;,
  &quot;main_caption&quot;: &quot;Here are the top 5 AI trends...&quot;,
  &quot;image_prompt&quot;: &quot;A futuristic digital illustration...&quot;,
  &quot;status&quot;: &quot;approved&quot;,
  &quot;scheduled_at&quot;: &quot;2024-01-20T15:00:00+00:00&quot;,
  &quot;brand_context&quot;: {
    &quot;name&quot;: &quot;My Brand&quot;,
    &quot;enabled_platforms&quot;: [&quot;facebook&quot;, &quot;instagram&quot;],
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
                            </div>
                            <!-- OUTPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-orange-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs font-bold">OUT</span>
                                    {{ t('docs.automationApi.mustReturn') }}
                                </h4>
                                <ul class="text-sm text-gray-700 space-y-1 mb-3">
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">success</code> — {{ t('docs.automationApi.publishWebhook.returnField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-orange-600">message</code> — {{ t('docs.automationApi.publishWebhook.returnField2') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;success&quot;: true,
  &quot;message&quot;: &quot;Published to Facebook and Instagram&quot;
}`" />
                            </div>
                        </div>
                    </div>

                    <!-- ===== 4. ON APPROVE WEBHOOK ===== -->
                    <div class="border-2 border-amber-200 rounded-xl mb-8 overflow-hidden">
                        <div class="bg-amber-50 px-5 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-amber-900 text-lg">{{ t('docs.automationApi.onApproveWebhook.title') }}</h3>
                                <code class="text-amber-600 text-xs">{{ t('docs.automationApi.onApproveWebhook.settingKey') }}</code>
                            </div>
                            <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-medium">{{ t('docs.automationApi.webhookTypesTable.fallback') }}: {{ t('docs.automationApi.webhookTypesTable.onApproveFallback') }}</span>
                        </div>
                        <div class="px-5 py-4 border-t border-amber-200">
                            <p class="text-gray-700 text-sm mb-2">{{ t('docs.automationApi.onApproveWebhook.description') }}</p>
                            <p class="text-amber-600 text-xs italic">{{ t('docs.automationApi.onApproveWebhook.fallbackNote') }}</p>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-amber-200 border-t border-amber-200">
                            <!-- INPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-green-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center text-xs font-bold">IN</span>
                                    {{ t('docs.automationApi.receives') }}
                                </h4>
                                <p class="text-gray-500 text-xs mb-2">{{ t('docs.automationApi.keyFields') }}:</p>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded text-amber-600">status</code> — {{ t('docs.automationApi.onApproveWebhook.keyField1') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-amber-600">main_caption</code> — {{ t('docs.automationApi.onApproveWebhook.keyField2') }}</li>
                                    <li><code class="bg-gray-100 px-1 rounded text-amber-600">brand_context</code> — {{ t('docs.automationApi.onApproveWebhook.keyField3') }}</li>
                                </ul>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;post_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;title&quot;: &quot;AI trends in marketing&quot;,
  &quot;main_caption&quot;: &quot;Here are the top 5 AI trends...&quot;,
  &quot;status&quot;: &quot;approved&quot;,
  &quot;brand_context&quot;: { &quot;...&quot;: &quot;...&quot; }
}`" />
                            </div>
                            <!-- OUTPUT -->
                            <div class="px-5 py-4">
                                <h4 class="font-semibold text-orange-700 mb-3 flex items-center gap-2">
                                    <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs font-bold">OUT</span>
                                    {{ t('docs.automationApi.mustReturn') }}
                                </h4>
                                <p class="text-sm text-gray-600 mb-3">{{ t('docs.automationApi.onApproveWebhook.returnNote') }}</p>
                                <DocsCodeBlock language="json" class="mt-3" :code="`{
  &quot;success&quot;: true
}`" />
                            </div>
                        </div>
                    </div>

                    <!-- Configure Webhook Settings -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/brands/{brand_id}/automation/settings</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.automationApi.configureSettings') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;webhooks&quot;: {
    &quot;text_generation_url&quot;: &quot;https://your-n8n.com/webhook/generate-text&quot;,
    &quot;text_generation_prompt&quot;: &quot;Generate a social media post about the given topic&quot;,
    &quot;image_generation_url&quot;: &quot;https://your-n8n.com/webhook/generate-image&quot;,
    &quot;image_generation_prompt&quot;: &quot;Create an image description for the post&quot;,
    &quot;publish_url&quot;: &quot;https://your-n8n.com/webhook/publish&quot;,
    &quot;on_approve_url&quot;: &quot;https://your-n8n.com/webhook/on-approve&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- ========== ENDPOINTS ========== -->
                    <h2 id="automation-endpoints" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">Endpoints</h2>

                    <!-- Generate Text -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/generate-text</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">
                                {{ t('docs.automationApi.generateText.description') }}
                            </p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body (optional)</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1 w-32">prompt</td><td class="text-gray-400 w-20">optional</td><td>{{ t('docs.automationApi.generateText.promptParam') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;prompt&quot;: &quot;Write a LinkedIn post about AI trends in marketing&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;success&quot;: true,
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;AI Trends in Marketing 2024&quot;,
    &quot;main_caption&quot;: &quot;Here are the top 5 AI trends...&quot;,
    &quot;image_prompt&quot;: null,
    &quot;status&quot;: &quot;draft&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Generate Image Prompt -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/generate-image-prompt</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">
                                {{ t('docs.automationApi.generateImagePrompt.description') }}
                            </p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;success&quot;: true,
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;AI Trends&quot;,
    &quot;main_caption&quot;: &quot;Here are the top 5...&quot;,
    &quot;image_prompt&quot;: &quot;A futuristic digital illustration showing AI brain...&quot;,
    &quot;status&quot;: &quot;draft&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Webhook Publish -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/{id}/webhook-publish</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">
                                {{ t('docs.automationApi.webhookPublish.description') }}
                            </p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;success&quot;: true,
  &quot;message&quot;: &quot;Post sent for publishing&quot;,
  &quot;data&quot;: { &quot;...&quot;: &quot;full post object&quot; }
}`" />
                        </div>
                    </div>

                    <!-- Bulk Generate Text -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/bulk-generate-text</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.automationApi.bulkGenerateText') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;post_ids&quot;: [&quot;01HQ7X5GNPQ8...&quot;, &quot;01HQ7X5GNPQ9...&quot;]
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;success&quot;: 2,
  &quot;failed&quot;: 0,
  &quot;errors&quot;: []
}`" />
                        </div>
                    </div>

                    <!-- Bulk Generate Image Prompt -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/posts/bulk-generate-image-prompt</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.automationApi.bulkGenerateImagePrompt') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;post_ids&quot;: [&quot;01HQ7X5GNPQ8...&quot;, &quot;01HQ7X5GNPQ9...&quot;]
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;success&quot;: 1,
  &quot;failed&quot;: 1,
  &quot;errors&quot;: [
    {
      &quot;post_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
      &quot;error&quot;: &quot;No image generation webhook configured&quot;
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- Automation List -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/posts/automation</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.automationApi.automationList') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1 w-32">brand_id</td><td class="text-gray-400 w-20">optional</td><td>{{ t('docs.automationApi.queryParams.brandId') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">status</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.queryParams.status') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">search</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.queryParams.search') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.queryParams.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">optional</td><td>{{ t('docs.automationApi.queryParams.perPage') }}</td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- ========== N8N INTEGRATION ========== -->
                    <h2 id="n8n-integration" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.automationApi.n8nExample.title') }}</h2>

                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-8">
                        <p class="text-emerald-700 text-sm mb-3">{{ t('docs.automationApi.n8nExample.description') }}</p>
                        <ol class="list-decimal list-inside text-emerald-700 space-y-2 text-sm">
                            <li>{{ t('docs.automationApi.n8nExample.step1') }}</li>
                            <li>{{ t('docs.automationApi.n8nExample.step2') }}</li>
                            <li>{{ t('docs.automationApi.n8nExample.step3') }}</li>
                            <li>{{ t('docs.automationApi.n8nExample.step4') }}</li>
                            <li>{{ t('docs.automationApi.n8nExample.step5') }}</li>
                        </ol>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-8">
                        <h4 class="font-medium text-emerald-800 mb-3">{{ t('docs.automationApi.n8nApproveExample.title') }}</h4>
                        <p class="text-emerald-700 text-sm mb-3">{{ t('docs.automationApi.n8nApproveExample.description') }}</p>
                        <ol class="list-decimal list-inside text-emerald-700 space-y-2 text-sm">
                            <li>{{ t('docs.automationApi.n8nApproveExample.step1') }}</li>
                            <li>{{ t('docs.automationApi.n8nApproveExample.step2') }}</li>
                            <li>{{ t('docs.automationApi.n8nApproveExample.step3') }}</li>
                            <li>{{ t('docs.automationApi.n8nApproveExample.step4') }}</li>
                        </ol>
                        <p class="text-emerald-600 text-xs mt-3">{{ t('docs.automationApi.n8nApproveExample.note') }}</p>
                    </div>

                    <!-- ========== CURL EXAMPLES ========== -->
                    <h2 id="automation-curl" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.automationApi.curlExamples') }}</h2>

                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <p class="text-gray-500 text-xs mb-3">{{ t('docs.automationApi.curlGenerateText') }}</p>
                        <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/posts/{post_id}/generate-text' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Content-Type: application/json' \\
  -d '{&quot;prompt&quot;: &quot;Write an engaging post about summer sales&quot;}'`" />
                        <p class="text-gray-500 text-xs mb-3 mt-4">{{ t('docs.automationApi.curlGenerateImage') }}</p>
                        <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/posts/{post_id}/generate-image-prompt' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />
                        <p class="text-gray-500 text-xs mb-3 mt-4">{{ t('docs.automationApi.curlBulkText') }}</p>
                        <DocsCodeBlock language="bash" :code="`curl -X POST '${baseUrl}/posts/bulk-generate-text' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Content-Type: application/json' \\
  -d '{&quot;post_ids&quot;: [&quot;POST_ID_1&quot;, &quot;POST_ID_2&quot;]}'`" />
                        <p class="text-gray-500 text-xs mb-3 mt-4">{{ t('docs.automationApi.curlConfigureWebhooks') }}</p>
                        <DocsCodeBlock language="bash" :code="`curl -X PUT '${baseUrl}/brands/{brand_id}/automation/settings' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN' \\
  -H 'Content-Type: application/json' \\
  -d '{&quot;webhooks&quot;: {&quot;text_generation_url&quot;: &quot;https://n8n.example.com/webhook/abc123&quot;}}'`" />
                    </div>

                </template>

                <!-- ===== CALENDAR TAB ===== -->
                <template v-else-if="currentTab === 'calendar'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.calendar.title') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.calendar.description') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- Event Types Reference -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-8">
                        <h4 class="font-medium text-purple-800 mb-3">{{ t('docs.calendar.eventTypes.title') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <div><code class="bg-white px-2 py-1 rounded">meeting</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">birthday</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">reminder</code></div>
                            <div><code class="bg-white px-2 py-1 rounded">other</code></div>
                        </div>
                    </div>

                    <!-- ========== EVENTS OVERVIEW ========== -->
                    <h2 id="events-overview" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.calendar.overview') }}</h2>
                    <p class="text-gray-600 mb-6">{{ t('docs.calendar.objectDescription') }}</p>

                    <!-- Event Object -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <h4 class="font-medium mb-3">{{ t('docs.calendar.object') }}</h4>
                        <DocsCodeBlock language="json" :code="`{
  &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;title&quot;: &quot;Team Meeting&quot;,
  &quot;description&quot;: &quot;Weekly sync with the team&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;,
  &quot;event_type&quot;: &quot;meeting&quot;,
  &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
  &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
  &quot;all_day&quot;: false,
  &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
}`" />
                    </div>

                    <table class="w-full text-sm mb-8">
                        <tr><td class="font-mono text-blue-600 py-1">id</td><td>{{ t('docs.calendar.fields.id') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">title</td><td>{{ t('docs.calendar.fields.title') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">description</td><td>{{ t('docs.calendar.fields.description') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">color</td><td>{{ t('docs.calendar.fields.color') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">event_type</td><td>{{ t('docs.calendar.fields.eventType') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">starts_at</td><td>{{ t('docs.calendar.fields.startsAt') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">ends_at</td><td>{{ t('docs.calendar.fields.endsAt') }}</td></tr>
                        <tr><td class="font-mono text-blue-600 py-1">all_day</td><td>{{ t('docs.calendar.fields.allDay') }}</td></tr>
                    </table>

                    <!-- ========== EVENTS CRUD ========== -->
                    <h2 id="events-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.calendar.eventsCrud') }}</h2>

                    <!-- List Events -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/events</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.listDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.page') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">per_page</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.paginationSection.parameters.perPage') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">event_type</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Filter by type (meeting, birthday, reminder, other)</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;title&quot;: &quot;Team Meeting&quot;,
      &quot;description&quot;: &quot;Weekly sync&quot;,
      &quot;color&quot;: &quot;#3B82F6&quot;,
      &quot;event_type&quot;: &quot;meeting&quot;,
      &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
      &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
      &quot;all_day&quot;: false,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: { &quot;current_page&quot;: 1, &quot;per_page&quot;: 50, &quot;total&quot;: 10 }
}`" />
                        </div>
                    </div>

                    <!-- Get Event -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/events/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.getDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Team Meeting&quot;,
    &quot;description&quot;: &quot;Weekly sync with the team&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;event_type&quot;: &quot;meeting&quot;,
    &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
    &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
    &quot;all_day&quot;: false,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 404</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;Event not found.&quot;
}`" />
                        </div>
                    </div>

                    <!-- Create Event -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/events</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.title') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">starts_at</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.startsAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.color') }} (default: #3B82F6)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">event_type</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.eventType') }} (default: meeting)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">ends_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.endsAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">all_day</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.allDay') }} (default: false)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Team Meeting&quot;,
  &quot;description&quot;: &quot;Weekly sync with the team&quot;,
  &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
  &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;,
  &quot;event_type&quot;: &quot;meeting&quot;,
  &quot;all_day&quot;: false
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Team Meeting&quot;,
    &quot;description&quot;: &quot;Weekly sync with the team&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;event_type&quot;: &quot;meeting&quot;,
    &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
    &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
    &quot;all_day&quot;: false,
    &quot;created_at&quot;: &quot;2024-01-20T09:00:00Z&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 422</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;The given data was invalid.&quot;,
  &quot;errors&quot;: {
    &quot;title&quot;: [&quot;The title field is required.&quot;],
    &quot;starts_at&quot;: [&quot;The starts at field is required.&quot;]
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Event -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/events/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.title') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.description') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.color') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">event_type</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.eventType') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">starts_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.startsAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">ends_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.endsAt') }}</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">all_day</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>{{ t('docs.calendar.fields.allDay') }}</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Updated Meeting Title&quot;,
  &quot;color&quot;: &quot;#10B981&quot;,
  &quot;starts_at&quot;: &quot;2024-01-20T14:00:00Z&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Updated Meeting Title&quot;,
    &quot;description&quot;: &quot;Weekly sync with the team&quot;,
    &quot;color&quot;: &quot;#10B981&quot;,
    &quot;event_type&quot;: &quot;meeting&quot;,
    &quot;starts_at&quot;: &quot;2024-01-20T14:00:00Z&quot;,
    &quot;ends_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;all_day&quot;: false,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Event -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/events/{id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== CALENDAR VIEW ========== -->
                    <h2 id="events-calendar" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.calendar.calendarView') }}</h2>

                    <!-- Calendar View -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/events/calendar</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.calendarDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Query Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">start</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Start date (YYYY-MM-DD)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">end</td><td class="text-red-500">{{ t('docs.required') }}</td><td>End date (YYYY-MM-DD)</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Example Request</h4>
                            <DocsCodeBlock language="bash" :code="`curl -X GET '${baseUrl}/events/calendar?start=2024-01-01&end=2024-01-31' \\
  -H 'Authorization: Bearer YOUR_API_TOKEN'`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: [
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;title&quot;: &quot;Team Meeting&quot;,
      &quot;color&quot;: &quot;#3B82F6&quot;,
      &quot;event_type&quot;: &quot;meeting&quot;,
      &quot;starts_at&quot;: &quot;2024-01-20T10:00:00Z&quot;,
      &quot;ends_at&quot;: &quot;2024-01-20T11:00:00Z&quot;,
      &quot;all_day&quot;: false
    },
    {
      &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
      &quot;title&quot;: &quot;John's Birthday&quot;,
      &quot;color&quot;: &quot;#F59E0B&quot;,
      &quot;event_type&quot;: &quot;birthday&quot;,
      &quot;starts_at&quot;: &quot;2024-01-25T00:00:00Z&quot;,
      &quot;ends_at&quot;: null,
      &quot;all_day&quot;: true
    }
  ]
}`" />
                        </div>
                    </div>

                    <!-- ========== RESCHEDULE ========== -->
                    <h2 id="events-reschedule" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.calendar.reschedule') }}</h2>

                    <!-- Reschedule Event -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/events/{id}/reschedule</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.calendar.endpoints.rescheduleDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>{{ t('docs.calendar.fields.id') }}</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">starts_at</td><td class="text-red-500">{{ t('docs.required') }}</td><td>New start date/time</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">ends_at</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>New end date/time</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;starts_at&quot;: &quot;2024-01-22T10:00:00Z&quot;,
  &quot;ends_at&quot;: &quot;2024-01-22T11:00:00Z&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;title&quot;: &quot;Team Meeting&quot;,
    &quot;starts_at&quot;: &quot;2024-01-22T10:00:00Z&quot;,
    &quot;ends_at&quot;: &quot;2024-01-22T11:00:00Z&quot;,
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Usage Tip -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-8">
                        <h4 class="font-medium text-green-800 mb-2">Drag & Drop Integration</h4>
                        <p class="text-green-700 text-sm">
                            The reschedule endpoint is optimized for drag & drop calendar interactions.
                            When a user drags an event to a new date/time, call this endpoint to update the event's schedule without modifying other properties.
                        </p>
                    </div>
                </template>

                <!-- ===== BOARDS TAB ===== -->
                <template v-else-if="currentTab === 'boards'">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ t('docs.boards.title') }}</h1>
                    <p class="text-lg text-gray-600 mb-8">{{ t('docs.boards.description') }}</p>

                    <!-- Authentication info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                        <h3 class="font-medium text-blue-800 mb-2">{{ t('docs.authentication') }}</h3>
                        <p class="text-blue-700 text-sm mb-2">{{ t('docs.auth.usingTokenDescription') }}</p>
                        <code class="bg-blue-100 px-2 py-1 rounded text-xs">Authorization: Bearer YOUR_API_TOKEN</code>
                    </div>

                    <!-- ========== OVERVIEW ========== -->
                    <h2 id="boards-overview" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.boards.overview') }}</h2>
                    <p class="text-gray-600 mb-4">{{ t('docs.boards.overviewDescription') }}</p>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                        <h4 class="font-medium text-gray-800 mb-3">{{ t('docs.boards.structure') }}</h4>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><strong>Board</strong> - {{ t('docs.boards.boardDesc') }}</li>
                            <li class="pl-4"><strong>Column</strong> - {{ t('docs.boards.columnDesc') }}</li>
                            <li class="pl-8"><strong>Card</strong> - {{ t('docs.boards.cardDesc') }}</li>
                        </ul>
                    </div>

                    <!-- Board Object -->
                    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                        <h4 class="font-medium mb-3">Board Object</h4>
                        <DocsCodeBlock language="json" :code="`{
  &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
  &quot;name&quot;: &quot;Content Pipeline&quot;,
  &quot;description&quot;: &quot;Track content from idea to publication&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;,
  &quot;settings&quot;: null,
  &quot;columns_count&quot;: 3,
  &quot;cards_count&quot;: 12,
  &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
  &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
}`" />
                    </div>

                    <!-- ========== BOARDS CRUD ========== -->
                    <h2 id="boards-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.boards.boardsCrud') }}</h2>

                    <!-- List Boards -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/boards</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.listDescription') }}</p>
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
      &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
      &quot;name&quot;: &quot;Content Pipeline&quot;,
      &quot;description&quot;: &quot;Track content from idea to publication&quot;,
      &quot;color&quot;: &quot;#3B82F6&quot;,
      &quot;settings&quot;: null,
      &quot;columns_count&quot;: 3,
      &quot;cards_count&quot;: 12,
      &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
      &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
    }
  ],
  &quot;meta&quot;: {
    &quot;current_page&quot;: 1,
    &quot;per_page&quot;: 50,
    &quot;total&quot;: 5
  }
}`" />
                        </div>
                    </div>

                    <!-- Get Board -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono font-bold">GET</span>
                            <code class="text-sm">/api/v1/boards/{board_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.showDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">board_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Board public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Content Pipeline&quot;,
    &quot;description&quot;: &quot;Track content from idea to publication&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;settings&quot;: null,
    &quot;columns_count&quot;: 3,
    &quot;cards_count&quot;: 12,
    &quot;columns&quot;: [
      {
        &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
        &quot;board_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
        &quot;name&quot;: &quot;To Do&quot;,
        &quot;color&quot;: &quot;#6B7280&quot;,
        &quot;position&quot;: 0,
        &quot;card_limit&quot;: null,
        &quot;cards&quot;: [
          {
            &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
            &quot;column_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
            &quot;title&quot;: &quot;Write blog post&quot;,
            &quot;description&quot;: &quot;Draft about new features&quot;,
            &quot;position&quot;: 0,
            &quot;color&quot;: null,
            &quot;due_date&quot;: &quot;2024-02-01&quot;,
            &quot;is_overdue&quot;: false,
            &quot;labels&quot;: [&quot;content&quot;, &quot;blog&quot;],
            &quot;created_by&quot;: { &quot;id&quot;: 1, &quot;name&quot;: &quot;John Doe&quot; },
            &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
            &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
          }
        ]
      }
    ],
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
  }
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-red-700">Error 404</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;message&quot;: &quot;Board not found.&quot;
}`" />
                        </div>
                    </div>

                    <!-- Create Board -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/boards</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.createDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Board name (max 255 chars)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Board description</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color code (default: #3B82F6)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Content Pipeline&quot;,
  &quot;description&quot;: &quot;Track content from idea to publication&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Content Pipeline&quot;,
    &quot;description&quot;: &quot;Track content from idea to publication&quot;,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;settings&quot;: null,
    &quot;columns_count&quot;: 3,
    &quot;cards_count&quot;: 0,
    &quot;columns&quot;: [
      { &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;, &quot;name&quot;: &quot;To Do&quot;, &quot;position&quot;: 0, &quot;cards&quot;: [] },
      { &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;, &quot;name&quot;: &quot;In Progress&quot;, &quot;position&quot;: 1, &quot;cards&quot;: [] },
      { &quot;id&quot;: &quot;01HQ7X5GNPQB...&quot;, &quot;name&quot;: &quot;Done&quot;, &quot;position&quot;: 2, &quot;cards&quot;: [] }
    ],
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

                    <!-- Update Board -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/boards/{board_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.updateDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">board_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Board public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Board name</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Board description</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color code</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">settings</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Board settings (JSON object)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Updated Pipeline&quot;,
  &quot;color&quot;: &quot;#10B981&quot;
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Updated Pipeline&quot;,
    &quot;description&quot;: &quot;Track content from idea to publication&quot;,
    &quot;color&quot;: &quot;#10B981&quot;,
    &quot;settings&quot;: null,
    &quot;columns_count&quot;: 3,
    &quot;cards_count&quot;: 12,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Board -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/boards/{board_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.deleteDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">board_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Board public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== COLUMNS CRUD ========== -->
                    <h2 id="columns-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.boards.columnsCrud') }}</h2>

                    <!-- Create Column -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/boards/{board_id}/columns</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.createColumnDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">board_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Board public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Column name (max 255 chars)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color code (default: #6B7280)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">card_limit</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>WIP limit (integer, min: 1)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;In Review&quot;,
  &quot;color&quot;: &quot;#F59E0B&quot;,
  &quot;card_limit&quot;: 5
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQC...&quot;,
    &quot;board_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;In Review&quot;,
    &quot;color&quot;: &quot;#F59E0B&quot;,
    &quot;position&quot;: 3,
    &quot;card_limit&quot;: 5,
    &quot;cards_count&quot;: 0,
    &quot;cards&quot;: [],
    &quot;created_at&quot;: &quot;2024-01-20T15:00:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Column -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/columns/{column_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.updateColumnDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">column_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Column public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">name</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Column name</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color code</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">card_limit</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>WIP limit (null to remove)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;name&quot;: &quot;Done&quot;,
  &quot;card_limit&quot;: 10
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;board_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;Done&quot;,
    &quot;color&quot;: &quot;#6B7280&quot;,
    &quot;position&quot;: 0,
    &quot;card_limit&quot;: 10,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Column -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/columns/{column_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.deleteColumnDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">column_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Column public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- Reorder Column -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/columns/{column_id}/reorder</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.reorderColumnDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">column_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Column public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">position</td><td class="text-red-500">{{ t('docs.required') }}</td><td>New position index (integer, min: 0)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;position&quot;: 2
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;board_id&quot;: &quot;01HQ7X5GNPQ8...&quot;,
    &quot;name&quot;: &quot;To Do&quot;,
    &quot;color&quot;: &quot;#6B7280&quot;,
    &quot;position&quot;: 2,
    &quot;card_limit&quot;: null,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- ========== CARDS CRUD ========== -->
                    <h2 id="cards-crud" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.boards.cardsCrud') }}</h2>

                    <!-- Create Card -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/columns/{column_id}/cards</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.createCardDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">column_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Column public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Card title (max 255 chars)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Card description (text)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color code</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">due_date</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Due date (YYYY-MM-DD)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">labels</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Array of label strings</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Write blog post&quot;,
  &quot;description&quot;: &quot;Draft about new features&quot;,
  &quot;color&quot;: &quot;#3B82F6&quot;,
  &quot;due_date&quot;: &quot;2024-02-01&quot;,
  &quot;labels&quot;: [&quot;content&quot;, &quot;blog&quot;]
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 201</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
    &quot;column_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;title&quot;: &quot;Write blog post&quot;,
    &quot;description&quot;: &quot;Draft about new features&quot;,
    &quot;position&quot;: 0,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;due_date&quot;: &quot;2024-02-01&quot;,
    &quot;is_overdue&quot;: false,
    &quot;labels&quot;: [&quot;content&quot;, &quot;blog&quot;],
    &quot;created_by&quot;: { &quot;id&quot;: 1, &quot;name&quot;: &quot;John Doe&quot; },
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
    &quot;title&quot;: [&quot;The title field is required.&quot;]
  }
}`" />
                        </div>
                    </div>

                    <!-- Update Card -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/cards/{card_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.updateCardDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">card_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Card public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">title</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Card title</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">description</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Card description</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">color</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Hex color (null to remove)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">due_date</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Due date (null to remove)</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">labels</td><td class="text-gray-400">{{ t('docs.optional') }}</td><td>Array of label strings</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;title&quot;: &quot;Updated blog post&quot;,
  &quot;description&quot;: &quot;Added more details&quot;,
  &quot;labels&quot;: [&quot;content&quot;, &quot;blog&quot;, &quot;priority&quot;]
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
    &quot;column_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;title&quot;: &quot;Updated blog post&quot;,
    &quot;description&quot;: &quot;Added more details&quot;,
    &quot;position&quot;: 0,
    &quot;color&quot;: &quot;#3B82F6&quot;,
    &quot;due_date&quot;: &quot;2024-02-01&quot;,
    &quot;is_overdue&quot;: false,
    &quot;labels&quot;: [&quot;content&quot;, &quot;blog&quot;, &quot;priority&quot;],
    &quot;created_by&quot;: { &quot;id&quot;: 1, &quot;name&quot;: &quot;John Doe&quot; },
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Delete Card -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono font-bold">DELETE</span>
                            <code class="text-sm">/api/v1/cards/{card_id}</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.deleteCardDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">card_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Card public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 204</h4>
                            <p class="text-gray-500 text-sm">No content</p>
                        </div>
                    </div>

                    <!-- ========== MOVING CARDS ========== -->
                    <h2 id="cards-move" class="text-2xl font-bold text-gray-900 mt-10 mb-6 pb-2 border-b scroll-mt-8">{{ t('docs.boards.movingCards') }}</h2>
                    <p class="text-gray-600 mb-4">{{ t('docs.boards.moveDescription') }}</p>

                    <!-- Move Card -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-mono font-bold">PUT</span>
                            <code class="text-sm">/api/v1/cards/{card_id}/move</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.moveCardDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">card_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Card public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">column_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Target column public ID</td></tr>
                                <tr><td class="font-mono text-blue-600 py-1">position</td><td class="text-red-500">{{ t('docs.required') }}</td><td>New position index (integer, min: 0)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;column_id&quot;: &quot;01HQ7X5GNPQA...&quot;,
  &quot;position&quot;: 0
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQB...&quot;,
    &quot;column_id&quot;: &quot;01HQ7X5GNPQA...&quot;,
    &quot;title&quot;: &quot;Write blog post&quot;,
    &quot;description&quot;: &quot;Draft about new features&quot;,
    &quot;position&quot;: 0,
    &quot;color&quot;: null,
    &quot;due_date&quot;: &quot;2024-02-01&quot;,
    &quot;is_overdue&quot;: false,
    &quot;labels&quot;: [&quot;content&quot;],
    &quot;created_by&quot;: { &quot;id&quot;: 1, &quot;name&quot;: &quot;John Doe&quot; },
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;updated_at&quot;: &quot;2024-01-20T15:00:00Z&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Reorder Card -->
                    <div class="border rounded-lg mb-6 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center gap-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">POST</span>
                            <code class="text-sm">/api/v1/cards/{card_id}/reorder</code>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <p class="text-gray-600">{{ t('docs.boards.reorderDescription') }}</p>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Path Parameters</h4>
                            <table class="w-full text-sm">
                                <tr><td class="font-mono text-blue-600 py-1">card_id</td><td class="text-red-500">{{ t('docs.required') }}</td><td>Card public ID</td></tr>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2">Request Body</h4>
                            <table class="w-full text-sm mb-3">
                                <tr><td class="font-mono text-blue-600 py-1">position</td><td class="text-red-500">{{ t('docs.required') }}</td><td>New position index (integer, min: 0)</td></tr>
                            </table>
                            <DocsCodeBlock language="json" :code="`{
  &quot;position&quot;: 2
}`" />
                        </div>
                        <div class="px-4 py-3 border-t">
                            <h4 class="font-medium mb-2 text-green-700">Response 200</h4>
                            <DocsCodeBlock language="json" :code="`{
  &quot;data&quot;: {
    &quot;id&quot;: &quot;01HQ7X5GNPQA...&quot;,
    &quot;column_id&quot;: &quot;01HQ7X5GNPQ9...&quot;,
    &quot;title&quot;: &quot;Write blog post&quot;,
    &quot;position&quot;: 2,
    &quot;...&quot;: &quot;...&quot;
  }
}`" />
                        </div>
                    </div>

                    <!-- Drag & Drop Tip -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-8">
                        <h4 class="font-medium text-green-800 mb-2">{{ t('docs.boards.dragDropTip') }}</h4>
                        <p class="text-green-700 text-sm">{{ t('docs.boards.dragDropDescription') }}</p>
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
