"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloTextGenerationTrigger = void 0;
class AiselloTextGenerationTrigger {
    constructor() {
        this.description = {
            displayName: 'Aisello Text Generation Trigger',
            name: 'aiselloTextGenerationTrigger',
            icon: 'file:aisello.svg',
            group: ['trigger'],
            version: 1,
            subtitle: 'Receives text generation requests',
            description: 'Starts the workflow when Aisello requests text generation for a post. IMPORTANT: Copy the Webhook URL below and paste it in Aisello brand webhook settings.',
            defaults: { name: 'Text Generation Trigger' },
            inputs: [],
            outputs: ['main'],
            credentials: [
                {
                    name: 'aiselloApi',
                    required: false,
                },
            ],
            webhooks: [
                {
                    name: 'default',
                    httpMethod: 'POST',
                    responseMode: 'onReceived',
                    path: 'aisello-text-generation',
                },
            ],
            properties: [
                {
                    displayName: 'Webhook Secret',
                    name: 'webhookSecret',
                    type: 'string',
                    typeOptions: { password: true },
                    default: '',
                    description: 'Optional secret to validate X-Webhook-Secret header. Must match the secret configured in Aisello brand settings.',
                },
            ],
        };
    }
    async webhook() {
        const req = this.getRequestObject();
        const body = this.getBodyData();
        // Validate webhook secret if configured
        const webhookSecret = this.getNodeParameter('webhookSecret', '');
        if (webhookSecret) {
            const headerSecret = req.headers['x-webhook-secret'];
            if (headerSecret !== webhookSecret) {
                return {
                    webhookResponse: JSON.stringify({ error: 'Unauthorized' }),
                    noWebhookResponse: true,
                };
            }
        }
        // Return 202 Accepted immediately, workflow continues async
        return {
            workflowData: [
                this.helpers.returnJsonArray(body),
            ],
        };
    }
}
exports.AiselloTextGenerationTrigger = AiselloTextGenerationTrigger;
