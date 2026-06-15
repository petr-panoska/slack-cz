import { Controller } from '@hotwired/stimulus';

// Generic client-side table: column sorting + free-text filtering. Page-agnostic
// and reusable; the look is driven purely by CSS, not by this controller. Built
// for small datasets that render fully server-side (no pagination) — the server
// emits a real, ordered table and this just upgrades it (progressive enhancement).
//
//   <div data-controller="data-table">
//     <input data-data-table-target="filter" data-action="input->data-table#filter">
//     <span data-data-table-target="count"></span>
//     <table>
//       <thead><tr>
//         <th data-sort-type="text" data-action="click->data-table#sort">…</th>
//         <th data-sort-type="num"  data-action="click->data-table#sort">…</th>
//         <th data-sort-type="date" data-action="click->data-table#sort">…</th>
//       </tr></thead>
//       <tbody data-data-table-target="body">
//         <tr data-filter="searchable text"><td>…</td>…</tr>
//       </tbody>
//     </table>
//     <p data-data-table-target="empty" hidden>Nothing found</p>
//   </div>
//
// Per-cell sort value: `data-sort-value` on a <td> overrides its text content
// (use it for dates as `Y-m-d`, or any value that differs from what's displayed).
// Empty values always sink to the bottom regardless of sort direction.
//
// A header may set `data-sort-default="desc"` to make its FIRST click sort
// descending (handy for counts/dates where "most/newest first" is expected);
// the default is ascending. Subsequent clicks on the same column toggle.
export default class extends Controller {
    static targets = ['filter', 'body', 'count', 'empty'];

    connect() {
        // No initial sort — the server render is already in the intended order.
        this.sortIndex = -1;
        this.sortDir = 1;
        if (this.hasFilterTarget && this.filterTarget.value.trim()) {
            this.filter();
        } else {
            this.updateCount();
        }
    }

    sort(event) {
        const th = event.currentTarget;
        const headerRow = th.parentElement;
        const index = Array.from(headerRow.children).indexOf(th);
        if (index === -1) return;

        const type = th.dataset.sortType || 'text';

        // Same column toggles direction; a new column starts in its declared
        // default (`data-sort-default="desc"` → descending, otherwise ascending).
        this.sortDir = index === this.sortIndex
            ? -this.sortDir
            : (th.dataset.sortDefault === 'desc' ? -1 : 1);
        this.sortIndex = index;

        const rows = Array.from(this.bodyTarget.rows);
        rows.sort((a, b) => {
            const va = cellValue(a, index);
            const vb = cellValue(b, index);

            // Empties always last, no matter the direction.
            const ea = va === '';
            const eb = vb === '';
            if (ea || eb) return ea && eb ? 0 : ea ? 1 : -1;

            return compare(va, vb, type) * this.sortDir;
        });

        rows.forEach((row) => this.bodyTarget.appendChild(row));

        // Reflect state on headers for a11y + CSS arrow.
        Array.from(headerRow.children).forEach((cell, i) => {
            if (i === index) {
                cell.setAttribute('aria-sort', this.sortDir === 1 ? 'ascending' : 'descending');
            } else {
                cell.removeAttribute('aria-sort');
            }
        });
    }

    filter() {
        const q = fold(this.hasFilterTarget ? this.filterTarget.value : '').trim();

        for (const row of this.bodyTarget.rows) {
            const haystack = fold(row.dataset.filter ?? row.textContent);
            row.hidden = q !== '' && !haystack.includes(q);
        }

        this.updateCount();
    }

    updateCount() {
        const visible = Array.from(this.bodyTarget.rows).filter((r) => !r.hidden).length;

        if (this.hasCountTarget) this.countTarget.textContent = visible;
        if (this.hasEmptyTarget) this.emptyTarget.hidden = visible !== 0;
    }
}

// Diacritics-insensitive: "rene" matches "René".
function fold(s) {
    return (s ?? '')
        .toString()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

function cellValue(row, index) {
    const cell = row.cells[index];
    if (!cell) return '';
    const raw = cell.dataset.sortValue ?? cell.textContent;
    return raw.trim();
}

function compare(a, b, type) {
    if (type === 'num') {
        return (parseFloat(a) || 0) - (parseFloat(b) || 0);
    }
    if (type === 'date') {
        // Sort values are zero-padded `Y-m-d` strings → lexicographic == chronological.
        return a < b ? -1 : a > b ? 1 : 0;
    }
    return fold(a).localeCompare(fold(b), 'cs');
}
