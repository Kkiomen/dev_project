/**
 * Filter operators configuration for different field types.
 * Labels are translated using i18n in components that use this config.
 */

export const operators = {
    // Common operators
    equals: { value: 'equals', labelKey: 'filter.operators.equals', requiresValue: true },
    not_equals: { value: 'not_equals', labelKey: 'filter.operators.not_equals', requiresValue: true },
    is_empty: { value: 'is_empty', labelKey: 'filter.operators.is_empty', requiresValue: false },
    is_not_empty: { value: 'is_not_empty', labelKey: 'filter.operators.is_not_empty', requiresValue: false },

    // Text operators
    contains: { value: 'contains', labelKey: 'filter.operators.contains', requiresValue: true },
    not_contains: { value: 'not_contains', labelKey: 'filter.operators.not_contains', requiresValue: true },
    starts_with: { value: 'starts_with', labelKey: 'filter.operators.starts_with', requiresValue: true },
    ends_with: { value: 'ends_with', labelKey: 'filter.operators.ends_with', requiresValue: true },

    // Number operators
    greater_than: { value: 'greater_than', labelKey: 'filter.operators.greater_than', requiresValue: true },
    less_than: { value: 'less_than', labelKey: 'filter.operators.less_than', requiresValue: true },
    greater_or_equal: { value: 'greater_or_equal', labelKey: 'filter.operators.greater_or_equal', requiresValue: true },
    less_or_equal: { value: 'less_or_equal', labelKey: 'filter.operators.less_or_equal', requiresValue: true },
    between: { value: 'between', labelKey: 'filter.operators.between', requiresValue: true, isRange: true },

    // Date operators
    before: { value: 'before', labelKey: 'filter.operators.before', requiresValue: true },
    after: { value: 'after', labelKey: 'filter.operators.after', requiresValue: true },
    on_or_before: { value: 'on_or_before', labelKey: 'filter.operators.on_or_before', requiresValue: true },
    on_or_after: { value: 'on_or_after', labelKey: 'filter.operators.on_or_after', requiresValue: true },

    // Checkbox operators
    is_true: { value: 'is_true', labelKey: 'filter.operators.is_true', requiresValue: false },
    is_false: { value: 'is_false', labelKey: 'filter.operators.is_false', requiresValue: false },

    // Select operators
    is_any_of: { value: 'is_any_of', labelKey: 'filter.operators.is_any_of', requiresValue: true, isMultiple: true },
    is_none_of: { value: 'is_none_of', labelKey: 'filter.operators.is_none_of', requiresValue: true, isMultiple: true },

    // Multi-select operators
    contains_any: { value: 'contains_any', labelKey: 'filter.operators.contains_any', requiresValue: true, isMultiple: true },
    contains_all: { value: 'contains_all', labelKey: 'filter.operators.contains_all', requiresValue: true, isMultiple: true },
};

/**
 * Get operators available for a specific field type.
 */
export function getOperatorsForFieldType(fieldType) {
    const operatorsByType = {
        text: ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
        url: ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
        number: ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_or_equal', 'less_or_equal', 'between', 'is_empty', 'is_not_empty'],
        date: ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'between', 'is_empty', 'is_not_empty'],
        datetime: ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'between', 'is_empty', 'is_not_empty'],
        checkbox: ['is_true', 'is_false'],
        select: ['equals', 'not_equals', 'is_any_of', 'is_none_of', 'is_empty', 'is_not_empty'],
        multi_select: ['contains_any', 'contains_all', 'is_empty', 'is_not_empty'],
        json: ['contains', 'is_empty', 'is_not_empty'],
        attachment: ['is_empty', 'is_not_empty'],
    };

    const operatorKeys = operatorsByType[fieldType] || [];
    return operatorKeys.map(key => operators[key]);
}

/**
 * Get the default operator for a field type.
 */
export function getDefaultOperator(fieldType) {
    const defaults = {
        text: 'contains',
        url: 'contains',
        number: 'equals',
        date: 'equals',
        datetime: 'equals',
        checkbox: 'is_true',
        select: 'equals',
        multi_select: 'contains_any',
        json: 'contains',
        attachment: 'is_not_empty',
    };
    return defaults[fieldType] || 'equals';
}

/**
 * Get input type for the value field based on field type and operator.
 */
export function getValueInputType(fieldType, operator) {
    if (!operators[operator]?.requiresValue) {
        return null;
    }

    switch (fieldType) {
        case 'number':
            return 'number';
        case 'date':
            return 'date';
        case 'datetime':
            return 'datetime-local';
        case 'select':
        case 'multi_select':
            return 'select';
        default:
            return 'text';
    }
}
