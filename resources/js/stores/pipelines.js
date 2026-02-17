import { defineStore } from 'pinia';
import axios from 'axios';
import { useBrandsStore } from '@/stores/brands';

export const usePipelinesStore = defineStore('pipelines', {
    state: () => ({
        pipelines: [],
        pipelinesLoading: false,
        pipelinesMeta: null,

        currentPipeline: null,
        currentPipelineLoading: false,

        // Canvas state (synced from Vue Flow)
        nodes: [],
        edges: [],
        selectedNodeId: null,
        isDirty: false,

        // Node types from backend
        nodeTypes: [],
        nodeTypesLoading: false,

        // Execution
        currentRun: null,
        runs: [],
        runsLoading: false,
        executing: false,

        // Node preview data (cached images per node from last run)
        nodePreviewData: {},

        saving: false,
    }),

    getters: {
        currentBrandId() {
            const brandsStore = useBrandsStore();
            return brandsStore.currentBrand?.id;
        },

        selectedNode(state) {
            if (!state.selectedNodeId) return null;
            return state.nodes.find(n => n.id === state.selectedNodeId) || null;
        },

        nodeTypesByCategory(state) {
            const inputs = ['image_input', 'text_input', 'template'];
            const processing = ['ai_image_generator', 'image_analysis', 'template_render'];
            const output = ['output'];
            return {
                inputs: state.nodeTypes.filter(t => inputs.includes(t.type)),
                processing: state.nodeTypes.filter(t => processing.includes(t.type)),
                output: state.nodeTypes.filter(t => output.includes(t.type)),
            };
        },
    },

    actions: {
        // === Pipelines CRUD ===
        async fetchPipelines(params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.pipelinesLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-pipelines`, { params });
                this.pipelines = response.data.data;
                this.pipelinesMeta = response.data.meta;
            } catch (error) { throw error; }
            finally { this.pipelinesLoading = false; }
        },

        async fetchPipeline(pipelineId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.currentPipelineLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}`);
                this.currentPipeline = response.data.data;
                this.loadCanvasFromPipeline(this.currentPipeline);
                return this.currentPipeline;
            } catch (error) { throw error; }
            finally { this.currentPipelineLoading = false; }
        },

        async createPipeline(data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.saving = true;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-pipelines`, data);
                const pipeline = response.data.data;
                this.pipelines.unshift(pipeline);
                return pipeline;
            } catch (error) { throw error; }
            finally { this.saving = false; }
        },

        async updatePipeline(pipelineId, data) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.saving = true;
            try {
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}`, data);
                const pipeline = response.data.data;
                const idx = this.pipelines.findIndex(p => p.id === pipelineId);
                if (idx >= 0) this.pipelines[idx] = pipeline;
                if (this.currentPipeline?.id === pipelineId) {
                    this.currentPipeline = { ...this.currentPipeline, ...pipeline };
                }
                return pipeline;
            } catch (error) { throw error; }
            finally { this.saving = false; }
        },

        async deletePipeline(pipelineId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                await axios.delete(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}`);
                this.pipelines = this.pipelines.filter(p => p.id !== pipelineId);
            } catch (error) { throw error; }
        },

        // === Canvas ===
        async saveCanvas(pipelineId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.saving = true;
            try {
                const payload = {
                    canvas_state: this.currentPipeline?.canvas_state || null,
                    nodes: this.nodes.map(n => ({
                        node_id: n.id,
                        type: n.type,
                        label: n.data?.label || null,
                        position: n.position,
                        config: n.data?.config || null,
                        data: n.data || null,
                    })),
                    edges: this.edges.map(e => ({
                        edge_id: e.id,
                        source_node_id: e.source,
                        source_handle: e.sourceHandle || null,
                        target_node_id: e.target,
                        target_handle: e.targetHandle || null,
                    })),
                };
                const response = await axios.put(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/canvas`, payload);
                this.currentPipeline = response.data.data;
                this.isDirty = false;
                return response.data.data;
            } catch (error) { throw error; }
            finally { this.saving = false; }
        },

        loadCanvasFromPipeline(pipeline) {
            if (!pipeline) return;
            this.nodes = (pipeline.nodes || []).map(n => ({
                id: n.node_id,
                type: n.type,
                position: n.position || { x: 0, y: 0 },
                data: {
                    ...n.data,
                    label: n.label,
                    config: n.config || {},
                },
            }));
            this.edges = (pipeline.edges || []).map(e => ({
                id: e.edge_id,
                source: e.source_node_id,
                sourceHandle: e.source_handle || undefined,
                target: e.target_node_id,
                targetHandle: e.target_handle || undefined,
                type: 'default',
                style: { stroke: '#94a3b8', strokeWidth: 1.5 },
            }));
            this.isDirty = false;
            this.selectedNodeId = null;
        },

        // === Node Types ===
        async fetchNodeTypes() {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            if (this.nodeTypes.length > 0) return;
            this.nodeTypesLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-pipelines/node-types`);
                this.nodeTypes = response.data.data;
            } catch (error) { throw error; }
            finally { this.nodeTypesLoading = false; }
        },

        // === Execution ===
        async executePipeline(pipelineId, inputData = null) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.executing = true;
            try {
                const response = await axios.post(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/execute`, {
                    input_data: inputData,
                });
                this.currentRun = response.data.data;
                return this.currentRun;
            } catch (error) { throw error; }
            finally { this.executing = false; }
        },

        async fetchRuns(pipelineId, params = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            this.runsLoading = true;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/runs`, { params });
                this.runs = response.data.data;
            } catch (error) { throw error; }
            finally { this.runsLoading = false; }
        },

        async previewNode(pipelineId, nodeId, inputs = {}) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.post(
                    `/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/nodes/${nodeId}/preview`,
                    { inputs }
                );
                return response.data.data;
            } catch (error) { throw error; }
        },

        async fetchRunStatus(pipelineId, runId) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            try {
                const response = await axios.get(`/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/runs/${runId}`);
                this.currentRun = response.data.data;
                return this.currentRun;
            } catch (error) { throw error; }
        },

        // === Local state management ===
        setSelectedNode(nodeId) {
            this.selectedNodeId = nodeId;
        },

        updateNodeData(nodeId, data) {
            const node = this.nodes.find(n => n.id === nodeId);
            if (node) {
                node.data = { ...node.data, ...data };
                this.isDirty = true;
            }
        },

        addNode(node) {
            this.nodes.push(node);
            this.isDirty = true;
        },

        removeNode(nodeId) {
            this.nodes = this.nodes.filter(n => n.id !== nodeId);
            this.edges = this.edges.filter(e => e.source !== nodeId && e.target !== nodeId);
            if (this.selectedNodeId === nodeId) this.selectedNodeId = null;
            this.isDirty = true;
        },

        addEdge(edge) {
            this.edges.push(edge);
            this.isDirty = true;
        },

        removeEdge(edgeId) {
            this.edges = this.edges.filter(e => e.id !== edgeId);
            this.isDirty = true;
        },

        updateNodePreviewData(nodeId, imageUrl) {
            this.nodePreviewData[nodeId] = imageUrl;
        },

        clearNodePreviewData() {
            this.nodePreviewData = {};
        },

        async uploadNodeImage(pipelineId, file) {
            const brandId = this.currentBrandId;
            if (!brandId) return;
            const formData = new FormData();
            formData.append('image', file);
            const response = await axios.post(
                `/api/v1/brands/${brandId}/sm-pipelines/${pipelineId}/upload-image`,
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );
            return response.data.data;
        },

        populatePreviewsFromRun(run) {
            if (!run?.node_results) return;
            Object.entries(run.node_results).forEach(([nodeId, result]) => {
                if (result?.image_url) {
                    this.nodePreviewData[nodeId] = result.image_url;
                } else if (result?.image_path) {
                    this.nodePreviewData[nodeId] = `/storage/${result.image_path}`;
                }
            });
        },

        resetEditor() {
            this.currentPipeline = null;
            this.nodes = [];
            this.edges = [];
            this.selectedNodeId = null;
            this.isDirty = false;
            this.currentRun = null;
            this.runs = [];
            this.nodePreviewData = {};
        },
    },
});
