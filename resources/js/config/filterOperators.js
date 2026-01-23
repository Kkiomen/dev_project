/**
 * Filter operators configuration for different field types.
 */

export const operators = {
    // Common operators
    equals: { value: 'equals', label: 'jest równe', requiresValue: true },
    not_equals: { value: 'not_equals', label: 'nie jest równe', requiresValue: true },
    is_empty: { value: 'is_empty', label: 'jest puste', requiresValue: false },
    is_not_empty: { value: 'is_not_empty', label: 'nie jest puste', requiresValue: false },

    // Text operators
    contains: { value: 'contains', label: 'zawiera', requiresValue: true },
    not_contains: { value: 'not_contains', label: 'nie zawiera', requiresValue: true },
    starts_with: { value: 'starts_with', label: 'zaczyna się od', requiresValue: true },
    ends_with: { value: 'ends_with', label: 'kończy się na', requiresValue: true },

    // Number operators
    greater_than: { value: 'greater_than', label: 'większe niż', requiresValue: true },
    less_than: { value: 'less_than', label: 'mniejsze niż', requiresValue: true },
    greater_or_equal: { value: 'greater_or_equal', label: 'większe lub równe', requiresValue: true },
    less_or_equal: { value: 'less_or_equal', label: 'mniejsze lub równe', requiresValue: true },
    between: { value: 'between', label: 'pomiędzy', requiresValue: true, isRange: true },

    // Date operators
    before: { value: 'before', label: 'przed', requiresValue: true },
    after: { value: 'after', label: 'po', requiresValue: true },
    on_or_before: { value: 'on_or_before', label: 'w dniu lub przed', requiresValue: true },
    on_or_after: { value: 'on_or_after', label: 'w dniu lub po', requiresValue: true },

    // Checkbox operators
    is_true: { value: 'is_true', label: 'jest zaznaczony', requiresValue: false },
    is_false: { value: 'is_false', label: 'nie jest zaznaczony', requiresValue: false },

    // Select operators
    is_any_of: { value: 'is_any_of', label: 'jest jednym z', requiresValue: true, isMultiple: true },
    is_none_of: { value: 'is_none_of', label: 'nie jest żadnym z', requiresValue: true, isMultiple: true },

    // Multi-select operators
    contains_any: { value: 'contains_any', label: 'zawiera którykolwiek z', requiresValue: true, isMultiple: true },
    contains_all: { value: 'contains_all', label: 'zawiera wszystkie z', requiresValue: true, isMultiple: true },
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
