import { Controller } from '@hotwired/stimulus';

// Click-to-play facade for slackTV cards — we don't load 24 YouTube iframes on
// page load (heavy + privacy). The thumbnail button stays a plain <img> until
// clicked, then we swap in a youtube-nocookie embed that autoplays.
//   <article data-controller="tv" data-tv-video-value="VIDEOID" data-tv-title-value="...">
//     <button data-tv-target="media" data-action="tv#play">…thumb…</button>
//   </article>
export default class extends Controller {
    static targets = ['media'];
    static values  = { video: String, title: String };

    play(event) {
        event.preventDefault();
        if (!this.videoValue) return;

        const iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube-nocookie.com/embed/${this.videoValue}?autoplay=1&rel=0`;
        iframe.title = this.titleValue;
        iframe.className = 'tv-embed';
        iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;

        this.mediaTarget.replaceWith(iframe);
    }
}
