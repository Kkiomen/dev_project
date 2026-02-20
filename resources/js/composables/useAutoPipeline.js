import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePostsStore } from '@/stores/posts';

const BATCH_SIZE = 20;

/**
 * Returns the next pipeline step a post needs.
 * Steps: text → imageDesc → image → approve → null (complete)
 */
export function getPostPipelineStep(post) {
    if (!post) return null;
    if (!post.main_caption) return 'text';
    if (!post.image_prompt) return 'imageDesc';
    if (!post.first_media_url && (post.media_count || 0) === 0) return 'image';
    if (!['approved', 'scheduled', 'published'].includes(post.status)) return 'approve';
    return null;
}

/**
 * Returns a summary of pipeline needs across posts.
 */
export function getPipelineSummary(posts) {
    const summary = { needText: 0, needImageDesc: 0, needImage: 0, needApproval: 0, complete: 0 };
    for (const post of posts) {
        const step = getPostPipelineStep(post);
        if (step === 'text') summary.needText++;
        else if (step === 'imageDesc') summary.needImageDesc++;
        else if (step === 'image') summary.needImage++;
        else if (step === 'approve') summary.needApproval++;
        else summary.complete++;
    }
    return summary;
}

/**
 * Composable for running the auto-pipeline on posts.
 */
export function useAutoPipeline() {
    const postsStore = usePostsStore();
    const { t } = useI18n();

    const isProcessing = ref(false);
    const cancelled = ref(false);
    const currentStep = ref(null); // 'text' | 'imageDesc' | 'image' | 'approve'
    const progress = ref({ totalPosts: 0, processedPosts: 0, currentStepIndex: 0, totalSteps: 0, errors: 0 });

    /**
     * Wait for all polling flags to settle for given post IDs.
     * Returns a promise that resolves when no more generating flags are active.
     */
    function waitForPolling(postIds, type) {
        return new Promise((resolve) => {
            const flagMap = {
                text: () => postsStore.generatingText,
                imageDesc: () => postsStore.generatingImageDescription,
                image: () => postsStore.generatingImage,
            };
            const getFlags = flagMap[type];
            if (!getFlags) {
                resolve();
                return;
            }

            const check = () => {
                const flags = getFlags();
                const anyActive = postIds.some(id => flags[id]);
                return !anyActive;
            };

            if (check()) {
                resolve();
                return;
            }

            const stop = watch(
                getFlags,
                () => {
                    if (check()) {
                        stop();
                        resolve();
                    }
                },
                { deep: true }
            );

            // Safety timeout — 5 minutes max wait
            setTimeout(() => {
                stop();
                resolve();
            }, 300000);
        });
    }

    /**
     * Batch array into chunks.
     */
    function batchArray(arr, size) {
        const batches = [];
        for (let i = 0; i < arr.length; i += size) {
            batches.push(arr.slice(i, i + size));
        }
        return batches;
    }

    /**
     * Process the next step for a single post.
     */
    async function processNextStep(postId, silentRefreshFn) {
        const post = postsStore.automationPosts.find(p => p.id === postId);
        if (!post) return;

        const step = getPostPipelineStep(post);
        if (!step) return;

        try {
            if (step === 'text') {
                await postsStore.generatePostText(postId);
                await waitForPolling([postId], 'text');
            } else if (step === 'imageDesc') {
                await postsStore.generatePostImageDescription(postId);
                await waitForPolling([postId], 'imageDesc');
            } else if (step === 'image') {
                await postsStore.generatePostImagePrompt(postId);
                await waitForPolling([postId], 'image');
            } else if (step === 'approve') {
                await postsStore.approvePost(postId);
                postsStore.updateAutomationPost(postId, {
                    status: 'approved',
                    status_label: t('posts.status.approved'),
                });
            }
        } catch {
            // Error handled silently for single step
        }

        if (silentRefreshFn) {
            await silentRefreshFn();
        }
    }

    /**
     * Process all posts through the full pipeline.
     * Steps: bulk text → refresh → bulk imageDesc → refresh → bulk image → refresh → bulk approve
     */
    async function processAll(posts, silentRefreshFn) {
        if (isProcessing.value) return;

        isProcessing.value = true;
        cancelled.value = false;

        const totalPosts = posts.length;
        const stepsToRun = [];

        // Determine which steps are needed
        const summary = getPipelineSummary(posts);
        if (summary.needText > 0) stepsToRun.push('text');
        if (summary.needText > 0 || summary.needImageDesc > 0) stepsToRun.push('imageDesc');
        if (summary.needText > 0 || summary.needImageDesc > 0 || summary.needImage > 0) stepsToRun.push('image');
        if (summary.needText > 0 || summary.needImageDesc > 0 || summary.needImage > 0 || summary.needApproval > 0) stepsToRun.push('approve');

        if (stepsToRun.length === 0) {
            isProcessing.value = false;
            return;
        }

        progress.value = {
            totalPosts,
            processedPosts: 0,
            currentStepIndex: 0,
            totalSteps: stepsToRun.length,
            errors: 0,
        };

        for (let stepIdx = 0; stepIdx < stepsToRun.length; stepIdx++) {
            if (cancelled.value) break;

            const step = stepsToRun[stepIdx];
            currentStep.value = step;
            progress.value.currentStepIndex = stepIdx + 1;

            // Get fresh data for filtering
            const currentPosts = silentRefreshFn
                ? postsStore.automationPosts
                : posts;

            // Filter posts that need this step
            const postsNeedingStep = currentPosts.filter(p => getPostPipelineStep(p) === step);
            const postIds = postsNeedingStep.map(p => p.id);

            if (postIds.length === 0) continue;

            const batches = batchArray(postIds, BATCH_SIZE);

            for (const batch of batches) {
                if (cancelled.value) break;

                try {
                    if (step === 'text') {
                        await postsStore.bulkGenerateText(batch);
                        await waitForPolling(batch, 'text');
                    } else if (step === 'imageDesc') {
                        await postsStore.bulkGenerateImageDescription(batch);
                        await waitForPolling(batch, 'imageDesc');
                    } else if (step === 'image') {
                        await postsStore.bulkGenerateImagePrompt(batch);
                        await waitForPolling(batch, 'image');
                    } else if (step === 'approve') {
                        await postsStore.batchApprove(batch);
                    }
                    progress.value.processedPosts += batch.length;
                } catch {
                    progress.value.errors += batch.length;
                }
            }

            // Refresh data after each step so next step has up-to-date values
            if (silentRefreshFn && !cancelled.value) {
                await silentRefreshFn();
            }
        }

        currentStep.value = null;
        isProcessing.value = false;
    }

    function cancel() {
        cancelled.value = true;
    }

    return {
        isProcessing,
        currentStep,
        progress,
        processNextStep,
        processAll,
        cancel,
    };
}
