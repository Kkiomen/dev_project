import { IExecuteFunctions, ILoadOptionsFunctions, INodeExecutionData, INodePropertyOptions, INodeType, INodeTypeDescription } from 'n8n-workflow';
export declare class AiselloDatabase implements INodeType {
    description: INodeTypeDescription;
    methods: {
        loadOptions: {
            getBases(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
            getTables(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
            getFields(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
        };
    };
    execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]>;
}
//# sourceMappingURL=AiselloDatabase.node.d.ts.map