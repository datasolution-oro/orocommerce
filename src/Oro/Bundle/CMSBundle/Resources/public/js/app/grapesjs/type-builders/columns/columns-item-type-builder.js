import __ from 'orotranslation/js/translator';
import BaseTypeBuilder from 'orocms/js/app/grapesjs/type-builders/base-type-builder';
import ColumnsStyleManagerService from './services/columns-style-manager-service';
import columnsResizer from './mixins/columns-resizer';
import columnComponent from './mixins/column-component';
import columnType from './mixins/column-type';

const ColumnsItemTypeBuilder = BaseTypeBuilder.extend({
    modelMixin: {
        defaults: {
            classes: ['oro-css-grid-col'],
            privateClasses: ['oro-css-grid-col'],
            draggable: '[data-gjs-type="columns"]',
            name: __('oro.cms.wysiwyg.component.columns_item.label'),
            unstylable: [
                'width', 'height', 'max-width', 'min-height', 'float',
                'display', 'label-parent-flex', 'flex-direction', 'justify-content',
                'align-items', 'flex', 'align-self', 'order'
            ],
            resizable: columnsResizer
        },

        init() {
            this.styleManager = new ColumnsStyleManagerService({
                model: this,
                editor: this.editor
            });
        },

        ...columnComponent
    },

    viewMixin: {
        onRender() {
            this.$el.css('min-height', 50);
        },

        getComputedSpan() {
            const span = getComputedStyle(this.el).getPropertyValue('grid-column-end');

            if (span.startsWith('span')) {
                return parseInt(span.replace('span ', ''));
            }

            return 1;
        }
    },

    ...columnType,

    constructor: function ColumnsItemTypeBuilder(...args) {
        ColumnsItemTypeBuilder.__super__.constructor.apply(this, args);
    },

    isComponent(el) {
        return el.nodeType === Node.ELEMENT_NODE &&
            el.parentElement.classList.contains('oro-css-grid') &&
            !el.classList.contains('oro-css-grid') &&
            [...el.classList].some(cls => cls.startsWith('oro-css-grid-col'));
    }
});

export default ColumnsItemTypeBuilder;