import {
	IAuthenticateGeneric,
	ICredentialTestRequest,
	ICredentialType,
	INodeProperties,
} from 'n8n-workflow';

export class AiselloApi implements ICredentialType {
	name = 'aiselloApi';
	displayName = 'Aisello API';
	documentationUrl = 'https://aisello.com';
	properties: INodeProperties[] = [
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

	authenticate: IAuthenticateGeneric = {
		type: 'generic',
		properties: {
			headers: {
				Authorization: '=Bearer {{$credentials.apiToken}}',
			},
		},
	};

	test: ICredentialTestRequest = {
		request: {
			baseURL: '={{$credentials.apiUrl}}',
			url: '/api/v1/user',
			method: 'GET',
		},
	};
}
// dodaj nowe bloki dla n8n brakujace ktore dodalismy
