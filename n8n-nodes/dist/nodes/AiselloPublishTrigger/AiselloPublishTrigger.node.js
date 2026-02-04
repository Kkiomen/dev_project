"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloPublishTrigger = void 0;
class AiselloPublishTrigger {
    constructor() {
        this.description = {
            displayName: 'Aisello Publish Trigger',
            name: 'aiselloPublishTrigger',
            icon: 'file:aisello.svg',
            group: ['trigger'],
            version: 1,
            subtitle: 'Receives publish requests',
            description: 'Starts the workflow when Aisello sends a post for publishing. Handle publishing to your platforms (Facebook, Instagram, etc.) and finish with Aisello Respond node.',
            defaults: { name: 'Publish Trigger' },
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
                    responseCode: '202',
                    path: 'aisello-publish',
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
exports.AiselloPublishTrigger = AiselloPublishTrigger;
