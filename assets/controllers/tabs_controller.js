import { Controller } from '@hotwired/stimulus';

// Generic tabs — one panel visible at a time. Tabs and panels share document
// order, so a tab's index maps to its panel's index. Reusable across pages; the
// look is driven purely by CSS (.tabs / .tabs-pills), not by this controller.
//
// Optional deep-linking: give a tab `data-tabs-hash-param="foo"` and selecting it
// writes `#foo` to the URL; on load a matching `#foo` activates that tab (so a
// redirect or shared link can land on a specific tab).
//
//   <div data-controller="tabs">
//     <div role="tablist">
//       <button role="tab" data-tabs-target="tab" data-action="tabs#select"
//               data-tabs-hash-param="longline" aria-selected="false">…</button>…
//     </div>
//     <div role="tabpanel" data-tabs-target="panel" hidden>…</div>…
//   </div>
export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        const hash = window.location.hash.slice(1);
        const fromHash = hash
            ? this.tabTargets.findIndex((t) => t.dataset.tabsHashParam === hash)
            : -1;

        if (fromHash !== -1) {
            this.activate(fromHash);
            return;
        }

        // No (matching) hash — honour the server-rendered selection, falling back
        // to the first tab so exactly one panel is ever visible.
        const selected = this.tabTargets.findIndex(
            (t) => t.getAttribute('aria-selected') === 'true',
        );
        this.activate(selected === -1 ? 0 : selected);
    }

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        if (index === -1) return;

        this.activate(index);

        const hash = event.currentTarget.dataset.tabsHashParam;
        if (hash) history.replaceState(null, '', `#${hash}`);
    }

    activate(index) {
        this.tabTargets.forEach((tab, i) => {
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
            // Bootstrap nav styly (.nav-link) se řídí třídou .active; legacy
            // .tab styly aria-selected. Přepínáme obojí, ať CSS zůstává volné.
            tab.classList.toggle('active', i === index);
        });
        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index;
        });
    }
}
