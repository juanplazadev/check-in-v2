// The 50 US states plus DC. `name` is what we store (matching the check-in's
// free-text state), `abbr` drives the license-card badge and per-state theming.

export interface UsState {
    abbr: string;
    name: string;
}

export const US_STATES: readonly UsState[] = [
    { abbr: 'AL', name: 'Alabama' },
    { abbr: 'AK', name: 'Alaska' },
    { abbr: 'AZ', name: 'Arizona' },
    { abbr: 'AR', name: 'Arkansas' },
    { abbr: 'CA', name: 'California' },
    { abbr: 'CO', name: 'Colorado' },
    { abbr: 'CT', name: 'Connecticut' },
    { abbr: 'DE', name: 'Delaware' },
    { abbr: 'DC', name: 'District of Columbia' },
    { abbr: 'FL', name: 'Florida' },
    { abbr: 'GA', name: 'Georgia' },
    { abbr: 'HI', name: 'Hawaii' },
    { abbr: 'ID', name: 'Idaho' },
    { abbr: 'IL', name: 'Illinois' },
    { abbr: 'IN', name: 'Indiana' },
    { abbr: 'IA', name: 'Iowa' },
    { abbr: 'KS', name: 'Kansas' },
    { abbr: 'KY', name: 'Kentucky' },
    { abbr: 'LA', name: 'Louisiana' },
    { abbr: 'ME', name: 'Maine' },
    { abbr: 'MD', name: 'Maryland' },
    { abbr: 'MA', name: 'Massachusetts' },
    { abbr: 'MI', name: 'Michigan' },
    { abbr: 'MN', name: 'Minnesota' },
    { abbr: 'MS', name: 'Mississippi' },
    { abbr: 'MO', name: 'Missouri' },
    { abbr: 'MT', name: 'Montana' },
    { abbr: 'NE', name: 'Nebraska' },
    { abbr: 'NV', name: 'Nevada' },
    { abbr: 'NH', name: 'New Hampshire' },
    { abbr: 'NJ', name: 'New Jersey' },
    { abbr: 'NM', name: 'New Mexico' },
    { abbr: 'NY', name: 'New York' },
    { abbr: 'NC', name: 'North Carolina' },
    { abbr: 'ND', name: 'North Dakota' },
    { abbr: 'OH', name: 'Ohio' },
    { abbr: 'OK', name: 'Oklahoma' },
    { abbr: 'OR', name: 'Oregon' },
    { abbr: 'PA', name: 'Pennsylvania' },
    { abbr: 'RI', name: 'Rhode Island' },
    { abbr: 'SC', name: 'South Carolina' },
    { abbr: 'SD', name: 'South Dakota' },
    { abbr: 'TN', name: 'Tennessee' },
    { abbr: 'TX', name: 'Texas' },
    { abbr: 'UT', name: 'Utah' },
    { abbr: 'VT', name: 'Vermont' },
    { abbr: 'VA', name: 'Virginia' },
    { abbr: 'WA', name: 'Washington' },
    { abbr: 'WV', name: 'West Virginia' },
    { abbr: 'WI', name: 'Wisconsin' },
    { abbr: 'WY', name: 'Wyoming' },
];

const NAME_TO_ABBR: Record<string, string> = Object.fromEntries(
    US_STATES.map((s) => [s.name.toLowerCase(), s.abbr]),
);

/** Resolve a stored state (name or abbreviation) to its two-letter code. */
export function stateAbbr(state?: string | null): string | null {
    if (!state) {
        return null;
    }

    const trimmed = state.trim();

    if (trimmed.length === 2) {
        const upper = trimmed.toUpperCase();
        return US_STATES.some((s) => s.abbr === upper) ? upper : null;
    }

    return NAME_TO_ABBR[trimmed.toLowerCase()] ?? null;
}

/**
 * Display name for a stored state. Check-ins store full names, but older or seeded rows may hold
 * a US code; that is expanded. Anything else (a Canadian or Mexican name) passes through.
 */
export function stateName(
    state?: string | null,
    country?: string | null,
): string {
    const trimmed = state?.trim() ?? '';

    if (trimmed.length !== 2 || (country && country.toUpperCase() !== 'US')) {
        return trimmed;
    }

    return (
        US_STATES.find((s) => s.abbr === trimmed.toUpperCase())?.name ?? trimmed
    );
}
