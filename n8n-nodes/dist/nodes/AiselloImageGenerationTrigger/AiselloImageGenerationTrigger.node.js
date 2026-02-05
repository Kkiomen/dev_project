"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloImageGenerationTrigger = void 0;
class AiselloImageGenerationTrigger {
    constructor() {
        this.description = {
            displayName: 'Aisello Image Generation Trigger',
            name: 'aiselloImageGenerationTrigger',
            icon: 'file:aisello.svg',
            group: ['trigger'],
            version: 1,
            subtitle: 'Receives image generation requests',
            description: 'Starts the workflow when Aisello requests image generation for a post. Use with AI image nodes (DALL-E, Stable Diffusion, etc.) and finish with Aisello Respond node.',
            defaults: { name: 'Image Generation Trigger' },
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
                    path: 'aisello-image-generation',
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
exports.AiselloImageGenerationTrigger = AiselloImageGenerationTrigger;
