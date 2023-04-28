import {uniqueId} from 'underscore';

/**
 *
 * @param item
 * @param classNames
 */
export const addClass = (item, classNames = []) => {
    if (item.row_class_name === void 0) {
        item.row_class_name = '';
    }

    const classes = item.row_class_name.split(' ');
    item.row_class_name = classes.concat(classNames).join(' ');
};

export const isHighlight = item => item.isUpcoming || (item.warnings && item.warnings.length);

export const isError = item => item.errors && item.errors.length;

export const messageModel = (item, columnName, opts = {}) => {
    const messageItem = {
        ...item,
        id: item.id + uniqueId('-bind-'),
        renderColumnName: columnName,
        row_class_name: item.row_class_name + ' extension-row notification-row',
        _templateKey: 'message',
        isMessage: true,
        isAuxiliary: true,
        row_attributes: {
            'aria-hidden': true
        },
        ...opts
    };

    item.row_class_name += ' has-message-row';
    item.messageModelId = messageItem.id;
    return messageItem;
};
