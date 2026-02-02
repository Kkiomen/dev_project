import { defineStore } from 'pinia';
import axios from 'axios';

export const usePsdEditorStore = defineStore('psdEditor', {
    state: () => ({
        // Files
        files: [],
        currentFile: null,
        filesLoading: false,

        // Parsed PSD data
        parsedData: null,
        layers: [],
        parsingLoading: false,

        // Tags
        tags: {},
        tagsSaving: false,

        // Preview
        selectedVariantPath: null,
        previewUrl: null,
        previewLoading: false,
        testData: {
            header: '',
            subtitle: '',
            paragraph: '',
            primary_color: '#000000',
            secondary_color: '#FFFFFF',
            social_handle: '',
            main_image: '',
            logo: '',
        },

        // Import
        importing: false,
        importedTemplates: [],

        // UI State
        expandedGroups: new Set(),
        error: null,
    }),

    getters: {
        // Get flat list of all variants (groups marked as variants)
        variants: (state) => {
            const variants = [];
            const findVariants = (layers, parentPath = '') => {
                for (const layer of layers) {
                    const path = parentPath ? `${parentPath}/${layer.name}` : layer.name;
                    if (layer._is_variant) {
                        variants.push({
                            path,
                            name: layer.name,
                            layer,
                        });
                    }
                    if (layer.children) {
                        findVariants(layer.children, path);
                    }
                }
            };
            findVariants(state.layers);
            return variants;
        },

        // Build layer tree with paths
        layerTree: (state) => {
            const buildTree = (layers, parentPath = '') => {
                return layers.map(layer => {
                    const path = parentPath ? `${parentPath}/${layer.name}` : layer.name;
                    return {
                        ...layer,
                        _path: path,
                        _semantic_tag: state.tags[path]?.semantic_tag || null,
                        _is_variant: state.tags[path]?.is_variant || false,
                        children: layer.children ? buildTree(layer.children, path) : [],
                    };
                });
            };
            return buildTree(state.layers);
        },

        // Get layers with semantic tags
        taggedLayers: (state) => {
            const tagged = [];
            const collectTagged = (layers, parentPath = '') => {
                for (const layer of layers) {
                    const path = parentPath ? `${parentPath}/${layer.name}` : layer.name;
                    if (state.tags[path]?.semantic_tag) {
                        tagged.push({
                            path,
                            name: layer.name,
                            type: layer.type,
                            semantic_tag: state.tags[path].semantic_tag,
                        });
                    }
                    if (layer.children) {
                        collectTagged(layer.children, path);
                    }
                }
            };
            collectTagged(state.layers);
            return tagged;
        },

        hasUnsavedTags: (state) => {
            return Object.keys(state.tags).length > 0;
        },
    },

    actions: {
        // Files
        async fetchFiles() {
            this.filesLoading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/psd-files');
                this.files = response.data.files;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch files';
                throw error;
            } finally {
                this.filesLoading = false;
            }
        },

        async selectFile(filename) {
            if (this.currentFile === filename) return;

            this.currentFile = filename;
            this.parsedData = null;
            this.layers = [];
            this.tags = {};
            this.selectedVariantPath = null;
            this.previewUrl = null;

            if (filename) {
                await this.parseFile(filename);
                await this.fetchTags(filename);
            }
        },

        async parseFile(filename) {
            this.parsingLoading = true;
            this.error = null;
            try {
                const response = await axios.post(`/api/v1/psd-files/${encodeURIComponent(filename)}/parse`);
                this.parsedData = response.data;
                this.layers = response.data.layers || [];
                return this.parsedData;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to parse PSD';
                throw error;
            } finally {
                this.parsingLoading = false;
            }
        },

        async getFileUrl(filename) {
            return `/api/v1/psd-files/${encodeURIComponent(filename)}`;
        },

        async saveFile(filename, fileBlob) {
            const formData = new FormData();
            formData.append('file', fileBlob, filename);

            try {
                await axios.put(`/api/v1/psd-files/${encodeURIComponent(filename)}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to save PSD';
                throw error;
            }
        },

        // Tags
        async fetchTags(filename) {
            try {
                const response = await axios.get(`/api/v1/psd-files/${encodeURIComponent(filename)}/tags`);
                const tagsArray = response.data.tags || [];
                this.tags = {};
                for (const tag of tagsArray) {
                    this.tags[tag.layer_path] = {
                        semantic_tag: tag.semantic_tag,
                        is_variant: tag.is_variant,
                    };
                }
            } catch (error) {
                console.error('Failed to fetch tags:', error);
            }
        },

        setLayerTag(layerPath, semanticTag) {
            const existing = this.tags[layerPath] || { semantic_tag: null, is_variant: false };
            this.tags = {
                ...this.tags,
                [layerPath]: {
                    ...existing,
                    semantic_tag: semanticTag || null,
                },
            };
        },

        setLayerVariant(layerPath, isVariant) {
            const existing = this.tags[layerPath] || { semantic_tag: null, is_variant: false };
            this.tags = {
                ...this.tags,
                [layerPath]: {
                    ...existing,
                    is_variant: isVariant,
                },
            };
        },

        async saveTags() {
            if (!this.currentFile) return;

            this.tagsSaving = true;
            try {
                const tagsArray = Object.entries(this.tags).map(([layerPath, data]) => ({
                    layer_path: layerPath,
                    semantic_tag: data.semantic_tag,
                    is_variant: data.is_variant,
                }));

                await axios.put(`/api/v1/psd-files/${encodeURIComponent(this.currentFile)}/tags`, {
                    tags: tagsArray,
                });
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to save tags';
                throw error;
            } finally {
                this.tagsSaving = false;
            }
        },

        // Preview
        selectVariant(variantPath) {
            this.selectedVariantPath = variantPath;
            this.previewUrl = null;
        },

        updateTestData(field, value) {
            this.testData[field] = value;
        },

        async generatePreview() {
            if (!this.currentFile || !this.selectedVariantPath) return;

            this.previewLoading = true;
            this.error = null;
            try {
                const response = await axios.post(
                    `/api/v1/psd-files/${encodeURIComponent(this.currentFile)}/preview`,
                    {
                        variant: this.selectedVariantPath,
                        data: this.testData,
                    }
                );
                this.previewUrl = response.data.preview_url;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to generate preview';
                throw error;
            } finally {
                this.previewLoading = false;
            }
        },

        // Import
        async importVariants(addToLibrary = false) {
            if (!this.currentFile) return;

            const variantPaths = this.variants.map(v => v.path);
            if (variantPaths.length === 0) {
                this.error = 'No variants selected for import';
                return;
            }

            this.importing = true;
            this.error = null;
            try {
                const response = await axios.post(
                    `/api/v1/psd-files/${encodeURIComponent(this.currentFile)}/import`,
                    {
                        variants: variantPaths,
                        add_to_library: addToLibrary,
                    }
                );
                this.importedTemplates = response.data.templates || [];
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to import variants';
                throw error;
            } finally {
                this.importing = false;
            }
        },

        // UI State
        toggleGroupExpanded(path) {
            if (this.expandedGroups.has(path)) {
                this.expandedGroups.delete(path);
            } else {
                this.expandedGroups.add(path);
            }
            // Trigger reactivity
            this.expandedGroups = new Set(this.expandedGroups);
        },

        isGroupExpanded(path) {
            return this.expandedGroups.has(path);
        },

        expandAll() {
            const paths = [];
            const collectGroupPaths = (layers, parentPath = '') => {
                for (const layer of layers) {
                    const path = parentPath ? `${parentPath}/${layer.name}` : layer.name;
                    if (layer.type === 'group' || layer.children?.length > 0) {
                        paths.push(path);
                    }
                    if (layer.children) {
                        collectGroupPaths(layer.children, path);
                    }
                }
            };
            collectGroupPaths(this.layers);
            this.expandedGroups = new Set(paths);
        },

        collapseAll() {
            this.expandedGroups = new Set();
        },

        // Reset
        reset() {
            this.files = [];
            this.currentFile = null;
            this.parsedData = null;
            this.layers = [];
            this.tags = {};
            this.selectedVariantPath = null;
            this.previewUrl = null;
            this.testData = {
                header: '',
                subtitle: '',
                paragraph: '',
                primary_color: '#000000',
                secondary_color: '#FFFFFF',
                social_handle: '',
                main_image: '',
                logo: '',
            };
            this.importedTemplates = [];
            this.expandedGroups = new Set();
            this.error = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
