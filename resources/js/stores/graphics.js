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

        // Flat sorted layers for canvas rendering
        sortedLayers: (state) => [...state.layers].sort((a, b) => a.position - b.position),

        // Build tree structure from flat layers (for layer panel)
        layerTree: (state) => {
            const buildTree = (parentId = null) => {
                return state.layers
                    .filter(l => l.parent_id === parentId)
                    .sort((a, b) => a.position - b.position)
                    .map(layer => ({
                        ...layer,
                        children: layer.type === 'group' ? buildTree(layer.id) : [],
                    }));
            };
            return buildTree();
        },

        // Get layers for rendering (respecting group visibility)
        visibleLayers: (state) => {
            // Build a map of layer visibility based on parent group visibility
            const visibilityMap = new Map();

            // First pass: determine effective visibility
            const determineVisibility = (layerId) => {
                if (visibilityMap.has(layerId)) return visibilityMap.get(layerId);

                const layer = state.layers.find(l => l.id === layerId);
                if (!layer) return false;

                // If layer itself is not visible, it's not visible
                if (!layer.visible) {
                    visibilityMap.set(layerId, false);
                    return false;
                }

                // If no parent, visibility is determined by own visible flag
                if (!layer.parent_id) {
                    visibilityMap.set(layerId, true);
                    return true;
                }

                // Check parent visibility
                const parentVisible = determineVisibility(layer.parent_id);
                visibilityMap.set(layerId, parentVisible);
                return parentVisible;
            };

            // Determine visibility for all layers
            state.layers.forEach(l => determineVisibility(l.id));

            // Return layers that are effectively visible and not groups
            return state.layers
                .filter(l => l.type !== 'group' && visibilityMap.get(l.id))
                .sort((a, b) => a.position - b.position);
        },

        // Check if a layer is effectively visible (considering parent groups)
        isLayerEffectivelyVisible: (state) => (layerId) => {
            const checkVisibility = (id) => {
                const layer = state.layers.find(l => l.id === id);
                if (!layer) return false;
                if (!layer.visible) return false;
                if (!layer.parent_id) return true;
                return checkVisibility(layer.parent_id);
            };
            return checkVisibility(layerId);
        },

        canUndo: (state) => state.historyIndex > 0,

        canRedo: (state) => state.historyIndex < state.history.length - 1,

        // Get all layers that share the same smartObjectSourceId (linked smart objects)
        getLinkedSmartObjectLayers: (state) => (smartObjectSourceId) => {
            if (!smartObjectSourceId) return [];
            return state.layers.filter(
                l => l.type === 'image' && l.properties?.smartObjectSourceId === smartObjectSourceId
            );
        },
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

            // Generate unique name by adding number suffix if name already exists
            const baseName = defaultNames[type] || type;
            let uniqueName = baseName;
            let counter = 1;

            const existingNames = new Set(this.layers.map(l => l.name));
            while (existingNames.has(uniqueName)) {
                counter++;
                uniqueName = `${baseName} ${counter}`;
            }

            const data = {
                name: uniqueName,
                type,
                x: 100,
                y: 100,
                width: type === 'text' ? null : 200,
                height: type === 'text' ? null : 200,
                visible: true,
                ...props,
            };

            try {
                const response = await axios.post(
                    `/api/v1/templates/${this.currentTemplate.id}/layers`,
                    data
                );
                const newLayer = response.data.data;
                // Use spread instead of push for better Vue reactivity
                this.layers = [...this.layers, newLayer];
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

        /**
         * Update image source for all linked smart object instances.
         * When one smart object's image is changed, all instances with the same
         * smartObjectSourceId are updated to maintain Photoshop-like linked behavior.
         *
         * @param {number} layerId - The ID of the layer being updated
         * @param {string} newSrc - The new image source (base64 or URL)
         * @returns {number} Number of layers updated
         */
        updateLinkedSmartObjectImage(layerId, newSrc) {
            const layer = this.layers.find(l => l.id === layerId);
            if (!layer) return 0;

            const smartObjectSourceId = layer.properties?.smartObjectSourceId;

            // If no smartObjectSourceId, just update the single layer
            if (!smartObjectSourceId) {
                this.updateLayerLocally(layerId, {
                    properties: { src: newSrc },
                });
                return 1;
            }

            // Find all linked layers with the same smartObjectSourceId
            const linkedLayers = this.getLinkedSmartObjectLayers(smartObjectSourceId);

            // Update all linked layers
            linkedLayers.forEach(linkedLayer => {
                this.updateLayerLocally(linkedLayer.id, {
                    properties: { src: newSrc },
                });
            });

            console.log(`[SmartObject] Updated ${linkedLayers.length} linked layers with smartObjectSourceId: ${smartObjectSourceId}`);

            return linkedLayers.length;
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

        // Group actions
        toggleGroupExpanded(groupId) {
            const index = this.layers.findIndex(l => l.id === groupId);
            if (index !== -1 && this.layers[index].type === 'group') {
                const layer = this.layers[index];
                const expanded = !(layer.properties?.expanded ?? true);
                this.layers.splice(index, 1, {
                    ...layer,
                    properties: {
                        ...layer.properties,
                        expanded,
                    },
                });
            }
        },

        isGroupExpanded(groupId) {
            const layer = this.layers.find(l => l.id === groupId);
            return layer?.properties?.expanded ?? true;
        },

        // Toggle visibility of a layer (including groups)
        toggleLayerVisibility(layerId) {
            const index = this.layers.findIndex(l => l.id === layerId);
            if (index !== -1) {
                const layer = this.layers[index];
                this.layers.splice(index, 1, {
                    ...layer,
                    visible: !layer.visible,
                });
                this.isDirty = true;
            }
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
        // Direct setters for external use (e.g., embedded editor)
        setCurrentTemplate(template) {
            this.currentTemplate = template;
            this.selectedLayerId = null;
            this.isDirty = false;
            this.saveToHistory();
        },

        setLayers(layers) {
            this.layers = layers || [];
        },

        setFonts(fonts) {
            this.fonts = fonts || [];
        },

        reset() {
            this.currentTemplate = null;
            this.layers = [];
            this.selectedLayerId = null;
            this.selectedLayerIds = [];
            this.fonts = [];
            this.history = [];
            this.historyIndex = -1;
            this.isDirty = false;
            this.zoom = 1;
            this.tool = 'select';
            this.lastError = null;
            this.clipboard = null;
        },

        clearError() {
            this.lastError = null;
            this.error = null;
        },
    },
});
