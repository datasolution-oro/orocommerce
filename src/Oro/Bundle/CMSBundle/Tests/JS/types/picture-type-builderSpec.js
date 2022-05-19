import 'jasmine-jquery';
import grapesJS from 'grapesjs';
import PictureTypeBuilder from 'orocms/js/app/grapesjs/type-builders/picture-type-builder';
import ComponentRestriction from 'orocms/js/app/grapesjs/plugins/components/component-restriction';
import html from 'text-loader!../fixtures/grapesjs-editor-view-fixture.html';

describe('orocms/js/app/grapesjs/type-builders/image-type-builder', () => {
    let pictureTypeBuilder;
    let editor;

    beforeEach(() => {
        window.setFixtures(html);
        editor = grapesJS.init({
            container: document.querySelector('.page-content-editor')
        });

        editor.ComponentRestriction = new ComponentRestriction(editor, {});
        editor.DomComponents.addType('source', {
            model: {
                defaults: {
                    type: 'source',
                    tagName: 'source'
                }
            }
        });
    });

    afterEach(() => {
        editor.destroy();
    });

    describe('component "PictureTypeBuilder"', () => {
        beforeEach(() => {
            pictureTypeBuilder = new PictureTypeBuilder({
                editor,
                componentType: 'picture'
            });

            pictureTypeBuilder.execute();
        });

        afterEach(() => {
            pictureTypeBuilder.dispose();
        });

        it('check to be defined', () => {
            expect(pictureTypeBuilder).toBeDefined();
            expect(pictureTypeBuilder.componentType).toEqual('picture');
        });

        it('check is component type defined', () => {
            const type = pictureTypeBuilder.editor.DomComponents.getType('picture');
            expect(type).toBeDefined();
            expect(type.id).toEqual('picture');
        });

        it('check is component type button', () => {
            const button = pictureTypeBuilder.editor.BlockManager.get(pictureTypeBuilder.componentType);
            expect(button).toBeDefined();
            expect(button.get('category').get('label')).toEqual('Basic');
        });

        it('check base model extend', () => {
            const mockElement = document.createElement('PICTURE');

            expect(pictureTypeBuilder.Model.isComponent).toBeDefined();
            expect(pictureTypeBuilder.Model.isComponent(mockElement)).toEqual({
                type: pictureTypeBuilder.componentType,
                sources: []
            });

            mockElement.innerHTML = `<source type="image/webp" srcset="url1">
                        <source type="image/png" srcset="url2">
                        <source type="image/jpeg" srcset="url3">`;

            expect(pictureTypeBuilder.Model.isComponent(mockElement)).toEqual({
                type: pictureTypeBuilder.componentType,
                sources: [{
                    attributes: {
                        type: 'image/webp',
                        srcset: 'url1'
                    }
                }, {
                    attributes: {
                        type: 'image/png',
                        srcset: 'url2'
                    }
                }, {
                    attributes: {
                        type: 'image/jpeg',
                        srcset: 'url3'
                    }
                }]
            });

            expect(pictureTypeBuilder.Model.componentType).toEqual(pictureTypeBuilder.componentType);

            expect(pictureTypeBuilder.Model.prototype.defaults.tagName).toEqual('picture');
            expect(pictureTypeBuilder.Model.prototype.defaults.type).toEqual('picture');
            expect(pictureTypeBuilder.Model.prototype.defaults.sources).toEqual([]);
            expect(pictureTypeBuilder.Model.prototype.defaults.editable).toEqual(true);
            expect(pictureTypeBuilder.Model.prototype.defaults.droppable).toEqual(false);

            expect(pictureTypeBuilder.Model.prototype.editor).toEqual(editor);
        });

        describe('test type in editor scope', () => {
            let pictureComponent;
            beforeEach(done => {
                editor.setComponents('<picture></picture>');

                pictureComponent = editor.Components.getComponents().models[0];
                setTimeout(() => done(), 0);
            });

            afterEach(done => {
                editor.setComponents('');
                setTimeout(() => done(), 0);
            });

            it('is defined', () => {
                expect(pictureComponent).toBeDefined();
                expect(pictureComponent.get('tagName')).toEqual('picture');
                expect(pictureComponent.get('sources')).toEqual([]);
            });

            it('is image exist', () => {
                expect(pictureComponent.image.get('tagName')).toEqual('img');
                expect(pictureComponent.image.get('type')).toEqual('image');
            });

            it('check add source', () => {
                pictureComponent.setSource('webp', {
                    srcset: 'url1'
                });

                expect(pictureComponent.get('sources')).toEqual([{
                    key: 'webp',
                    attributes: {
                        srcset: 'url1'
                    }
                }]);

                pictureComponent.setSource('jpeg', {
                    srcset: 'url2'
                });

                expect(pictureComponent.get('sources')).toEqual([{
                    key: 'webp',
                    attributes: {
                        srcset: 'url1'
                    }
                }, {
                    key: 'jpeg',
                    attributes: {
                        srcset: 'url2'
                    }
                }]);
            });

            it('check "toHTML"', () => {
                expect(pictureComponent.toHTML()).toEqual(
                    // eslint-disable-next-line
                    '<picture><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgc3R5bGU9ImZpbGw6IHJnYmEoMCwwLDAsMC4xNSk7IHRyYW5zZm9ybTogc2NhbGUoMC43NSkiPgogICAgICAgIDxwYXRoIGQ9Ik04LjUgMTMuNWwyLjUgMyAzLjUtNC41IDQuNSA2SDVtMTYgMVY1YTIgMiAwIDAgMC0yLTJINWMtMS4xIDAtMiAuOS0yIDJ2MTRjMCAxLjEuOSAyIDIgMmgxNGMxLjEgMCAyLS45IDItMnoiPjwvcGF0aD4KICAgICAgPC9zdmc+"/></picture>'
                );
            });

            it('check "toHTML" after update image', () => {
                pictureComponent.image.set('src', 'http://testlink.loc').addAttributes({
                    alt: 'Image title'
                });

                expect(pictureComponent.toHTML()).toEqual(
                    // eslint-disable-next-line
                    '<picture><img src="http://testlink.loc" alt="Image title"/></picture>'
                );
            });

            it('check "toHTML" after update image and source', () => {
                pictureComponent.image.set('src', 'http://testlink.loc').addAttributes({
                    alt: 'Image title'
                });

                pictureComponent.setSource('webp', {
                    srcset: 'url1',
                    type: 'image/webp'
                });

                pictureComponent.setSource('jpeg', {
                    srcset: 'url2',
                    type: 'image/jpeg'
                });

                expect(pictureComponent.toHTML()).toEqual(
                    // eslint-disable-next-line
                    '<picture><source srcset="url1" type="image/webp"/><source srcset="url2" type="image/jpeg"/><img src="http://testlink.loc" alt="Image title"/></picture>'
                );
            });
        });
    });
});
