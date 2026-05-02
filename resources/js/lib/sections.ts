/**
 * Group an ordered list of items by their `section` field, preserving
 * positional order. Consecutive items with the same `section` value are
 * grouped together; null/empty sections appear under a single "Onbekend"
 * group with a null label so the UI can render them without a heading.
 */
export type SectionGroup<T> = { section: string | null; items: T[] };

export function groupBySection<T extends { section: string | null }>(
    items: readonly T[],
): SectionGroup<T>[] {
    const groups: SectionGroup<T>[] = [];
    for (const item of items) {
        const sectionKey = item.section?.trim() || null;
        const last = groups[groups.length - 1];
        if (last && last.section === sectionKey) {
            last.items.push(item);
        } else {
            groups.push({ section: sectionKey, items: [item] });
        }
    }
    return groups;
}
