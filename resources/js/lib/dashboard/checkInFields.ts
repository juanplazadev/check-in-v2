/**
 * How a detail page shows one of a location's optional check-in fields:
 * - `shown`: the location collects it, so the row always renders (a dash when empty).
 * - `legacy`: the location no longer collects it, but this record still has a value.
 * - `hidden`: the location does not collect it and the record has nothing to show.
 */
export type FieldState = 'shown' | 'legacy' | 'hidden';

export const LEGACY_FIELD_HINT =
    'This location no longer collects this field. It was enabled when this check-in was submitted.';

/** The record resolver sends missing optional values as '' rather than null. */
export function hasValue(value: unknown): boolean {
    return value !== null && value !== undefined && value !== '';
}

export function fieldState(enabled: boolean, ...values: unknown[]): FieldState {
    if (enabled) {
        return 'shown';
    }

    return values.some(hasValue) ? 'legacy' : 'hidden';
}

/** The state of a row that combines several flags, e.g. a plate's state and country. */
export function combinedFieldState(states: FieldState[]): FieldState {
    if (states.includes('shown')) {
        return 'shown';
    }

    return states.includes('legacy') ? 'legacy' : 'hidden';
}
