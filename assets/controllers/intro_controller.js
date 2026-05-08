import { Controller } from '@hotwired/stimulus';

// Splash overlay + persistent music menu (mute, prev/next track).
// Tracks come from the `tracks` value as JSON [{ title, artist, src }, ...].
// Mute state and selected track index persist in localStorage.
//
// The Audio object lives in module scope (not on the controller) so it
// survives any Turbo lifecycle quirks: even if Stimulus disconnects and
// reconnects the controller during a permanent-element swap, the same
// Audio keeps playing — connect() rebinds to it instead of starting over.

const STORAGE_MUTED = 'slack-cz:music:muted';
const STORAGE_TRACK = 'slack-cz:music:track';
const STORAGE_COMPACT = 'slack-cz:music:compact';
const STORAGE_HIDDEN = 'slack-cz:music:hidden';
const STORAGE_POSITION = 'slack-cz:music:position';

const FADE_IN_SECONDS = 1.5;
const FADE_SWITCH_SECONDS = 0.7;
const FADE_OUT_SECONDS = 0.4;
const TARGET_VOLUME = 0.55;

const shared = {
    audio: null,
    started: false,
};
let currentInstance = null;
// First controller connect in this JS context = full page load (Turbo navigation
// doesn't reset module state). Used to reset the "hidden" flag on every refresh
// so the player comes back, while still surviving Turbo nav.
let firstConnect = true;

function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

export default class extends Controller {
    static values = {
        tracks: Array,
    };

    static targets = ['overlay', 'menu', 'muteBtn', 'title', 'artist', 'progress', 'time', 'playBtn', 'queue', 'miniBtn'];

    connect() {
        currentInstance = this;
        if (firstConnect) {
            firstConnect = false;
            // Page reload resets the hidden state — user's "hide" only persists
            // through Turbo navigation, not full reloads.
            localStorage.removeItem(STORAGE_HIDDEN);
        }
        this.muted = JSON.parse(localStorage.getItem(STORAGE_MUTED) || 'false');
        const stored = parseInt(localStorage.getItem(STORAGE_TRACK) ?? '0', 10);
        this.currentIndex = Number.isFinite(stored) && stored >= 0 && stored < this.tracksValue.length
            ? stored
            : 0;
        this.seeking = false;

        if (shared.started) {
            this.element.classList.add('intro-started');
            this.overlayTarget?.classList.add('intro-dismissing');
        }
        // Default = compact (docked in header). User must explicitly expand.
        const compactStored = localStorage.getItem(STORAGE_COMPACT);
        const isCompact = compactStored === null ? true : JSON.parse(compactStored);
        if (isCompact) this.menuTarget?.classList.add('is-compact');
        else this.applyStoredPosition();
        if (this.hasMiniBtnTarget) {
            this.miniBtnTarget.setAttribute('aria-label', isCompact ? 'Rozbalit přehrávač' : 'Sbalit přehrávač');
            this.miniBtnTarget.setAttribute('title', isCompact ? 'Rozbalit' : 'Sbalit');
        }
        if (JSON.parse(localStorage.getItem(STORAGE_HIDDEN) || 'false')) {
            this.element.classList.add('music-hidden');
        }
        this.bindHeaderDrag();
        this.bindAudioEvents(shared.audio);
        this.updateUI();
        this.updateProgressUI();
    }

    applyStoredPosition() {
        const raw = localStorage.getItem(STORAGE_POSITION);
        if (!raw || !this.hasMenuTarget) return;
        try {
            const { x, y } = JSON.parse(raw);
            if (!Number.isFinite(x) || !Number.isFinite(y)) return;
            const w = this.menuTarget.offsetWidth || 320;
            const h = this.menuTarget.offsetHeight || 100;
            const cx = Math.max(8, Math.min(window.innerWidth - w - 8, x));
            const cy = Math.max(8, Math.min(window.innerHeight - h - 8, y));
            this.menuTarget.style.left = `${cx}px`;
            this.menuTarget.style.top = `${cy}px`;
            this.menuTarget.style.right = 'auto';
            this.menuTarget.style.bottom = 'auto';
        } catch (_) {}
    }

    bindHeaderDrag() {
        if (!this.hasMenuTarget) return;
        const header = this.menuTarget.querySelector('.music-panel-header');
        if (!header || header.__dragBound) return;
        header.__dragBound = true;
        header.addEventListener('pointerdown', (event) => {
            // Drag only in expanded (floating) state. Compact panel is docked in flex layout.
            if (this.menuTarget.classList.contains('is-compact')) return;
            if (event.target.closest('button, input, a, .music-panel-trail')) return;
            this.startDrag(event);
        });
    }

    startDrag(event) {
        if (!this.hasMenuTarget) return;
        if (event.button !== undefined && event.button !== 0) return;

        event.preventDefault();
        const panel = this.menuTarget;
        const rect = panel.getBoundingClientRect();
        const offsetX = event.clientX - rect.left;
        const offsetY = event.clientY - rect.top;

        panel.classList.add('is-dragging');

        const onMove = (e) => {
            const w = panel.offsetWidth;
            const h = panel.offsetHeight;
            const x = Math.max(8, Math.min(window.innerWidth - w - 8, e.clientX - offsetX));
            const y = Math.max(8, Math.min(window.innerHeight - h - 8, e.clientY - offsetY));
            panel.style.left = `${x}px`;
            panel.style.top = `${y}px`;
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
        };

        const onUp = () => {
            panel.classList.remove('is-dragging');
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            document.removeEventListener('pointercancel', onUp);
            const r = panel.getBoundingClientRect();
            localStorage.setItem(STORAGE_POSITION, JSON.stringify({ x: r.left, y: r.top }));
        };

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
        document.addEventListener('pointercancel', onUp);
    }

    toggleCompact(event) {
        event?.preventDefault();
        if (!this.hasMenuTarget) return;
        const next = !this.menuTarget.classList.contains('is-compact');
        this.menuTarget.classList.toggle('is-compact', next);
        localStorage.setItem(STORAGE_COMPACT, JSON.stringify(next));
        if (next) {
            // Going to compact (docked) — clear inline floating position.
            this.menuTarget.style.left = '';
            this.menuTarget.style.top = '';
            this.menuTarget.style.right = '';
            this.menuTarget.style.bottom = '';
        } else {
            // Going to expanded (floating) — restore saved position.
            this.applyStoredPosition();
        }
        if (this.hasMiniBtnTarget) {
            this.miniBtnTarget.setAttribute('aria-label', next ? 'Rozbalit přehrávač' : 'Sbalit přehrávač');
            this.miniBtnTarget.setAttribute('title', next ? 'Rozbalit' : 'Sbalit');
        }
        this.updateMuteAriaLabel();
    }

    hide(event) {
        event?.preventDefault();
        this.element.classList.add('music-hidden');
        localStorage.setItem(STORAGE_HIDDEN, 'true');
        if (shared.audio) shared.audio.pause();
    }

    get audio() { return shared.audio; }
    set audio(v) { shared.audio = v; }

    enter(event) {
        event?.preventDefault();
        if (shared.started) return;
        shared.started = true;

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

    toggleMuteOrPlay(event) {
        if (this.menuTarget?.classList.contains('is-compact')) {
            this.togglePlay(event);
        } else {
            this.toggleMute(event);
        }
    }

    next(event) {
        event?.preventDefault();
        this.changeTrack((this.currentIndex + 1) % this.tracksValue.length);
    }

    prev(event) {
        event?.preventDefault();
        this.changeTrack((this.currentIndex - 1 + this.tracksValue.length) % this.tracksValue.length);
    }

    playIndex(event) {
        event?.preventDefault();
        const li = event.currentTarget.closest('[data-index]');
        if (!li) return;
        const idx = parseInt(li.dataset.index, 10);
        if (!Number.isFinite(idx) || idx === this.currentIndex) return;
        if (!shared.started) {
            // First gesture: count this as entering.
            shared.started = true;
            this.element.classList.add('intro-started');
            this.overlayTarget?.classList.add('intro-dismissing');
            this.currentIndex = idx;
            localStorage.setItem(STORAGE_TRACK, String(idx));
            this.updateUI();
            this.startAudio();
            return;
        }
        this.changeTrack(idx);
    }

    togglePlay(event) {
        event?.preventDefault();
        if (!shared.audio) {
            // Treat as the entry gesture if we haven't started yet.
            if (!shared.started) {
                shared.started = true;
                this.element.classList.add('intro-started');
                this.overlayTarget?.classList.add('intro-dismissing');
            }
            this.startAudio();
            return;
        }
        if (shared.audio.paused) {
            this.tryPlay(shared.audio);
        } else {
            shared.audio.pause();
        }
        this.updatePlayBtn();
    }

    seek(event) {
        const audio = shared.audio;
        if (!audio || !Number.isFinite(audio.duration) || audio.duration <= 0) return;
        const ratio = parseInt(event.currentTarget.value, 10) / 1000;
        audio.currentTime = ratio * audio.duration;
        this.updateTimeLabel();
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
        this.bindAudioEvents(audio);

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
        this.bindAudioEvents(next);

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
        audio.preload = 'metadata';
        audio.addEventListener('ended', () => {
            if (shared.audio === audio) currentInstance?.next();
        });
        return audio;
    }

    bindAudioEvents(audio) {
        if (!audio || audio === this._boundAudio) return;
        this._boundAudio = audio;
        const onUpdate = () => this.updateProgressUI();
        audio.addEventListener('timeupdate', onUpdate);
        audio.addEventListener('durationchange', onUpdate);
        audio.addEventListener('loadedmetadata', onUpdate);
        audio.addEventListener('play', () => this.updatePlayBtn());
        audio.addEventListener('pause', () => this.updatePlayBtn());
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
        if (this.hasTitleTarget && track) this.titleTarget.textContent = track.title;
        if (this.hasArtistTarget && track) this.artistTarget.textContent = track.artist;
        if (this.hasMuteBtnTarget) {
            this.muteBtnTarget.classList.toggle('is-muted', this.muted);
            this.updateMuteAriaLabel();
        }
        if (this.hasQueueTarget) {
            for (const li of this.queueTarget.querySelectorAll('[data-index]')) {
                li.classList.toggle('is-current', parseInt(li.dataset.index, 10) === this.currentIndex);
            }
        }
        this.updatePlayBtn();
    }

    updatePlayBtn() {
        const playing = !!shared.audio && !shared.audio.paused;
        if (this.hasMenuTarget) this.menuTarget.classList.toggle('is-playing', playing);
        for (const btn of this.playBtnTargets) {
            btn.textContent = playing ? '⏸' : '▶';
            btn.setAttribute('aria-label', playing ? 'Pauza' : 'Přehrát');
        }
        this.updateMuteAriaLabel();
    }

    updateMuteAriaLabel() {
        if (!this.hasMuteBtnTarget) return;
        const compact = this.menuTarget?.classList.contains('is-compact');
        if (compact) {
            const playing = !!shared.audio && !shared.audio.paused;
            this.muteBtnTarget.setAttribute('aria-label', playing ? 'Pauza' : 'Přehrát');
            this.muteBtnTarget.removeAttribute('aria-pressed');
        } else {
            this.muteBtnTarget.setAttribute('aria-label', this.muted ? 'Zapnout zvuk' : 'Ztlumit');
            this.muteBtnTarget.setAttribute('aria-pressed', String(this.muted));
        }
    }

    updateProgressUI() {
        const audio = shared.audio;
        if (this.hasProgressTarget && !this.seeking) {
            const ratio = audio && audio.duration ? audio.currentTime / audio.duration : 0;
            this.progressTarget.value = String(Math.round(ratio * 1000));
        }
        this.updateTimeLabel();
    }

    updateTimeLabel() {
        if (!this.hasTimeTarget) return;
        const audio = shared.audio;
        const cur = audio?.currentTime ?? 0;
        const dur = audio?.duration && Number.isFinite(audio.duration) ? audio.duration : 0;
        this.timeTarget.textContent = `${formatTime(cur)} / ${formatTime(dur)}`;
    }

    disconnect() {
        // Intentionally do NOT touch shared.audio here — the controller may
        // disconnect during Turbo navigation while the audio should keep playing.
        // The Audio is cleaned up on full page unload by the browser.
    }
}
