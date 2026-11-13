import { Node, mergeAttributes, wrappingInputRule } from '@tiptap/core';

export const CALLOUT_TYPES = ['info', 'tip', 'success', 'warning', 'danger', 'note'];

export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            type: {
                default: 'info',
                parseHTML: (el) => (CALLOUT_TYPES.includes(el.getAttribute('data-callout')) ? el.getAttribute('data-callout') : 'info'),
                renderHTML: (attrs) => ({ 'data-callout': attrs.type }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-callout]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { class: 'de-callout' }), 0];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            const dom = document.createElement('div');
            dom.className = 'de-callout';
            dom.dataset.callout = node.attrs.type;
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'de-callout-badge';
            badge.contentEditable = 'false';
            badge.title = 'Change callout type';
            badge.addEventListener('mousedown', (event) => event.preventDefault());
            badge.addEventListener('click', () => {
                if (!editor.isEditable) return;
                const pos = getPos();
                const next = CALLOUT_TYPES[(CALLOUT_TYPES.indexOf(dom.dataset.callout) + 1) % CALLOUT_TYPES.length];
                editor.view.dispatch(editor.state.tr.setNodeAttribute(pos, 'type', next));
            });
            const content = document.createElement('div');
            content.className = 'de-callout-content';
            dom.append(badge, content);

            return {
                dom,
                contentDOM: content,
                update(updated) {
                    if (updated.type.name !== 'callout') return false;
                    dom.dataset.callout = updated.attrs.type;
                    return true;
                },
            };
        };
    },

    addCommands() {
        return {
            setCallout: (type = 'info') => ({ commands }) => commands.wrapIn(this.name, { type }),
            toggleCallout: (type = 'info') => ({ commands, editor }) => {
                if (editor.isActive(this.name, { type })) return commands.lift(this.name);
                if (editor.isActive(this.name)) return commands.updateAttributes(this.name, { type });

                return commands.wrapIn(this.name, { type });
            },
            unsetCallout: () => ({ commands }) => commands.lift(this.name),
        };
    },

    addInputRules() {
        return [
            wrappingInputRule({
                find: /^:::(info|tip|success|warning|danger|note)\s$/,
                type: this.type,
                getAttributes: (match) => ({ type: match[1] }),
            }),
        ];
    },
});
