import { Extension } from '@tiptap/core';

/** Background colour and vertical alignment for table cells. */
export const TableCellStyle = Extension.create({
    name: 'tableCellStyle',

    addGlobalAttributes() {
        return [{
            types: ['tableCell', 'tableHeader'],
            attributes: {
                backgroundColor: {
                    default: null,
                    parseHTML: (el) => el.style.backgroundColor || null,
                    renderHTML: (attrs) => (attrs.backgroundColor ? { style: `background-color: ${attrs.backgroundColor}` } : {}),
                },
                verticalAlign: {
                    default: null,
                    parseHTML: (el) => el.style.verticalAlign || null,
                    renderHTML: (attrs) => (attrs.verticalAlign ? { style: `vertical-align: ${attrs.verticalAlign}` } : {}),
                },
            },
        }];
    },

    addCommands() {
        return {
            setCellBackground: (color) => ({ commands }) => commands.setCellAttribute('backgroundColor', color),
            setCellVerticalAlign: (value) => ({ commands }) => commands.setCellAttribute('verticalAlign', value),
        };
    },
});
