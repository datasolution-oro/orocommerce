import ComponentManager from 'orocms/js/app/grapesjs/plugins/components/component-manager';
import WrapperTypeBuilder from 'orocms/js/app/grapesjs/type-builders/wrapper-type-builder';
import ContentBlockTypeBuilder from 'orocms/js/app/grapesjs/type-builders/content-block-type-builder';
import ContentWidgetTypeBuilder from 'orocms/js/app/grapesjs/type-builders/content-widget-type-builder';
import FileTypeBuilder from 'orocms/js/app/grapesjs/type-builders/file-type-builder';
import ImageTypeBuilder from 'orocms/js/app/grapesjs/type-builders/image-type-builder';
import QuoteTypeBuilder from 'orocms/js/app/grapesjs/type-builders/quote-type-builder';
import TableTypeBuilder from 'orocms/js/app/grapesjs/type-builders/table-type-builder';
import TableRowTypeBuilder from 'orocms/js/app/grapesjs/type-builders/table-row-type-builder';
import TableResponsiveTypeBuilder from 'orocms/js/app/grapesjs/type-builders/table-responsive-type-builder';
import LinkBLockTypeBuilder from 'orocms/js/app/grapesjs/type-builders/link-block-builder';
import LinkButtonTypeBuilder from 'orocms/js/app/grapesjs/type-builders/link-button-type-builder';
import LinkTypeBuilder from 'orocms/js/app/grapesjs/type-builders/link-type-builder';
import CodeTypeBuilder from 'orocms/js/app/grapesjs/type-builders/code-type-builder';
import TextBasicTypeBuilder from 'orocms/js/app/grapesjs/type-builders/text-basic-type-builder';
import TextTypeBuilder from 'orocms/js/app/grapesjs/type-builders/text-type-builder';
import VideoTypeBuilder from 'orocms/js/app/grapesjs/type-builders/video-type-builder';
import MapTypeBuilder from 'orocms/js/app/grapesjs/type-builders/map-type-builder';
import GridTypeBuilder from 'orocms/js/app/grapesjs/type-builders/grid-type-builder';
import GridColumnTypeBuilder from 'orocms/js/app/grapesjs/type-builders/grid-column-type-builder';
import GridRowTypeBuilder from 'orocms/js/app/grapesjs/type-builders/grid-row-type-builder';
import PictureTypeBuilder from 'orocms/js/app/grapesjs/type-builders/picture-type-builder';
import SourceTypeBuilder from 'orocms/js/app/grapesjs/type-builders/source-type-builder';

ComponentManager.registerComponentTypes({
    'wrapper': {
        Constructor: WrapperTypeBuilder
    },
    'quote': {
        Constructor: QuoteTypeBuilder
    },
    'row': {
        Constructor: TableRowTypeBuilder
    },
    'table': {
        Constructor: TableTypeBuilder
    },
    'table-responsive': {
        Constructor: TableResponsiveTypeBuilder
    },
    'content-block': {
        Constructor: ContentBlockTypeBuilder,
        optionNames: ['excludeContentBlockAlias']
    },
    'content-widget': {
        Constructor: ContentWidgetTypeBuilder,
        optionNames: ['excludeContentWidgetAlias']
    },
    'code': {
        Constructor: CodeTypeBuilder
    },
    'link': {
        Constructor: LinkTypeBuilder
    },
    'link-block': {
        Constructor: LinkBLockTypeBuilder
    },
    'link-button': {
        Constructor: LinkButtonTypeBuilder
    },
    'text': {
        Constructor: TextTypeBuilder
    },
    'text-basic': {
        Constructor: TextBasicTypeBuilder
    },
    'file': {
        Constructor: FileTypeBuilder
    },
    'image': {
        Constructor: ImageTypeBuilder
    },
    'picture': {
        Constructor: PictureTypeBuilder
    },
    'source': {
        Constructor: SourceTypeBuilder
    },
    'video': {
        Constructor: VideoTypeBuilder
    },
    'map': {
        Constructor: MapTypeBuilder
    },
    'grid-row': {
        Constructor: GridRowTypeBuilder
    },
    'grid-column': {
        Constructor: GridColumnTypeBuilder
    },
    'grid': {
        Constructor: GridTypeBuilder
    }
});
