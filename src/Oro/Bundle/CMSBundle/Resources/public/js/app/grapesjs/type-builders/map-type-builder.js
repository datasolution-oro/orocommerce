import _ from 'underscore';
import __ from 'orotranslation/js/translator';
import BaseTypeBuilder from 'orocms/js/app/grapesjs/type-builders/base-type-builder';

const MapTypeBuilder = BaseTypeBuilder.extend({
    constructor: function MapTypeBuilder(options) {
        MapTypeBuilder.__super__.constructor.call(this, options);
    },

    initialize(options) {
        Object.assign(this, _.pick(options, 'editor', 'componentType'));
    },

    execute() {
        const {BlockManager} = this.editor;
        BlockManager.add(this.componentType, {
            label: __('oro.cms.wysiwyg.component.map.label'),
            category: 'Basic',
            select: true,
            attributes: {
                'class': 'fa fa-map-o'
            },
            content: {
                type: 'map',
                style: {
                    height: '350px',
                    width: '100%'
                }
            }
        });
    }
}, {
    isAllowed(options) {
        const {componentType, editor} = options;
        const mapModel = editor.Components.getType(componentType).model;

        return editor.ComponentRestriction.isAllowedDomain(mapModel.prototype.defaults.mapUrl);
    }
});

export default MapTypeBuilder;
