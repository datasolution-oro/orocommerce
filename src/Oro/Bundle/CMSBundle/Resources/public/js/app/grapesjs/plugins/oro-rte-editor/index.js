import BaseClass from 'oroui/js/base-class';
import RteCollection from './rte-collection';
import rteActions from '../components/rte';
import RteCollectionView from './rte-collection-view';

const GrapesjsRteEditor = BaseClass.extend({
    constructor: function GrapesjsRteEditor(...args) {
        GrapesjsRteEditor.__super__.constructor.apply(this, args);
    },

    initialize({editor, ...args}) {
        this.editor = editor;
        const {RichTextEditor} = this.editor;

        this.collection = new RteCollection(rteActions, {
            rte: RichTextEditor
        });

        this.listenTo(this.editor, 'destroy', this.onDestroy.bind(this));

        this.removeDefaultAllActions();
        this.defineRteEditor();
        GrapesjsRteEditor.__super__.initialize.apply(this, args);
    },

    /**
     * Remove default RTE actions from GrapesJS
     */
    removeDefaultAllActions() {
        const {RichTextEditor} = this.editor;

        for (const {name} of [...RichTextEditor.actions]) {
            RichTextEditor.remove(name);
        }
    },

    /**
     * Define custom RTE editor view
     */
    defineRteEditor() {
        const {RichTextEditor, Canvas, $} = this.editor;
        const self = this;
        this.editor.setCustomRte({
            enable(el, rte) {
                if (rte && !rte.disposed) {
                    this.focus(el, rte);
                    return rte;
                }

                el.contentEditable = true;

                rte = self.view = new RteCollectionView({
                    el: RichTextEditor.actionbar,
                    editableEl: el,
                    $editableEl: $(el),
                    editor: self.editor,
                    doc: Canvas.getDocument(),
                    collection: self.collection
                });

                this.focus(el, rte);

                return rte;
            },

            disable(el, rte) {
                el.contentEditable = false;

                if (rte && !rte.disposed) {
                    rte.dispose();
                }
            },

            focus(el, rte) {
                if (rte && !rte.disposed) {
                    return;
                }

                el.contentEditable = true;

                rte && rte.focus();

                self.editor.trigger('change:canvasOffset');
            }
        });
    },

    /**
     * Add action to custom RTE editor
     * @param {Object} action
     */
    addAction(action) {
        this.collection.add(action);
    },

    /**
     * Remove action to custom RTE editor
     * @param {string} name
     */
    removeAction(name) {
        const found = this.collection.findWhere({name});

        if (found) {
            this.collection.remove(found);
        }
    },

    onDestroy() {
        if (this.view) {
            this.view.dispose();
        }
        this.collection.dispose();

        delete this.view;
        delete this.collection;
        delete this.editor;

        this.dispose();
    }
});

export default editor => {
    editor.RteEditor = new GrapesjsRteEditor({editor});
};

