import __ from 'orotranslation/js/translator';
import BaseTypeBuilder from 'orocms/js/app/grapesjs/type-builders/base-type-builder';
import openDigitalAssetsCommand from 'orocms/js/app/grapesjs/modules/open-digital-assets-command';

const ImageTypeBuilder = BaseTypeBuilder.extend({
    parentType: 'image',

    modelMixin: {
        defaults: {
            tagName: 'img',
            previewMetadata: {}
        }
    },

    viewMixin: {
        onActive: function(e) {
            if (e) {
                e.stopPropagation();
            }

            if (this.model.get('editable')) {
                this._openDigitalAssetManager(this.model);
            }
        },

        _openDigitalAssetManager: function(digitalAssetImageComponentModel) {
            this.em.get('Commands').run(
                'open-digital-assets',
                {
                    target: digitalAssetImageComponentModel,
                    title: __('oro.cms.wysiwyg.digital_asset.image.title'),
                    routeName: 'oro_digital_asset_widget_choose_image',
                    onSelect: function(digitalAssetModel) {
                        digitalAssetImageComponentModel.set(
                            'previewMetadata',
                            digitalAssetModel.get('previewMetadata')
                        );
                        const {url, title} = digitalAssetImageComponentModel.get('previewMetadata');

                        digitalAssetImageComponentModel.set('src', url).addAttributes({
                            alt: title || ''
                        });
                    }
                }
            );
        }
    },

    commands: {
        'open-digital-assets': openDigitalAssetsCommand
    },

    constructor: function ImageTypeBuilder(options) {
        ImageTypeBuilder.__super__.constructor.call(this, options);
    },

    createPanelButton() {
        this.editor.BlockManager.remove(this.componentType);
    },

    registerEditorCommands() {
        if (this.editor.Commands.has('open-digital-assets')) {
            return;
        }

        ImageTypeBuilder.__super__.registerEditorCommands.call(this);
    }
});

export default ImageTypeBuilder;
