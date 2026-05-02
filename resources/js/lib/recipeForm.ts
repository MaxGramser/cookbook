/**
 * UI state for the section-aware recipe form. Rows live as a flat list of
 * either `header` rows (one bold input) or `item` rows (the actual data).
 * On submit we walk the list, carry the most-recent header into a `section`
 * field on each item, and emit the flat payload the backend expects.
 */

export type IngredientHeaderRow = { kind: 'header'; name: string };
export type IngredientItemRow = {
    kind: 'item';
    quantity_text: string;
    unit_text: string;
    name: string;
};
export type IngredientRow = IngredientHeaderRow | IngredientItemRow;

export type StepHeaderRow = { kind: 'header'; name: string };
export type StepItemRow = { kind: 'item'; body: string };
export type StepRow = StepHeaderRow | StepItemRow;

export type BackendIngredient = {
    section: string;
    quantity_text: string;
    unit_text: string;
    name: string;
};
export type BackendStep = { section: string; body: string };

export function compileIngredients(rows: IngredientRow[]): BackendIngredient[] {
    let section = '';
    const out: BackendIngredient[] = [];
    for (const row of rows) {
        if (row.kind === 'header') {
            section = row.name.trim();
            continue;
        }
        if (row.name.trim() === '') {
            continue;
        }
        out.push({
            section,
            quantity_text: row.quantity_text,
            unit_text: row.unit_text,
            name: row.name,
        });
    }
    return out;
}

export function compileSteps(rows: StepRow[]): BackendStep[] {
    let section = '';
    const out: BackendStep[] = [];
    for (const row of rows) {
        if (row.kind === 'header') {
            section = row.name.trim();
            continue;
        }
        if (row.body.trim() === '') {
            continue;
        }
        out.push({ section, body: row.body });
    }
    return out;
}

/** Build the visual rows from existing recipe data, inserting a header row
 * each time the section changes. */
export function expandIngredientsToRows(
    items: { section: string | null; quantity: number | null; unit: string | null; name: string }[],
    unitToText: (unit: string | null) => string,
): IngredientRow[] {
    const rows: IngredientRow[] = [];
    let lastSection: string | null = null;
    for (const item of items) {
        const section = item.section?.trim() || null;
        if (section !== lastSection) {
            if (section) {
                rows.push({ kind: 'header', name: section });
            }
            lastSection = section;
        }
        rows.push({
            kind: 'item',
            quantity_text: item.quantity?.toString().replace('.', ',') ?? '',
            unit_text: unitToText(item.unit),
            name: item.name,
        });
    }
    if (rows.length === 0) {
        rows.push({ kind: 'item', quantity_text: '', unit_text: '', name: '' });
    }
    return rows;
}

export function expandStepsToRows(
    items: { section: string | null; body: string }[],
): StepRow[] {
    const rows: StepRow[] = [];
    let lastSection: string | null = null;
    for (const item of items) {
        const section = item.section?.trim() || null;
        if (section !== lastSection) {
            if (section) {
                rows.push({ kind: 'header', name: section });
            }
            lastSection = section;
        }
        rows.push({ kind: 'item', body: item.body });
    }
    if (rows.length === 0) {
        rows.push({ kind: 'item', body: '' });
    }
    return rows;
}
