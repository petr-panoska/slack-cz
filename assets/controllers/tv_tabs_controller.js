import { Controller } from '@hotwired/stimulus';

// Hashtag tabs for slackTV — one panel visible at a time. Tabs and panels are
// in matching document order, so a tab's index maps to its panel's index.
//   <section data-controller="tv-tabs">
//     <div role="tablist">
//       <button data-tv-tabs-target="tab" data-action="tv-tabs#select">…</button>…
//     </div>
//     <div data-tv-tabs-target="panel" hidden>…</div>…
//   </section>
export default class extends Controller {
    static targets = ['tab', 'panel'];

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        if (index === -1) return;

        this.tabTargets.forEach((tab, i) => {
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index;
        });
    }
}
