<?php

return [
    'layer_types' => [
        'text' => 'Text',
        'textbox' => 'Text Box',
        'image' => 'Image',
        'rectangle' => 'Rectangle',
        'ellipse' => 'Ellipse',
        'line' => 'Line',
        'group' => 'Group',
    ],

    'templates' => [
        'title' => 'Templates',
        'create' => 'Create Template',
        'edit' => 'Edit Template',
        'duplicate' => 'Duplicate Template',
        'delete' => 'Delete Template',
        'delete_confirm' => 'Are you sure you want to delete this template?',
        'duplicated' => 'Template duplicated successfully.',
        'saved' => 'Template saved successfully.',
        'deleted' => 'Template deleted successfully.',
        'newFromGroup' => 'Template from Group',
    ],

    'layers' => [
        'title' => 'Layers',
        'add' => 'Add Layer',
        'delete' => 'Delete Layer',
        'visible' => 'Visible',
        'locked' => 'Locked',
        'properties' => 'Properties',
        'notAGroup' => 'Selected layer is not a group',
        'groupEmpty' => 'Group has no children layers',
        'createTemplateFromGroup' => 'Create Template from Group',
    ],

    'editor' => [
        'title' => 'Graphics Editor',
        'save' => 'Save',
        'export' => 'Export',
        'download' => 'Download',
        'upload' => 'Upload to Server',
        'undo' => 'Undo',
        'redo' => 'Redo',
        'zoom_in' => 'Zoom In',
        'zoom_out' => 'Zoom Out',
        'fit_to_screen' => 'Fit to Screen',
    ],

    'canvas' => [
        'title' => 'Canvas',
        'settings' => 'Canvas Settings',
        'size' => 'Size',
        'width' => 'Width',
        'height' => 'Height',
        'background_color' => 'Background Color',
        'background_image' => 'Background Image',
    ],

    'properties' => [
        'position' => 'Position',
        'size' => 'Size',
        'rotation' => 'Rotation',
        'opacity' => 'Opacity',
        'fill' => 'Fill',
        'stroke' => 'Stroke',
        'stroke_width' => 'Stroke Width',
        'corner_radius' => 'Corner Radius',
        'font_family' => 'Font Family',
        'font_size' => 'Font Size',
        'font_weight' => 'Font Weight',
        'font_style' => 'Font Style',
        'text_align' => 'Text Align',
        'line_height' => 'Line Height',
        'letter_spacing' => 'Letter Spacing',
        'text_direction' => 'Text Direction',
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
    ],

    'export' => [
        'title' => 'Export Image',
        'format' => 'Format',
        'quality' => 'Quality',
        'scale' => 'Scale',
        'filename' => 'Filename',
    ],

    'fonts' => [
        'title' => 'Fonts',
        'upload' => 'Upload Font',
        'delete' => 'Delete Font',
        'supported_formats' => 'Supported formats: TTF, OTF, WOFF, WOFF2',
    ],

    'aiChat' => [
        'title' => 'AI Assistant',
        'toggle' => 'Toggle AI Chat',
        'placeholder' => 'Ask me to create or modify your template...',
        'send' => 'Send',
        'thinking' => 'Thinking...',
        'error' => 'Something went wrong. Please try again.',
        'welcomeMessage' => 'Hi! I can help you create and modify templates. Try asking me to:',
        'enterHint' => 'Press Enter to send, Shift+Enter for new line',
        'clearHistory' => 'Clear chat history',
        'suggestions' => [
            'createInstagram' => 'Create an Instagram post template',
            'changeText' => 'Change the text to something else',
            'addShape' => 'Add a background shape',
            'apiHelp' => 'How do I use the API to generate images?',
        ],
        'actions' => [
            'layerModified' => 'Layer ":name" modified',
            'layerAdded' => 'New layer ":name" added',
            'layerDeleted' => 'Layer ":name" deleted',
            'templateUpdated' => 'Template updated',
        ],
    ],

    'psd' => [
        'errors' => [
            'uploadFailed' => 'Failed to import PSD file',
            'analyzeFailed' => 'Failed to analyze PSD file',
            'parseFailed' => 'Failed to parse PSD file',
        ],
    ],

    'semantic_tags' => [
        'header' => 'Header',
        'subtitle' => 'Subtitle',
        'paragraph' => 'Paragraph',
        'url' => 'URL / Link',
        'social_handle' => 'Social Handle (@)',
        'main_image' => 'Main Image',
        'logo' => 'Logo',
        'cta' => 'Call to Action',
        'primary_color' => 'Primary Color',
        'secondary_color' => 'Secondary Color',
        'text_primary_color' => 'Text Primary Color',
        'text_secondary_color' => 'Text Secondary Color',
        'content_tag' => 'Content Tag',
        'style_tag' => 'Style Tag',
        'no_content_tag' => 'No content tag',
        'no_style_tag' => 'No style tag',
    ],

    'template_preview' => [
        'no_tagged_templates' => 'No templates with semantic tags found in library',
        'render_failed' => 'Failed to generate preview',
    ],

    'library' => [
        'groupTemplateCreated' => 'New template created from group',
    ],

    'apiDocs' => [
        'title' => 'API Documentation',
        'howItWorks' => 'How it works',
        'howItWorksDesc' => 'Use the API to programmatically generate images with custom content',
        'copy' => 'Copy',
        'generateEndpoint' => 'Generate Endpoint',
        'modifiableLayers' => 'Modifiable Layers',
        'key' => 'Key',
        'noModifiableLayers' => 'No modifiable layers in this template',
        'requestBody' => 'Request Body',
        'optionalParams' => 'Optional parameters',
        'optionalParamsDesc' => 'format (png/jpeg/webp), quality (1-100), scale (1-4, default: 2 for retina)',
        'response' => 'Response',
        'curlCommand' => 'cURL Command',
    ],
];
