<template>
    <div class="automation-panel">
        <!-- Header with toggle -->
        <div class="panel-header">
            <div class="header-content">
                <h3 class="panel-title">{{ $t('automation.title') }}</h3>
                <p class="panel-description">{{ $t('automation.description') }}</p>
            </div>
            <label class="toggle" :class="{ 'is-saving': saving }">
                <input
                    type="checkbox"
                    v-model="localEnabled"
                    :disabled="saving"
                    @change="toggleAutomation"
                />
                <span class="toggle-slider"></span>
                <span class="toggle-label">
                    {{ localEnabled ? $t('automation.enabled') : $t('automation.disabled') }}
                </span>
            </label>
        </div>

        <!-- How it works section (always visible) -->
        <div class="how-it-works">
            <h4 class="section-title">{{ $t('automation.howItWorks') }}</h4>
            <div class="steps-list">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>{{ $t('automation.step1Title') }}</strong>
                        <p>{{ $t('automation.step1Desc') }}</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>{{ $t('automation.step2Title') }}</strong>
                        <p>{{ $t('automation.step2Desc') }}</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>{{ $t('automation.step3Title') }}</strong>
                        <p>{{ $t('automation.step3Desc') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success message after enabling -->
        <Transition name="fade">
            <div v-if="showSuccessMessage" class="success-message">
                <div class="success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="success-content">
                    <strong>{{ $t('automation.enabledSuccess') }}</strong>
                    <p>{{ $t('automation.enabledSuccessDesc') }}</p>
                </div>
            </div>
        </Transition>

        <!-- Loading state -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <span>{{ $t('common.loading') }}</span>
        </div>

        <!-- Stats (only when enabled) -->
        <template v-else-if="localEnabled && stats">
            <!-- Queue stats -->
            <div class="stats-section">
                <h4 class="section-title">{{ $t('automation.queueStats') }}</h4>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-value">{{ stats.queue?.total || 0 }}</span>
                        <span class="stat-label">{{ $t('automation.totalInQueue') }}</span>
                    </div>
                    <div class="stat-card pending">
                        <span class="stat-value">{{ stats.queue?.by_status?.pending || 0 }}</span>
                        <span class="stat-label">{{ $t('automation.pending') }}</span>
                    </div>
                    <div class="stat-card ready">
                        <span class="stat-value">{{ stats.queue?.by_status?.ready || 0 }}</span>
                        <span class="stat-label">{{ $t('automation.ready') }}</span>
                    </div>
                    <div class="stat-card published">
                        <span class="stat-value">{{ stats.queue?.by_status?.published || 0 }}</span>
                        <span class="stat-label">{{ $t('automation.published') }}</span>
                    </div>
                </div>

                <!-- Empty queue hint -->
                <div v-if="(stats.queue?.total || 0) === 0" class="empty-queue-hint">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span>{{ $t('automation.emptyQueueHint') }}</span>
                </div>

                <!-- No pending slots hint -->
                <div v-else-if="(stats.queue?.by_status?.pending || 0) === 0 && (stats.queue?.by_status?.ready || 0) > 0" class="no-pending-hint">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span>{{ $t('automation.noPendingHint') }}</span>
                </div>
            </div>

            <!-- Pillar distribution -->
            <div class="stats-section" v-if="stats.pillar_distribution?.pillars?.length">
                <h4 class="section-title">{{ $t('automation.pillarDistribution') }}</h4>
                <div class="pillar-list">
                    <div
                        v-for="pillar in stats.pillar_distribution.pillars"
                        :key="pillar.pillar_name"
                        class="pillar-item"
                    >
                        <div class="pillar-header">
                            <span class="pillar-name">{{ pillar.pillar_name }}</span>
                            <span class="pillar-counts">
                                {{ pillar.planned_count }} {{ $t('automation.planned') }} / {{ pillar.target_percentage }}% {{ $t('automation.target') }}
                            </span>
                        </div>
                        <div class="pillar-bar">
                            <div
                                class="pillar-progress"
                                :style="{ width: `${Math.min(pillar.actual_percentage, 100)}%` }"
                                :class="{
                                    'under': pillar.delta < -5,
                                    'over': pillar.delta > 5,
                                }"
                            ></div>
                            <div
                                class="pillar-target"
                                :style="{ left: `${pillar.target_percentage}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="stats-section">
                <h4 class="section-title">{{ $t('automation.settings') }}</h4>
                <div class="settings-form">
                    <div class="form-group">
                        <label>{{ $t('automation.queueDays') }}</label>
                        <select v-model="queueDays" @change="updateSettings" :disabled="saving">
                            <option :value="3">3 {{ $t('automation.days') }}</option>
                            <option :value="5">5 {{ $t('automation.days') }}</option>
                            <option :value="7">7 {{ $t('automation.days') }}</option>
                            <option :value="14">14 {{ $t('automation.days') }}</option>
                            <option :value="30">30 {{ $t('automation.days') }}</option>
                        </select>
                        <span class="form-hint">{{ $t('automation.queueDaysHint') }}</span>
                    </div>
                </div>
            </div>

            <!-- Last run info -->
            <div class="stats-section info-section">
                <div class="info-row">
                    <span class="info-label">{{ $t('automation.lastRun') }}:</span>
                    <span class="info-value">
                        {{ stats.last_run ? formatDate(stats.last_run) : $t('automation.never') }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ $t('automation.coverageDays') }}:</span>
                    <span class="info-value">
                        {{ stats.queue?.queue_coverage_days || 0 }} {{ $t('automation.days') }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ $t('automation.nextRun') }}:</span>
                    <span class="info-value">{{ $t('automation.nextRunHint') }}</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-section">
                <button
                    v-if="hasPendingSlots"
                    class="btn btn-primary"
                    @click="triggerNow"
                    :disabled="triggering"
                >
                    <span v-if="triggering" class="spinner-small"></span>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    {{ $t('automation.triggerNow') }}
                </button>
                <button
                    class="btn btn-primary"
                    @click="extendQueue"
                    :disabled="extending"
                >
                    <span v-if="extending" class="spinner-small"></span>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    {{ $t('automation.extendQueue') }}
                </button>
                <button
                    class="btn btn-secondary"
                    @click="refreshStats"
                    :disabled="loading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                        <polyline points="23 4 23 10 17 10"/>
                        <polyline points="1 20 1 14 7 14"/>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                    {{ $t('automation.refresh') }}
                </button>
            </div>

            <!-- Trigger feedback -->
            <Transition name="fade">
                <div v-if="showTriggerMessage" class="trigger-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span>{{ $t('automation.triggerStarted') }}</span>
                </div>
            </Transition>

            <!-- Extend feedback -->
            <Transition name="fade">
                <div v-if="showExtendMessage" class="trigger-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span>{{ $t('automation.extendStarted') }}</span>
                </div>
            </Transition>
        </template>

        <!-- Empty state when disabled -->
        <div v-else-if="!localEnabled" class="empty-state">
            <div class="empty-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
            </div>
            <h4>{{ $t('automation.disabledTitle') }}</h4>
            <p>{{ $t('automation.disabledDescription') }}</p>
            <button class="btn btn-primary mt-4" @click="enableNow" :disabled="saving">
                {{ $t('automation.enableNow') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBrandsStore } from '@/stores/brands';
import { useActiveTasksStore } from '@/stores/activeTasks';

const props = defineProps({
    brandId: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const brandsStore = useBrandsStore();
const activeTasksStore = useActiveTasksStore();

const loading = ref(false);
const saving = ref(false);
const triggering = ref(false);
const extending = ref(false);
const stats = ref(null);
const queueDays = ref(7);
const localEnabled = ref(false);
const showSuccessMessage = ref(false);
const showTriggerMessage = ref(false);
const showExtendMessage = ref(false);

const hasPendingSlots = computed(() => {
    return (stats.value?.queue?.by_status?.pending || 0) > 0;
});

// Sync local state with store
watch(
    () => brandsStore.currentBrand?.automation_enabled,
    (newVal) => {
        localEnabled.value = newVal ?? false;
    },
    { immediate: true }
);

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString();
};

const fetchStats = async () => {
    if (!localEnabled.value) return;

    loading.value = true;
    try {
        stats.value = await brandsStore.fetchAutomationStats(props.brandId);
        queueDays.value = stats.value.queue_days || 7;
    } catch (error) {
        console.error('Failed to fetch automation stats:', error);
    } finally {
        loading.value = false;
    }
};

const toggleAutomation = async () => {
    saving.value = true;
    const wasEnabled = !localEnabled.value; // Value before change

    try {
        if (localEnabled.value) {
            await brandsStore.enableAutomation(props.brandId, {
                content_queue_days: queueDays.value,
            });
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 5000);
            await fetchStats();
        } else {
            await brandsStore.disableAutomation(props.brandId);
            stats.value = null;
        }
    } catch (error) {
        console.error('Failed to toggle automation:', error);
        // Revert local state on error
        localEnabled.value = wasEnabled;
    } finally {
        saving.value = false;
    }
};

const enableNow = async () => {
    localEnabled.value = true;
    await toggleAutomation();
};

const updateSettings = async () => {
    saving.value = true;
    try {
        await brandsStore.updateAutomationSettings(props.brandId, {
            content_queue_days: queueDays.value,
        });
    } catch (error) {
        console.error('Failed to update settings:', error);
    } finally {
        saving.value = false;
    }
};

const triggerNow = async () => {
    triggering.value = true;

    // Add placeholder task for instant feedback (will be replaced by WebSocket tasks)
    const placeholderTaskId = `automation_placeholder_${Date.now()}`;
    activeTasksStore.addTask({
        task_id: placeholderTaskId,
        task_type: 'content_generation',
        data: {
            brand_name: brandsStore.currentBrand?.name || '',
            pillar: t('automation.triggerNow'),
        },
    });

    try {
        await brandsStore.triggerAutomation(props.brandId);
        showTriggerMessage.value = true;
        setTimeout(() => {
            showTriggerMessage.value = false;
        }, 5000);

        // Remove placeholder after short delay - real tasks come via WebSocket
        setTimeout(() => {
            activeTasksStore.removeTask(placeholderTaskId);
        }, 2000);

        // Wait a bit then refresh stats
        setTimeout(fetchStats, 3000);
    } catch (error) {
        console.error('Failed to trigger automation:', error);
        // Mark placeholder task as failed
        activeTasksStore.completeTask(placeholderTaskId, false, error.message || t('automation.triggerError'));
    } finally {
        triggering.value = false;
    }
};

const extendQueue = async () => {
    extending.value = true;

    // Add placeholder task for instant feedback
    const placeholderTaskId = `extend_queue_${Date.now()}`;
    activeTasksStore.addTask({
        task_id: placeholderTaskId,
        task_type: 'content_generation',
        data: {
            brand_name: brandsStore.currentBrand?.name || '',
            pillar: t('automation.extendQueue'),
        },
    });

    try {
        const result = await brandsStore.extendQueue(props.brandId, 7);
        showExtendMessage.value = true;
        setTimeout(() => {
            showExtendMessage.value = false;
        }, 5000);

        // Remove placeholder after short delay - real tasks come via WebSocket
        setTimeout(() => {
            activeTasksStore.removeTask(placeholderTaskId);
        }, 2000);

        // Wait a bit then refresh stats
        setTimeout(fetchStats, 3000);
    } catch (error) {
        console.error('Failed to extend queue:', error);
        activeTasksStore.completeTask(placeholderTaskId, false, error.message || t('automation.extendError'));
    } finally {
        extending.value = false;
    }
};

const refreshStats = () => {
    fetchStats();
};

onMounted(() => {
    localEnabled.value = brandsStore.currentBrand?.automation_enabled ?? false;
    if (localEnabled.value) {
        fetchStats();
    }
});

watch(() => props.brandId, () => {
    localEnabled.value = brandsStore.currentBrand?.automation_enabled ?? false;
    if (localEnabled.value) {
        fetchStats();
    }
});
</script>

<style scoped>
.automation-panel {
    background: var(--color-bg-secondary, #f8f9fa);
    border-radius: 12px;
    padding: 1.5rem;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.header-content {
    flex: 1;
}

.panel-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
}

.panel-description {
    font-size: 0.875rem;
    color: var(--color-text-secondary, #6b7280);
    margin: 0;
}

.toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.toggle.is-saving {
    opacity: 0.6;
    pointer-events: none;
}

.toggle input {
    display: none;
}

.toggle-slider {
    width: 48px;
    height: 24px;
    background: var(--color-bg-tertiary, #e5e7eb);
    border-radius: 12px;
    position: relative;
    transition: background 0.2s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.toggle input:checked + .toggle-slider {
    background: var(--color-primary, #3b82f6);
}

.toggle input:checked + .toggle-slider::after {
    transform: translateX(24px);
}

.toggle-label {
    font-size: 0.875rem;
    font-weight: 500;
}

/* How it works section */
.how-it-works {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.steps-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.step-item {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.step-number {
    width: 24px;
    height: 24px;
    background: var(--color-primary, #3b82f6);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content strong {
    font-size: 0.875rem;
    display: block;
    margin-bottom: 0.125rem;
}

.step-content p {
    font-size: 0.8125rem;
    color: var(--color-text-secondary, #6b7280);
    margin: 0;
    line-height: 1.4;
}

/* Success message */
.success-message {
    display: flex;
    gap: 0.75rem;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.success-icon {
    color: #10b981;
    flex-shrink: 0;
}

.success-content strong {
    display: block;
    color: #065f46;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.success-content p {
    color: #047857;
    font-size: 0.8125rem;
    margin: 0;
}

/* Trigger message */
.trigger-message {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    margin-top: 1rem;
    color: #065f46;
    font-size: 0.875rem;
}

.trigger-message svg {
    color: #10b981;
    flex-shrink: 0;
}

/* Empty queue hint */
.empty-queue-hint {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 0.75rem;
    margin-top: 0.75rem;
    color: #92400e;
    font-size: 0.8125rem;
}

.empty-queue-hint svg {
    flex-shrink: 0;
    color: #d97706;
}

.no-pending-hint {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 6px;
    padding: 0.75rem;
    margin-top: 0.75rem;
    color: #065f46;
    font-size: 0.8125rem;
}

.no-pending-hint svg {
    flex-shrink: 0;
    color: #10b981;
}

.loading-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem;
    color: var(--color-text-secondary, #6b7280);
}

.spinner {
    width: 24px;
    height: 24px;
    border: 2px solid var(--color-border, #e5e7eb);
    border-top-color: var(--color-primary, #3b82f6);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.spinner-small {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
    margin-right: 0.5rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.stats-section {
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-secondary, #6b7280);
    margin: 0 0 0.75rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text, #111827);
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: var(--color-text-secondary, #6b7280);
    margin-top: 0.25rem;
}

.stat-card.pending .stat-value { color: #f59e0b; }
.stat-card.ready .stat-value { color: #10b981; }
.stat-card.published .stat-value { color: #3b82f6; }

.pillar-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pillar-item {
    background: white;
    border-radius: 8px;
    padding: 0.75rem 1rem;
}

.pillar-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.pillar-name {
    font-weight: 500;
    font-size: 0.875rem;
}

.pillar-counts {
    font-size: 0.75rem;
    color: var(--color-text-secondary, #6b7280);
}

.pillar-bar {
    height: 6px;
    background: var(--color-bg-tertiary, #e5e7eb);
    border-radius: 3px;
    position: relative;
    overflow: hidden;
}

.pillar-progress {
    height: 100%;
    background: var(--color-primary, #3b82f6);
    border-radius: 3px;
    transition: width 0.3s;
}

.pillar-progress.under { background: #f59e0b; }
.pillar-progress.over { background: #10b981; }

.pillar-target {
    position: absolute;
    top: -2px;
    bottom: -2px;
    width: 2px;
    background: var(--color-text, #111827);
    transform: translateX(-50%);
}

.settings-form {
    background: white;
    border-radius: 8px;
    padding: 1rem;
}

.form-group {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 500;
}

.form-group select {
    padding: 0.5rem 1rem;
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 6px;
    font-size: 0.875rem;
    background: white;
}

.form-hint {
    width: 100%;
    font-size: 0.75rem;
    color: var(--color-text-secondary, #6b7280);
    margin-top: 0.25rem;
}

.info-section {
    background: white;
    border-radius: 8px;
    padding: 1rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.25rem 0;
}

.info-label {
    font-size: 0.875rem;
    color: var(--color-text-secondary, #6b7280);
}

.info-value {
    font-size: 0.875rem;
    font-weight: 500;
}

.actions-section {
    display: flex;
    gap: 0.75rem;
    padding-top: 0.5rem;
}

.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}

.btn-icon {
    margin-right: 0.5rem;
}

.btn-primary {
    background: var(--color-primary, #3b82f6);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: var(--color-primary-dark, #2563eb);
}

.btn-secondary {
    background: white;
    color: var(--color-text, #111827);
    border: 1px solid var(--color-border, #e5e7eb);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--color-bg-secondary, #f8f9fa);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 2rem;
}

.empty-icon {
    color: var(--color-text-tertiary, #9ca3af);
    margin-bottom: 1rem;
}

.empty-state h4 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 0.875rem;
    color: var(--color-text-secondary, #6b7280);
    margin: 0;
}

.mt-4 {
    margin-top: 1rem;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .panel-header {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>
