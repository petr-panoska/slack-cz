import { Controller } from '@hotwired/stimulus';

// Splash overlay + persistent music menu (mute, prev/next track).
// Tracks come from the `tracks` value as JSON [{ title, artist, src }, ...].
// Mute state and selected track index persist in localStorage.

const STORAGE_MUTED = 'slack-cz:music:muted';
const STORAGE_TRACK = 'slack-cz:music:track';

const FADE_IN_SECONDS = 1.5;
const FADE_SWITCH_SECONDS = 0.7;
const FADE_OUT_SECONDS = 0.4;
const TARGET_VOLUME = 0.55;

export default class extends Controller {
    static values = {
        tracks: Array,
    };

    static targets = ['overlay', 'menu', 'muteBtn', 'label'];

    connect() {
        this.muted = JSON.parse(localStorage.getItem(STORAGE_MUTED) || 'false');
        const stored = parseInt(localStorage.getItem(STORAGE_TRACK) ?? '0', 10);
        this.currentIndex = Number.isFinite(stored) && stored >= 0 && stored < this.tracksValue.length
            ? stored
            : 0;
        this.updateUI();
    }

    enter(event) {
        event?.preventDefault();
        if (this.started) return;
        this.started = true;

        this.element.classList.add('intro-started');
        this.overlayTarget?.classList.add('intro-dismissing');

        this.startAudio();
    }

    toggleMute(event) {
        event?.preventDefault();
        this.muted = !this.muted;
        localStorage.setItem(STORAGE_MUTED, JSON.stringify(this.muted));
        if (this.audio) this.fade(this.audio, this.muted ? 0 : TARGET_VOLUME, FADE_OUT_SECONDS);
        this.updateUI();
    }

    next(event) {
        event?.preventDefault();
        this.changeTrack((this.currentIndex + 1) % this.tracksValue.length);
    }

    prev(event) {
        event?.preventDefault();
        this.changeTrack((this.currentIndex - 1 + this.tracksValue.length) % this.tracksValue.length);
    }

    changeTrack(newIndex) {
        this.currentIndex = newIndex;
        localStorage.setItem(STORAGE_TRACK, String(newIndex));
        this.updateUI();
        if (!this.audio) return;
        this.swapAudio();
    }

    startAudio() {
        const track = this.tracksValue[this.currentIndex];
        if (!track) return;

        const audio = this.makeAudio(track.src);
        audio.volume = 0;
        this.audio = audio;

        this.tryPlay(audio);
        this.fade(audio, this.muted ? 0 : TARGET_VOLUME, FADE_IN_SECONDS);
    }

    swapAudio() {
        const track = this.tracksValue[this.currentIndex];
        if (!track) return;

        const oldAudio = this.audio;
        const next = this.makeAudio(track.src);
        next.volume = 0;
        this.audio = next;

        this.tryPlay(next);

        // Crossfade
        if (oldAudio) {
            this.fade(oldAudio, 0, FADE_SWITCH_SECONDS, () => {
                oldAudio.pause();
                oldAudio.src = '';
            });
        }
        this.fade(next, this.muted ? 0 : TARGET_VOLUME, FADE_SWITCH_SECONDS);
    }

    makeAudio(src) {
        const audio = new Audio(src);
        audio.preload = 'none';
        audio.addEventListener('ended', () => {
            if (this.audio === audio) this.next();
        });
        return audio;
    }

    tryPlay(audio) {
        const p = audio.play();
        if (p && typeof p.catch === 'function') {
            p.catch((err) => console.warn('Audio play blocked', err));
        }
    }

    fade(audio, target, seconds, done) {
        const start = audio.volume;
        const t0 = performance.now();
        const step = () => {
            if (!audio) return;
            const elapsed = (performance.now() - t0) / 1000;
            const ratio = Math.min(1, elapsed / seconds);
            audio.volume = start + (target - start) * ratio;
            if (ratio < 1) {
                requestAnimationFrame(step);
            } else if (done) {
                done();
            }
        };
        requestAnimationFrame(step);
    }

    updateUI() {
        const track = this.tracksValue[this.currentIndex];
        if (this.hasLabelTarget && track) {
            this.labelTarget.textContent = `${track.title} — ${track.artist}`;
        }
        if (this.hasMuteBtnTarget) {
            this.muteBtnTarget.setAttribute('aria-pressed', String(this.muted));
            this.muteBtnTarget.setAttribute('aria-label', this.muted ? 'Zapnout zvuk' : 'Ztlumit');
            this.muteBtnTarget.classList.toggle('is-muted', this.muted);
        }
    }

    disconnect() {
        if (this.audio) {
            const audio = this.audio;
            this.audio = null;
            this.fade(audio, 0, FADE_OUT_SECONDS, () => {
                audio.pause();
                audio.src = '';
            });
        }
    }
}
