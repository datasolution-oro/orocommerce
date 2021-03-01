import $ from 'jquery';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';
import grapesJS from 'grapesjs';

import BaseView from 'oroui/js/app/views/base/view';
import styleManagerModule from 'orocms/js/app/grapesjs/modules/style-manager-module';
import PanelManagerModule from 'orocms/js/app/grapesjs/modules/panels-module';
import DevicesModule from 'orocms/js/app/grapesjs/modules/devices-module';
import mediator from 'oroui/js/mediator';
import canvasStyle from 'orocms/js/app/grapesjs/modules/canvas-style';

import 'grapesjs-preset-webpage';
import parserPostCSS from 'grapesjs-parser-postcss';
import 'orocms/js/app/grapesjs/plugins/components/grapesjs-components';
import 'orocms/js/app/grapesjs/plugins/import/import';
import 'orocms/js/app/grapesjs/plugins/code/code';
import 'orocms/js/app/grapesjs/plugins/panel-scrolling-hints';
import {escapeWrapper, getWrapperAttrs} from 'orocms/js/app/grapesjs/plugins/grapesjs-style-isolation';
import i18nMessages from 'orocms/js/app/grapesjs/plugins/i18n-messages';
import ContentParser from 'orocms/js/app/grapesjs/plugins/grapesjs-content-parser';
import moduleConfig from 'module-config';

const MIN_EDITOR_WIDTH = 1100;
const LOCK_PASTE_ATTR = 'data-lock-paste';

const config = {
    allowBreakpoints: [],
    disableDeviceManager: false,
    ...moduleConfig(module.id)
};

/**
 * Create grapesJS content builder
 * @type {*|void}
 */
const GrapesjsEditorView = BaseView.extend({
    /**
     * @inheritDoc
     */
    optionNames: BaseView.prototype.optionNames.concat([
        'autoRender', 'allow_tags', 'allowed_iframe_domains', 'builderPlugins', 'currentTheme', 'canvasConfig',
        'contextClass', 'storageManager', 'stylesInputSelector', 'storagePrefix', 'themes',
        'entityClass', 'disableDeviceManager'
    ]),

    /**
     * @inheritDoc
     */
    autoRender: true,

    /**
     * Active style theme for iframe
     * @property {String}
     */
    activeTheme: null,

    /**
     * @property {grapesJS.Instance}
     */
    builder: null,

    /**
     * Allow html tags
     * @property {Object}
     */
    allow_tags: null,

    /**
     * Allow iframe domains
     * @property {Array}
     */
    allowed_iframe_domains: [],

    /**
     * Page context class
     * @property {String}
     */
    contextClass: 'body cms-page cms-typography',

    /**
     * State of changes in editor
     * @property {Boolean}
     */
    componentUpdated: false,

    entityClass: null,

    /**
     * Main builder options
     * @property {Object}
     */
    builderOptions: {
        height: '700px',
        avoidInlineStyle: true,
        avoidFrameOffset: true,
        allowScripts: 1,
        wrapperIsBody: 0,
        exportWrapper: 0,
        pasteStyles: false,
        requestParams: {},
        noticeOnUnload: false,
        cssIcons: false,

        /**
         * Color picker options
         * @property {Object}
         */
        colorPicker: {
            appendTo: 'body',
            showPalette: false,
            chooseText: __('oro.cms.wysiwyg.color_picker.choose_text'),
            cancelText: __('oro.cms.wysiwyg.color_picker.cancel_text')
        },

        /**
         * Modal Export Title text
         */
        textViewCode: __('oro.cms.wysiwyg.export.title')
    },

    /**
     * Storage prefix
     * @property {String}
     */
    storagePrefix: 'gjs-',

    /**
     * Storage options
     * @property {Object}
     */
    storageManager: {
        autosave: false,
        autoload: false
    },

    /**
     * Configurations for Trait Manager
     * @property {Object}
     */
    traitManager: {
        optionsTarget: [{
            value: '_self'
        }, {
            value: '_blank'
        }]
    },

    /**
     * Canvas options
     * @property {Object}
     */
    canvasConfig: {
        canvasCss: canvasStyle
    },

    /**
     * Style manager options
     * @property {Object}
     */
    styleManager: {
        clearProperties: 1
    },

    /**
     * Asset manager settings
     * @property {Object}
     */
    assetManagerConfig: {
        embedAsBase64: 1
    },

    /**
     * Themes list
     * @property {Array}
     */
    themes: [],

    /**
     * Styles input selector
     * @property {String}
     */
    stylesInputSelector: '[data-grapesjs-styles]',

    /**
     * Styles input element
     * @property {Object}
     */
    $stylesInputElement: null,

    /**
     * @property {String}
     */
    wrapperSelector: '.page-content-editor, .fallback-item-value, .content-variant-item',

    /**
     * @property {jQuery.Element}
     */
    $parent: null,

    /**
     * @property {Object}
     */
    rte: null,

    /**
     * Disable responsive design manager
     * @property {Boolean}
     */
    disableDeviceManager: config.disableDeviceManager,

    /**
     * Allow breakpoints list
     * @property {Array}
     */
    allowBreakpoints: config.allowBreakpoints,

    /**
     * List of grapesjs plugins
     * @property {Object}
     */
    builderPlugins: {
        'gjs-preset-webpage': {
            aviaryOpts: false,
            filestackOpts: null,
            formsOpts: {
                labelInputName: __('oro.cms.wysiwyg.forms.label_input_name'),
                labelTextareaName: __('oro.cms.wysiwyg.forms.label_textarea_name'),
                labelSelectName: __('oro.cms.wysiwyg.forms.label_select_name'),
                labelCheckboxName: __('oro.cms.wysiwyg.forms.label_checkbox_name'),
                labelRadioName: __('oro.cms.wysiwyg.forms.label_radio_name'),
                labelButtonName: __('oro.cms.wysiwyg.forms.label_button_name'),
                labelTypeText: __('oro.cms.wysiwyg.forms.label_type_text'),
                labelTypeEmail: __('oro.cms.wysiwyg.forms.label_type_email'),
                labelTypePassword: __('oro.cms.wysiwyg.forms.label_type_password'),
                labelTypeNumber: __('oro.cms.wysiwyg.forms.label_type_number'),
                labelTypeSubmit: __('oro.cms.wysiwyg.forms.label_type_submit'),
                labelTypeReset: __('oro.cms.wysiwyg.forms.label_type_reset'),
                labelTypeButton: __('oro.cms.wysiwyg.forms.label_type_button'),
                labelNameLabel: __('oro.cms.wysiwyg.forms.label_name_label'),
                labelForm: __('oro.cms.wysiwyg.forms.label_form'),
                labelSelectOption: __('oro.cms.wysiwyg.forms.label_select_option'),
                labelOption: __('oro.cms.wysiwyg.forms.label_option'),
                labelStateNormal: __('oro.cms.wysiwyg.forms.label_state_normal'),
                labelStateSuccess: __('oro.cms.wysiwyg.forms.label_state_success'),
                labelStateError: __('oro.cms.wysiwyg.forms.label_state_error')
            },
            navbarOpts: false,
            countdownOpts: false,
            importViewerOptions: {},
            codeViewerOptions: {},
            exportOpts: {
                btnLabel: __('oro.cms.wysiwyg.export.btn_label')
            }
        },
        'grapesjs-components': {},
        'grapesjs-style-isolation': {},
        'grapesjs-import': {},
        'grapesjs-code': {},
        'grapesjs-panel-scrolling-hints': {}
    },

    events: {
        'wysiwyg:enable': 'enableEditor',
        'wysiwyg:disable': 'disableEditor'
    },

    /**
     * @inheritDoc
     */
    constructor: function GrapesjsEditorView(options) {
        GrapesjsEditorView.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     * @param options
     */
    initialize: function(options = {}) {
        this.builderOptions = {...this.builderOptions, ...options.builderOptions};
        this.setCurrentContentAlias();
        this.$parent = this.$el.closest(this.wrapperSelector);
        this.$stylesInputElement = this.$parent.find(this.stylesInputSelector);
        this.setAlternativeFields();
        this.setActiveTheme(this.getCurrentTheme());

        const extendOptions = {};

        if (this.allow_tags) {
            extendOptions.allowTags = this.allow_tags;
        }

        if (this.allowed_iframe_domains) {
            extendOptions.allowedIframeDomains = this.allowed_iframe_domains;
        }

        this.builderPlugins['grapesjs-components'] = _.extend({},
            this.builderPlugins['grapesjs-components'],
            extendOptions
        );

        this.builderPlugins['grapesjs-import'] = {
            ...this.builderPlugins['grapesjs-import'],
            entityClass: this.entityClass,
            fieldName: this.$el.attr('name')
        };

        GrapesjsEditorView.__super__.initialize.call(this, options);
    },

    /**
     * @inheritDoc
     */
    render: function() {
        if (_.isMobile() || _.isTouchDevice()) {
            this.message = mediator.execute('showFlashMessage', 'error', __('oro.cms.wysiwyg.mobile.flash_message'), {
                container: this.$el.parent(),
                hideCloseButton: true
            });

            this.$el.parent().addClass('editor-unavailable');

            return;
        }

        this.initContainer();
        this.initBuilder();
    },

    /**
     * @inheritDoc
     */
    dispose: function() {
        if (this.disposed) {
            return;
        }

        if (this._panelManagerModule) {
            this._panelManagerModule.dispose();
            delete this._panelManagerModule;
        }

        if (this._devicesModule) {
            this._devicesModule.dispose();
            delete this._devicesModule;
        }

        this.disableEditor();
        GrapesjsEditorView.__super__.dispose.call(this);
    },

    /**
     * Set disable editor
     */
    disableEditor: function() {
        if (this.builder) {
            this.builder.trigger('destroy');
            this.builderUndelegateEvents();
            this.builder.destroy();

            this.disposeElements();

            this.builder = null;
        }
    },

    /**
     * Set enable editor
     */
    enableEditor: function() {
        if (!this.builder) {
            this.render();
        }
    },

    disposeElements: function() {
        this.$el.show();
        this.$container.remove();
    },

    /**
     * Creates editor container
     * @returns {*}
     */
    initContainer: function() {
        this.$container = $('<div class="grapesjs" data-skip-input-widgets />');
        this.$container.insertAfter(this.$el);
    },

    /**
     * Initialize builder instance
     */
    initBuilder: function() {
        this.builder = grapesJS.init({
            avoidInlineStyle: 1,
            container: this.$container.get(0),
            ...this._prepareBuilderOptions()
        });

        this.builder.setComponents(escapeWrapper(this.$el.val()));

        const wrapperAttrs = getWrapperAttrs(this.$el.val());
        if (!_.isEmpty(wrapperAttrs)) {
            wrapperAttrs.class && this.builder.getWrapper().addClass(wrapperAttrs.class);
        }

        // Ensures all changes to sectors, properties and types are applied.
        this.builder.StyleManager.getSectors().reset(styleManagerModule);
        this.rte = this.builder.RichTextEditor;

        const pureStyles = this.builder.getPureStyle(this.$stylesInputElement.val());

        this.builder.setStyle(pureStyles);

        mediator.trigger('grapesjs:created', this.builder);

        this.builderDelegateEvents();
    },

    /**
     * Add builder event listeners
     */
    builderDelegateEvents: function() {
        const canvas = this.builder.Canvas;

        this.$el.closest('form')
            .on(
                'keyup' + this.eventNamespace() + ' keypress' + this.eventNamespace()
                , e => {
                    const keyCode = e.keyCode || e.which;
                    if (keyCode === 13 && this.$container.get(0).contains(e.target)) {
                        e.preventDefault();
                        return false;
                    }
                })
            .on('submit', this.contentValidate.bind(this));

        this.$el.closest('.scrollable-container').on(`scroll${this.eventNamespace()}`, () => {
            this.builder.trigger('change:canvasOffset');
        });

        canvas.getCanvasView().$el.on(`scroll${this.eventNamespace()}`, e => {
            const $cvTools = $(e.target).find('#gjs-cv-tools');

            $cvTools.css({
                top: e.target.scrollTop
            });

            // Force recalculate highlight boxes positions;
            this.builder.trigger('frame:updated', {
                frame: canvas.model.get('frame')
            });
        });

        this.builder.on('load', this._onLoadBuilder.bind(this));
        this.builder.on('update', this._onUpdatedBuilder.bind(this));
        this.builder.on('component:update', _.debounce(this._onComponentUpdatedBuilder.bind(this), 100));
        this.builder.on('changeTheme', this._updateTheme.bind(this));
        this.builder.on('component:selected', this.componentSelected.bind(this));
        this.builder.on('component:deselected', this.componentDeselected.bind(this));
        this.builder.on('rteToolbarPosUpdate', this.updateRtePosition.bind(this));

        // Fix reload form when click export to zip dialog
        this.builder.on('run:export-template', () => {
            $(this.builder.Modal.getContentEl())
                .find('.gjs-btn-prim').on('click', e => {
                    e.preventDefault();
                });
        });

        $(this.builder.Canvas.getBody()).on(
            'paste',
            '[contenteditable="true"]',
            function(e) {
                // Prevent recursive call of "paste" event in IE11
                if (e.target.hasAttribute(LOCK_PASTE_ATTR)) {
                    e.target.removeAttribute(LOCK_PASTE_ATTR);
                } else if (!this.builderOptions.pasteStyles) {
                    e.preventDefault();

                    this.onPasteContent(e);
                }
            }.bind(this)
        );
    },

    /**
     * Remove builder event listeners
     */
    builderUndelegateEvents: function() {
        this.$el.closest('form').off(this.eventNamespace());
        this.$el.closest('.scrollable-container').off(this.eventNamespace());
        mediator.off('dropdown-button:click');

        const canvas = this.builder.Canvas;

        if (canvas) {
            canvas.getCanvasView().$el.off(this.eventNamespace());
            $(canvas.getBody()).off();
        }
        if (this.builder) {
            this.builder.off();
            this.builder.editor.view.$el.find('.gjs-toolbar').off('mouseover');
        }
    },

    /**
     * Get current theme
     * @returns {Object}
     */
    getCurrentTheme: function() {
        return _.find(this.themes, function(theme) {
            return theme.active;
        });
    },

    /**
     * Set active state for button
     * @param panel {String}
     * @param name {String}
     */
    setActiveButton: function(panel, name) {
        this.builder.Commands.run(name);
        const button = this.builder.Panels.getButton(panel, name);

        button.set('active', true);
    },

    setCurrentContentAlias: function() {
        this.form = this.$el.closest('form');
        const contentBlockAliasField = this.form.find('[name="oro_cms_content_block[alias]"]');
        if (contentBlockAliasField.length && contentBlockAliasField.val()) {
            this.builderOptions.contentBlockAlias = contentBlockAliasField.val();
        }
    },

    setAlternativeFields: function() {
        const fieldPrefix = this.$el.attr('data-ftid');
        const styleFiledName = fieldPrefix + '_style';

        if (!this.$stylesInputElement.length) {
            this.$stylesInputElement = this.form.find('[data-ftid="' + styleFiledName + '"]');
        }
    },

    /**
     * Validation by tags
     * @param e {Object}
     */
    contentValidate: function(e) {
        if (!this.allow_tags) {
            return;
        }

        const _res = this.builder.ComponentRestriction.validate(this.$el.val());
        const validationMessage = __('oro.cms.wysiwyg.validation.import', {tags: _res.join(', ')});

        if (_res.length) {
            e.preventDefault();
            mediator.execute('showFlashMessage', 'error', validationMessage, {
                delay: 5000
            });

            return false;
        }
    },

    /**
     * Get editor content
     * @returns {String}
     */
    getEditorContent: function() {
        return this.builder.getIsolatedHtml();
    },

    /**
     * Get editor styles
     * @returns {String}
     */
    getEditorStyles: function() {
        return this.builder.getIsolatedCss();
    },

    getToolbarItems() {
        return $(this.builder.editor.view.$el.find('.gjs-toolbar .gjs-toolbar-item'));
    },

    componentDeselected() {
        this.builder.editor.view.$el.find('.gjs-toolbar').off('mouseover');
        this.getToolbarItems().each(function() {
            const tooltip = $(this).data('bs.tooltip');

            if (tooltip) {
                tooltip.dispose();
            }
        });
    },

    componentSelected(model) {
        let toolbar = model.get('toolbar');

        if (_.isArray(toolbar)) {
            toolbar = toolbar.map(tool => {
                if (_.isFunction(tool.command) && !tool.attributes.label) {
                    tool.attributes.label = __('oro.cms.wysiwyg.toolbar.selectParent');

                    return tool;
                }

                switch (tool.command) {
                    case 'tlb-move':
                        tool.attributes.label = __('oro.cms.wysiwyg.toolbar.move');
                        break;
                    case 'tlb-clone':
                        tool.attributes.label = __('oro.cms.wysiwyg.toolbar.clone');
                        break;
                    case 'tlb-delete':
                        tool.attributes.label = __('oro.cms.wysiwyg.toolbar.delete');
                        break;
                }

                return tool;
            });

            model.set('toolbar', toolbar);
        }

        this.builder.editor.view.$el.find('.gjs-toolbar')
            .off('mouseover')
            .on('mouseover', '.gjs-toolbar-item', e => {
                $(e.target).tooltip({
                    title: $(e.target).attr('label') || ''
                });

                $(e.target).tooltip('show');
            });
    },

    /**
     * Add wrapper classes for iframe with content
     */
    _addClassForFrameWrapper: function() {
        $(this.builder.Canvas.getFrameEl().contentDocument).find('#wrapper').addClass(this.contextClass);
    },

    onPasteContent(e) {
        if (e.originalEvent.clipboardData) {
            const content = e.originalEvent.clipboardData.getData('text/plain');

            e.target.ownerDocument.execCommand('insertText', false, content);
        } else if (window.clipboardData) {
            const data = window.clipboardData.getData('Text');
            let content;

            try {
                content = JSON.parse(data).content;
            } catch (e) {
                content = data;
            }

            e.target.setAttribute(LOCK_PASTE_ATTR, '');
            e.target.ownerDocument.execCommand('paste', false, content);
        }
    },

    /**
     * Onload builder handler
     * @private
     */
    _onLoadBuilder: function() {
        this._panelManagerModule = new PanelManagerModule({
            builder: this.builder,
            themes: this.themes
        });

        if (!this.disableDeviceManager) {
            this._devicesModule = new DevicesModule({
                builder: this.builder,
                allowBreakpoints: this.allowBreakpoints
            });
        }

        this.setActiveButton('options', 'sw-visibility');
        this.setActiveButton('views', 'open-blocks');
        this._addClassForFrameWrapper();

        mediator.trigger('grapesjs:loaded', this.builder);
        mediator.trigger('page:afterChange');

        this.$el.closest('.ui-dialog-content').dialog('option', 'minWidth', MIN_EDITOR_WIDTH);
    },

    /**
     * Update builder handler
     * @private
     */
    _onUpdatedBuilder: function() {
        mediator.trigger('grapesjs:updated', this.builder);
        this._updateInitialField();
    },

    /**
     * Update components builder handler
     * @param state
     * @private
     */
    _onComponentUpdatedBuilder: function(state) {
        if (!this.componentUpdated) {
            mediator.on('dropdown-button:click', this._onComponentUpdatedBuilder, this);
        }
        this._updateInitialField();
        this.builder.trigger('change:canvasOffset');
        mediator.trigger('grapesjs:components:updated', state);
        this.componentUpdated = true;
    },

    /**
     * Update theme view in grapes iframe
     * @param selected {String}
     * @private
     */
    _updateTheme: function(selected) {
        if (!_.isUndefined(this.activeTheme) && this.activeTheme.name === selected) {
            this.setActiveTheme(selected);
            return false;
        }

        this.setActiveTheme(selected);
        this.builder.activeDevice = this.builder.getDevice();

        _.each(this.themes, function(theme) {
            theme.active = theme.name === selected;
        });

        const activeTheme = this.activeTheme;
        const head = this.builder.Canvas.getFrameEl().contentDocument.head;
        const style = head.querySelector('link');
        const styleClone = style.cloneNode();

        styleClone.setAttribute('href', this.activeTheme.stylesheet);
        styleClone.onload = function(e) {
            style.remove();
            mediator.trigger('grapesjs:theme:change', activeTheme);
        };

        head.appendChild(styleClone);
    },

    /**
     * Set active theme name
     * @param theme {String}
     * @private
     */
    setActiveTheme: function(theme) {
        this.activeTheme = _.find(this.themes, function(theme) {
            return theme.active;
        });
    },

    /**
     * Update source textarea and styles
     * @private
     */
    _updateInitialField: function() {
        const htmlContent = this.getEditorContent();
        const cssContent = this.getEditorStyles();

        if (this.$el.val() !== htmlContent) {
            this.$el.val(htmlContent).trigger('change');
        }

        if (this.$stylesInputElement.val() !== cssContent) {
            this.$stylesInputElement.val(cssContent).trigger('change');
        }
    },

    /**
     * Collect and compare builder options
     * @returns {GrapesjsEditorView.builderOptions|{fromElement}}
     * @private
     */
    _prepareBuilderOptions: function() {
        _.extend(this.builderOptions
            , this._getPlugins()
            , this._getStorageManagerConfig()
            , this._getCanvasConfig()
            , this._getStyleManagerConfig()
            , this._getTaitManagerConfig()
            , this._getAssetConfig()
        );

        return this.builderOptions;
    },

    /**
     * Get extended Storage Manager config
     * @returns {{storageManager: (*|void)}}
     * @private
     */
    _getStorageManagerConfig: function() {
        return {
            storageManager: _.extend({}, this.storageManager, {
                id: this.storagePrefix
            })
        };
    },

    /**
     * Get extended Style Manager config
     * @returns {{styleManager: *}}
     * @private
     */
    _getStyleManagerConfig: function() {
        return {
            styleManager: this.styleManager
        };
    },

    /**
     * Get extended Tait Manager config
     * @returns {{traitManager: *}}
     * @private
     */
    _getTaitManagerConfig: function() {
        return {
            traitManager: this.traitManager
        };
    },

    /**
     * Get extended Canvas config
     * @returns {{canvasCss: string, canvas: {styles: (*|string)[]}}}
     * @private
     */
    _getCanvasConfig: function() {
        const theme = this.getCurrentTheme();
        return _.extend({}, this.canvasConfig, {
            canvas: {
                styles: [theme.stylesheet]
            },
            protectedCss: []
        });
    },

    /**
     * Get asset manager configuration
     * @returns {*|void}
     * @private
     */
    _getAssetConfig: function() {
        return {
            assetManager: this.assetManagerConfig
        };
    },

    /**
     * Get plugins list with options
     * @returns {{plugins: *, pluginsOpts: (GrapesjsEditorView.builderPlugins|{"gjs-preset-webpage"})}}
     * @private
     */
    _getPlugins: function() {
        return {
            plugins: [i18nMessages, ContentParser, parserPostCSS, ...Object.keys(this.builderPlugins)],
            pluginsOpts: this.builderPlugins
        };
    },

    updateRtePosition(pos) {
        const $builderIframe = $(this.builder.Canvas.getFrameEl());
        const selected = this.builder.getSelected();
        if (!selected) {
            return;
        }

        const $el = selected.view.$el;
        const targetHeight = $(this.rte.actionbar).outerHeight();
        const targetWidth = $(this.rte.actionbar).outerWidth();

        $(this.rte.actionbar).parent().css('margin-left', '');

        if ($builderIframe.innerWidth() <= (pos.canvasOffsetLeft + targetWidth)) {
            $(this.rte.actionbar).parent().css('margin-left', $el.outerWidth() - targetWidth);
        }
        if (pos.top < 0 && $builderIframe.innerHeight() > (pos.canvasOffsetTop + targetHeight)) {
            pos.top += $el.outerHeight() + targetHeight;
        }
    }
});

export default GrapesjsEditorView;
