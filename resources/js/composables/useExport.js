import { ref } from 'vue';
import axios from 'axios';

export function useExport(stageRef, template) {
    const exporting = ref(false);

    const getStageNode = () => {
        return stageRef.value?.getNode?.() || stageRef.value?.getNode();
    };

    const exportToDataURL = (options = {}) => {
        const stage = getStageNode();
        if (!stage) return null;

        return stage.toDataURL({
            pixelRatio: options.pixelRatio || 2,
            mimeType: options.mimeType || 'image/png',
            quality: options.quality || 1,
            ...options,
        });
    };

    const exportToBlob = (options = {}) => {
        const stage = getStageNode();
        if (!stage) return Promise.resolve(null);

        return new Promise((resolve) => {
            stage.toBlob({
                callback: resolve,
                pixelRatio: options.pixelRatio || 2,
                mimeType: options.mimeType || 'image/png',
                quality: options.quality || 1,
                ...options,
            });
        });
    };

    const downloadImage = async (filename = 'graphic.png', options = {}) => {
        const dataURL = exportToDataURL(options);
        if (!dataURL) return;

        const link = document.createElement('a');
        link.download = filename;
        link.href = dataURL;
        link.click();
    };

    const uploadToServer = async (templateId, modifications = null) => {
        exporting.value = true;

        try {
            const blob = await exportToBlob();
            if (!blob) {
                throw new Error('Failed to generate image');
            }

            const formData = new FormData();
            formData.append('image', blob, 'generated.png');

            if (modifications) {
                formData.append('modifications', JSON.stringify(modifications));
            }

            const response = await axios.post(
                `/api/v1/templates/${templateId}/images`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                }
            );

            return response.data.data;
        } finally {
            exporting.value = false;
        }
    };

    const generateWithModifications = async (modifications, options = {}) => {
        // This function would apply modifications to layers
        // and generate an image with those modifications
        // Used for batch generation

        const stage = getStageNode();
        if (!stage) return null;

        // Apply modifications to layers temporarily
        // ... (implementation depends on how modifications are structured)

        const dataURL = exportToDataURL(options);

        // Restore original layer values
        // ...

        return dataURL;
    };

    return {
        exporting,
        exportToDataURL,
        exportToBlob,
        downloadImage,
        uploadToServer,
        generateWithModifications,
    };
}
