"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloApi = void 0;
class AiselloApi {
    constructor() {
        this.name = 'aiselloApi';
        this.displayName = 'Aisello API';
        this.documentationUrl = 'https://aisello.com';
        this.properties = [
            {
                displayName: 'API URL',
                name: 'apiUrl',
                type: 'string',
                default: 'https://aisello.com',
                placeholder: 'https://aisello.com',
                description: 'Base URL of the Aisello instance (without trailing slash)',
            },
            {
                displayName: 'API Token',
                name: 'apiToken',
                type: 'string',
                typeOptions: { password: true },
                default: '',
                required: true,
                description: 'Sanctum personal access token from Settings > API Tokens',
            },
        ];
        this.authenticate = {
            type: 'generic',
            properties: {
                headers: {
                    Authorization: '=Bearer {{$credentials.apiToken}}',
                },
            },
        };
        this.test = {
            request: {
                baseURL: '={{$credentials.apiUrl}}',
                url: '/api/v1/user',
                method: 'GET',
            },
        };
    }
}
exports.AiselloApi = AiselloApi;
// dodaj nowe bloki dla n8n brakujace ktore dodalismy
