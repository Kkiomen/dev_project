<template>
    <NleEditorPage :project-id="projectId" />
</template>

<script setup>
import NleEditorPage from '@/components/nle/NleEditorPage.vue';

const props = defineProps({
    projectId: { type: String, required: true },
});
</script>
