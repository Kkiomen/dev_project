<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Mention from '@tiptap/extension-mention';
import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    editable: { type: Boolean, default: true },
    minHeight: { type: String, default: '150px' },
});

const emit = defineEmits(['update:modelValue', 'mention', 'blur']);

const { t } = useI18n();

const editor = useEditor({
    content: props.modelValue,
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-blue-600 hover:underline',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Mention.configure({
            HTMLAttributes: {
                class: 'mention',
            },
            suggestion: {
                items: ({ query }) => {
                    return props.users
                        .filter(user =>
                            user.name.toLowerCase().includes(query.toLowerCase())
                        )
                        .slice(0, 5);
                },
                render: () => {
                    let component;
                    let popup;

                    return {
                        onStart: (mentionProps) => {
                            component = document.createElement('div');
                            component.className = 'mention-suggestions bg-white shadow-lg rounded-lg border border-gray-200 py-1 min-w-[200px]';

                            mentionProps.items.forEach((item, index) => {
                                const button = document.createElement('button');
                                button.className = 'w-full text-left px-3 py-2 text-sm hover:bg-gray-100 flex items-center gap-2';
                                button.innerHTML = `
                                    <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium">
                                        ${item.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span>${item.name}</span>
                                `;
                                button.onclick = () => mentionProps.command({ id: item.id, label: item.name });
                                component.appendChild(button);
                            });

                            if (mentionProps.items.length === 0) {
                                component.innerHTML = `<div class="px-3 py-2 text-sm text-gray-500">${t('devTasks.mentions.noUsers')}</div>`;
                            }

                            popup = tippy('body', {
                                getReferenceClientRect: mentionProps.clientRect,
                                appendTo: () => document.body,
                                content: component,
                                showOnCreate: true,
                                interactive: true,
                                trigger: 'manual',
                                placement: 'bottom-start',
                            });
                        },
                        onUpdate: (mentionProps) => {
                            component.innerHTML = '';

                            mentionProps.items.forEach((item) => {
                                const button = document.createElement('button');
                                button.className = 'w-full text-left px-3 py-2 text-sm hover:bg-gray-100 flex items-center gap-2';
                                button.innerHTML = `
                                    <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium">
                                        ${item.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span>${item.name}</span>
                                `;
                                button.onclick = () => mentionProps.command({ id: item.id, label: item.name });
                                component.appendChild(button);
                            });

                            if (mentionProps.items.length === 0) {
                                component.innerHTML = `<div class="px-3 py-2 text-sm text-gray-500">${t('devTasks.mentions.noUsers')}</div>`;
                            }

                            popup[0].setProps({
                                getReferenceClientRect: mentionProps.clientRect,
                            });
                        },
                        onKeyDown: (mentionProps) => {
                            if (mentionProps.event.key === 'Escape') {
                                popup[0].hide();
                                return true;
                            }
                            return false;
                        },
                        onExit: () => {
                            popup[0].destroy();
                        },
                    };
                },
            },
        }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
    onBlur: () => {
        emit('blur');
    },
});

watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.getHTML() !== newValue) {
        editor.value.commands.setContent(newValue, false);
    }
});

watch(() => props.editable, (newValue) => {
    if (editor.value) {
        editor.value.setEditable(newValue);
    }
});

const setLink = () => {
    const previousUrl = editor.value.getAttributes('link').href;
    const url = window.prompt(t('devTasks.editor.enterUrl'), previousUrl);

    if (url === null) return;

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="rich-text-editor border border-gray-200 rounded-lg overflow-hidden focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
        <!-- Toolbar -->
        <div v-if="editable && editor" class="toolbar flex items-center gap-1 px-2 py-1.5 border-b border-gray-200 bg-gray-50">
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'bg-gray-200': editor.isActive('bold') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.bold')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'bg-gray-200': editor.isActive('italic') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.italic')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="{ 'bg-gray-200': editor.isActive('strike') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.strikethrough')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-9v18" />
                </svg>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1" />

            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-gray-200': editor.isActive('heading', { level: 2 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                :title="t('devTasks.editor.heading')"
            >
                H2
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="{ 'bg-gray-200': editor.isActive('heading', { level: 3 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                :title="t('devTasks.editor.heading')"
            >
                H3
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1" />

            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-gray-200': editor.isActive('bulletList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.bulletList')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-gray-200': editor.isActive('orderedList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.orderedList')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleCodeBlock().run()"
                :class="{ 'bg-gray-200': editor.isActive('codeBlock') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.code')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1" />

            <button
                type="button"
                @click="setLink"
                :class="{ 'bg-gray-200': editor.isActive('link') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.link')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-gray-200': editor.isActive('blockquote') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                :title="t('devTasks.editor.quote')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
            </button>
        </div>

        <!-- Editor content -->
        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none px-3 py-2"
            :style="{ minHeight: minHeight }"
        />
    </div>
</template>

<style>
.rich-text-editor .ProseMirror {
    outline: none;
    min-height: inherit;
}

.rich-text-editor .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}

.rich-text-editor .mention {
    background-color: #dbeafe;
    border-radius: 0.25rem;
    padding: 0.125rem 0.25rem;
    color: #2563eb;
    font-weight: 500;
}

.rich-text-editor .ProseMirror h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.rich-text-editor .ProseMirror h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}

.rich-text-editor .ProseMirror ul {
    list-style-type: disc;
    padding-left: 1.5rem;
}

.rich-text-editor .ProseMirror ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
}

.rich-text-editor .ProseMirror blockquote {
    border-left: 3px solid #e5e7eb;
    padding-left: 1rem;
    color: #6b7280;
    font-style: italic;
}

.rich-text-editor .ProseMirror pre {
    background-color: #1f2937;
    color: #f3f4f6;
    padding: 0.75rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    font-family: monospace;
}

.rich-text-editor .ProseMirror code {
    background-color: #f3f4f6;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-family: monospace;
    font-size: 0.875em;
}

.rich-text-editor .ProseMirror pre code {
    background-color: transparent;
    padding: 0;
}

.mention-suggestions {
    z-index: 9999;
}
</style>
