import { defineStore } from 'pinia';
import axios from 'axios';

export const useGraphicsStore = defineStore('graphics', {
    state: () => ({
        templates: [],
        currentTemplate: null,
        layers: [],
        selectedLayerId: null,
        selectedLayerIds: [], // For multi-select
        fonts: [],
        loading: false,
        saving: false,
        deleting: false,
        duplicating: false,
        layerOperationInProgress: null, // 'add' | 'update' | 'delete' | 'reorder' | null
        error: null,
        lastError: null, // Store last error for display
        zoom: 1,
        tool: 'select', // select, text, image, rectangle, ellipse
        history: [],
        historyIndex: -1,
        isDirty: false,
        clipboard: null, // For copy/paste
        lastSavedAt: null, // Timestamp of last save
        chatPanelOpen: false, // AI Chat panel state
    }),

    getters: {
        getTemplateById: (state) => (id) => state.templates.find(t => t.id === id),

        selectedLayer: (state) => state.layers.find(l => l.id === state.selectedLayerId),

        selectedLayers: (state) => state.layers.filter(l => state.selectedLayerIds.includes(l.id)),

        hasMultipleSelection: (state) => state.selectedLayerIds.length > 1,

        sortedLayers: (state) => [...state.layers].sort((a, b) => a.position - b.position),

        canUndo: (state) => state.historyIndex > 0,

        canRedo: (state) => state.historyIndex < state.history.length - 1,
    },

    actions: {
        // Templates
        async fetchTemplates() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/v1/templates');
                this.templates = response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch templates';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchTemplate(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/v1/templates/${id}`);
                this.currentTemplate = response.data.data;
                this.layers = response.data.data.layers || [];
                this.fonts = response.data.data.fonts || [];
                this.selectedLayerId = null;
                this.isDirty = false;
                this.saveToHistory();
                return this.currentTemplate;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch template';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createTemplate(data) {
            try {
                const response = await axios.post('/api/v1/templates', data);
                const newTemplate = response.data.data;
                this.templates.push(newTemplate);
                return newTemplate;
            } catch (error) {
                throw error;
            }
        },

        async updateTemplate(id, data) {
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/templates/${id}`, data);
                const updatedTemplate = response.data.data;

                const index = this.templates.findIndex(t => t.id === id);
                if (index !== -1) {
                    this.templates[index] = { ...this.templates[index], ...updatedTemplate };
                }

                if (this.currentTemplate?.id === id) {
                    this.currentTemplate = { ...this.currentTemplate, ...updatedTemplate };
                }

                this.isDirty = false;
                return updatedTemplate;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        async deleteTemplate(id) {
            this.deleting = true;
            this.lastError = null;
            try {
                await axios.delete(`/api/v1/templates/${id}`);
                this.templates = this.templates.filter(t => t.id !== id);
                if (this.currentTemplate?.id === id) {
                    this.currentTemplate = null;
                    this.layers = [];
                }
            } catch (error) {
                this.lastError = error.response?.data?.message || 'Failed to delete template';
                throw error;
            } finally {
                this.deleting = false;
            }
        },

        async duplicateTemplate(id) {
            this.duplicating = true;
            this.lastError = null;
            try {
                const response = await axios.post(`/api/v1/templates/${id}/duplicate`);
                const newTemplate = response.data.data;
                this.templates.push(newTemplate);
                return newTemplate;
            } catch (error) {
                this.lastError = error.response?.data?.message || 'Failed to duplicate template';
                throw error;
            } finally {
                this.duplicating = false;
            }
        },

        // Layers
        async addLayer(type, props = {}) {
            if (!this.currentTemplate) return;

            const defaultNames = {
                text: 'Text',
                image: 'Image',
                rectangle: 'Rectangle',
                ellipse: 'Ellipse',
                textbox: 'Text Box',
            };

            const data = {
                name: defaultNames[type] || type,
                type,
                x: 100,
                y: 100,
                width: type === 'text' ? null : 200,
                height: type === 'text' ? null : 200,
                ...props,
            };

            try {
                const response = await axios.post(
                    `/api/v1/templates/${this.currentTemplate.id}/layers`,
                    data
                );
                const newLayer = response.data.data;
                this.layers.push(newLayer);
                this.selectedLayerId = newLayer.id;
                this.isDirty = true;
                this.saveToHistory();
                return newLayer;
            } catch (error) {
                throw error;
            }
        },

        async updateLayer(id, changes) {
            const layer = this.layers.find(l => l.id === id);
            if (!layer) return;

            // Optimistic update
            const index = this.layers.findIndex(l => l.id === id);
            this.layers[index] = { ...layer, ...changes };
            this.isDirty = true;

            try {
                const response = await axios.put(`/api/v1/layers/${id}`, changes);
                this.layers[index] = response.data.data;
                this.saveToHistory();
                return response.data.data;
            } catch (error) {
                // Revert on error
                this.layers[index] = layer;
                throw error;
            }
        },

        updateLayerLocally(id, changes) {
            const index = this.layers.findIndex(l => l.id === id);
            if (index !== -1) {
                // Deep merge for nested properties
                const currentLayer = this.layers[index];
                const updatedLayer = {
                    ...currentLayer,
                    ...changes,
                    properties: {
                        ...currentLayer.properties,
                        ...(changes.properties || {}),
                    },
                };
                // Use splice to ensure Vue reactivity is triggered
                this.layers.splice(index, 1, updatedLayer);
                this.isDirty = true;
            }
        },

        async deleteLayer(id) {
            try {
                await axios.delete(`/api/v1/layers/${id}`);
                this.layers = this.layers.filter(l => l.id !== id);
                if (this.selectedLayerId === id) {
                    this.selectedLayerId = null;
                }
                this.isDirty = true;
                this.saveToHistory();
            } catch (error) {
                throw error;
            }
        },

        async reorderLayer(id, newPosition) {
            const layer = this.layers.find(l => l.id === id);
            if (!layer) return;

            const oldPosition = layer.position;
            if (oldPosition === newPosition) return;

            // Update positions locally first
            this.layers.forEach(l => {
                if (l.id === id) {
                    l.position = newPosition;
                } else if (oldPosition < newPosition) {
                    // Moving down: decrease positions of layers in between
                    if (l.position > oldPosition && l.position <= newPosition) {
                        l.position--;
                    }
                } else {
                    // Moving up: increase positions of layers in between
                    if (l.position >= newPosition && l.position < oldPosition) {
                        l.position++;
                    }
                }
            });

            this.isDirty = true;
            this.saveToHistory();

            // Send to server (don't re-fetch to preserve local changes)
            try {
                await axios.post(`/api/v1/layers/${id}/reorder`, { position: newPosition });
            } catch (error) {
                // Revert on error - re-fetch from server
                if (this.currentTemplate) {
                    await this.fetchTemplate(this.currentTemplate.id);
                }
                throw error;
            }
        },

        async saveAllLayers() {
            if (!this.currentTemplate) return;
            if (this.layers.length === 0 && !this.isDirty) return;

            this.saving = true;
            try {
                const response = await axios.put(
                    `/api/v1/templates/${this.currentTemplate.id}/layers`,
                    { layers: this.layers }
                );
                this.layers = response.data.data;
                this.isDirty = false;
                this.lastSavedAt = Date.now();
                return this.layers;
            } catch (error) {
                throw error;
            } finally {
                this.saving = false;
            }
        },

        selectLayer(id, addToSelection = false) {
            if (addToSelection) {
                // Multi-select with Shift
                if (this.selectedLayerIds.includes(id)) {
                    // Remove from selection if already selected
                    this.selectedLayerIds = this.selectedLayerIds.filter(lid => lid !== id);
                    if (this.selectedLayerId === id) {
                        this.selectedLayerId = this.selectedLayerIds[0] || null;
                    }
                } else {
                    // Add to selection
                    this.selectedLayerIds.push(id);
                    this.selectedLayerId = id;
                }
            } else {
                // Single select
                this.selectedLayerId = id;
                this.selectedLayerIds = id ? [id] : [];
            }
        },

        deselectLayer() {
            this.selectedLayerId = null;
            this.selectedLayerIds = [];
        },

        // Select all layers
        selectAllLayers() {
            this.selectedLayerIds = this.layers.map(l => l.id);
            this.selectedLayerId = this.selectedLayerIds[0] || null;
        },

        // Copy selected layer to clipboard
        copyLayer() {
            if (!this.selectedLayerId) return;
            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (layer) {
                this.clipboard = JSON.parse(JSON.stringify(layer));
            }
        },

        // Paste layer from clipboard
        async pasteLayer() {
            if (!this.clipboard || !this.currentTemplate) return;

            const newLayerData = {
                ...this.clipboard,
                name: `${this.clipboard.name} (copy)`,
                x: (this.clipboard.x || 0) + 20,
                y: (this.clipboard.y || 0) + 20,
            };

            // Remove id to create new layer
            delete newLayerData.id;
            delete newLayerData.public_id;
            delete newLayerData.template_id;
            delete newLayerData.created_at;
            delete newLayerData.updated_at;

            try {
                const response = await axios.post(
                    `/api/v1/templates/${this.currentTemplate.id}/layers`,
                    newLayerData
                );
                const newLayer = response.data.data;
                this.layers.push(newLayer);
                this.selectedLayerId = newLayer.id;
                this.isDirty = true;
                this.saveToHistory();
                return newLayer;
            } catch (error) {
                throw error;
            }
        },

        // Duplicate selected layer
        async duplicateLayer() {
            if (!this.selectedLayerId) return;

            // Copy current selection to clipboard and paste
            this.copyLayer();
            return this.pasteLayer();
        },

        // Bring selected layer to front (highest z-index)
        async bringToFront() {
            if (!this.selectedLayerId) return;

            const maxPosition = Math.max(...this.layers.map(l => l.position));
            const layer = this.layers.find(l => l.id === this.selectedLayerId);

            if (layer && layer.position < maxPosition) {
                await this.reorderLayer(this.selectedLayerId, maxPosition);
            }
        },

        // Send selected layer to back (lowest z-index)
        async sendToBack() {
            if (!this.selectedLayerId) return;

            const minPosition = Math.min(...this.layers.map(l => l.position));
            const layer = this.layers.find(l => l.id === this.selectedLayerId);

            if (layer && layer.position > minPosition) {
                await this.reorderLayer(this.selectedLayerId, minPosition);
            }
        },

        // Move layer one step up
        async bringForward() {
            if (!this.selectedLayerId) return;

            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;

            const maxPosition = Math.max(...this.layers.map(l => l.position));
            if (layer.position < maxPosition) {
                await this.reorderLayer(this.selectedLayerId, layer.position + 1);
            }
        },

        // Move layer one step down
        async sendBackward() {
            if (!this.selectedLayerId) return;

            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;

            const minPosition = Math.min(...this.layers.map(l => l.position));
            if (layer.position > minPosition) {
                await this.reorderLayer(this.selectedLayerId, layer.position - 1);
            }
        },

        // Alignment - align selected layer to canvas
        alignLeft() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            this.updateLayerLocally(this.selectedLayerId, { x: 0 });
        },

        alignCenterH() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;
            const newX = (this.currentTemplate.width - (layer.width || 0)) / 2;
            this.updateLayerLocally(this.selectedLayerId, { x: newX });
        },

        alignRight() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;
            const newX = this.currentTemplate.width - (layer.width || 0);
            this.updateLayerLocally(this.selectedLayerId, { x: newX });
        },

        alignTop() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            this.updateLayerLocally(this.selectedLayerId, { y: 0 });
        },

        alignCenterV() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;
            const newY = (this.currentTemplate.height - (layer.height || 0)) / 2;
            this.updateLayerLocally(this.selectedLayerId, { y: newY });
        },

        alignBottom() {
            if (!this.selectedLayerId || !this.currentTemplate) return;
            const layer = this.layers.find(l => l.id === this.selectedLayerId);
            if (!layer) return;
            const newY = this.currentTemplate.height - (layer.height || 0);
            this.updateLayerLocally(this.selectedLayerId, { y: newY });
        },

        // Tools
        setTool(tool) {
            this.tool = tool;
        },

        // Zoom
        setZoom(zoom) {
            this.zoom = Math.max(0.1, Math.min(5, zoom));
        },

        zoomIn() {
            this.setZoom(this.zoom * 1.2);
        },

        zoomOut() {
            this.setZoom(this.zoom / 1.2);
        },

        resetZoom() {
            this.zoom = 1;
        },

        // AI Chat Panel
        toggleChatPanel() {
            this.chatPanelOpen = !this.chatPanelOpen;
        },

        openChatPanel() {
            this.chatPanelOpen = true;
        },

        closeChatPanel() {
            this.chatPanelOpen = false;
        },

        // History
        saveToHistory() {
            const state = {
                layers: JSON.parse(JSON.stringify(this.layers)),
                selectedLayerId: this.selectedLayerId,
            };

            // Remove any future states if we're not at the end
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }

            this.history.push(state);
            this.historyIndex = this.history.length - 1;

            // Limit history size
            if (this.history.length > 50) {
                this.history.shift();
                this.historyIndex--;
            }
        },

        undo() {
            if (!this.canUndo) return;

            this.historyIndex--;
            const state = this.history[this.historyIndex];
            this.layers = JSON.parse(JSON.stringify(state.layers));
            this.selectedLayerId = state.selectedLayerId;
            this.isDirty = true;
        },

        redo() {
            if (!this.canRedo) return;

            this.historyIndex++;
            const state = this.history[this.historyIndex];
            this.layers = JSON.parse(JSON.stringify(state.layers));
            this.selectedLayerId = state.selectedLayerId;
            this.isDirty = true;
        },

        // Reset
        reset() {
            this.currentTemplate = null;
            this.layers = [];
            this.selectedLayerId = null;
            this.fonts = [];
            this.history = [];
            this.historyIndex = -1;
            this.isDirty = false;
            this.zoom = 1;
            this.tool = 'select';
            this.lastError = null;
        },

        clearError() {
            this.lastError = null;
            this.error = null;
        },
    },
});
