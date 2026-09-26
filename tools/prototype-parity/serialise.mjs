/** One record per line keeps multi-megabyte fixtures reviewable in diffs. */
export function serialise(meta, sections) {
    const parts = [`{"meta":${JSON.stringify(meta)}`];
    for (const [name, records] of Object.entries(sections)) {
        parts.push(
            `,\n"${name}":[\n${records.map((r) => JSON.stringify(r)).join(',\n')}\n]`,
        );
    }

    return `${parts.join('')}\n}\n`;
}
