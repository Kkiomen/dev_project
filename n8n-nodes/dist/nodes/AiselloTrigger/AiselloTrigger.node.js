"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloTrigger = void 0;
class AiselloTrigger {
    constructor() {
        this.description = {
            displayName: 'Aisello Trigger',
            name: 'aiselloTrigger',
            icon: 'file:aisello.svg',
            group: ['trigger'],
            version: 1,
            subtitle: 'Receives publishing result callbacks',
            description: 'Starts the workflow when Aisello sends a publishing result webhook',
            defaults: { name: 'Aisello Trigger' },
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
                    path: 'aisello-webhook',
                },
            ],
            properties: [
                {
                    displayName: 'Webhook Secret',
                    name: 'webhookSecret',
                    type: 'string',
                    typeOptions: { password: true },
                    default: '',
                    description: 'Optional secret to validate X-Webhook-Secret header. Leave empty to skip validation.',
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
                    webhookResponse: 'Unauthorized',
                    noWebhookResponse: true,
                };
            }
        }
        return {
            workflowData: [
                this.helpers.returnJsonArray(body),
            ],
        };
    }
}
exports.AiselloTrigger = AiselloTrigger;
