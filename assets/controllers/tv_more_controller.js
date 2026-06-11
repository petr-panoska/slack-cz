import { Controller } from '@hotwired/stimulus';

// Load-more for one slackTV slider. Every slider renders its first page
// server-side; clicking the trailing "Načíst další" card fetches the next page
// from /tv/more (keyed by the source's stable key — channel/playlist/hashtag)
// and appends the cards before the button. When YouTube runs out of pages the
// button is swapped for a "Vše na YouTube" link card.
//   <div data-controller="tv-more"
//        data-tv-more-key-value="channel:@x"
//        data-tv-more-next-value="TOKEN"
//        data-tv-more-url-value="https://www.youtube.com/...">
//     <div data-tv-more-target="track">
//       …cards…
//       <button data-tv-more-target="button" data-action="tv-more#more">…</button>
//     </div>
//   </div>
export default class extends Controller {
    static targets = ['button'];
    static values  = { key: String, next: String, url: String };

    async more() {
        if (this.loading || !this.nextValue || !this.hasButtonTarget) return;
        this.loading = true;
        this.buttonTarget.classList.add('is-loading');

        try {
            const params = new URLSearchParams({ key: this.keyValue, page: this.nextValue });
            const res = await fetch(`/tv/more?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const { html, nextPage } = await res.json();
            if (html) this.buttonTarget.insertAdjacentHTML('beforebegin', html);
            this.nextValue = nextPage || '';
        } catch {
            // Leave the button in place so the user can retry.
            this.buttonTarget.classList.remove('is-loading');
            this.loading = false;
            return;
        }

        this.loading = false;
        this.buttonTarget.classList.remove('is-loading');
        if (!this.nextValue) this.exhaust();
    }

    // No more pages — turn the button into a "view all on YouTube" link card
    // (or just drop it if we have no source URL).
    exhaust() {
        if (!this.urlValue) {
            this.buttonTarget.remove();
            return;
        }
        const link = document.createElement('a');
        link.className = 'tv-card tv-more-card';
        link.href = this.urlValue;
        link.target = '_blank';
        link.rel = 'noopener';
        link.innerHTML =
            '<span class="tv-more-card-inner"><span class="tv-more-icon" aria-hidden="true">↗</span>Vše na YouTube</span>';
        this.buttonTarget.replaceWith(link);
    }
}
