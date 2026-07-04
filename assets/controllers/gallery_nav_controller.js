import { Controller } from '@hotwired/stimulus';

/*
 * Lišta roků v galerii: zvýrazní rok sekce, která je právě pod hlavičkou,
 * a drží aktivní chip viditelný, když lišta na mobilu scrolluje vodorovně.
 */
export default class extends Controller {
    static targets = ['link', 'section'];

    connect() {
        this.onScroll = this.onScroll.bind(this);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        this.onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }

    // Klik na rok: scroll řešíme sami — Turbo by anchor bralo jako novou visit
    // (refetch + re-render celé stránky) a jeho vlastní scroll cíl přestřeluje.
    jump(event) {
        const id = event.currentTarget.getAttribute('href').slice(1);
        const section = document.getElementById(id);
        if (!section) {
            return;
        }
        event.preventDefault();
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(history.state, '', `#${id}`);
    }

    onScroll() {
        if (this.ticking) {
            return;
        }
        this.ticking = true;
        requestAnimationFrame(() => {
            this.ticking = false;
            this.highlightCurrent();
        });
    }

    highlightCurrent() {
        // Aktivní = poslední sekce, jejíž začátek už vyjel nad hranici pod hlavičkou.
        const threshold = this.linkTargets[0]?.getBoundingClientRect().bottom ?? 120;
        let current = this.sectionTargets[0];
        for (const section of this.sectionTargets) {
            // Vůle 32 px: cíl kotvy (scroll-margin) je ~16 px pod lištou a smooth
            // scroll končí se subpixelovou odchylkou — menší vůle nechá aktivní
            // předchozí rok.
            if (section.getBoundingClientRect().top <= threshold + 32) {
                current = section;
            }
        }
        if (!current || current.id === this.activeId) {
            return;
        }
        this.activeId = current.id;
        for (const link of this.linkTargets) {
            const active = link.getAttribute('href') === `#${current.id}`;
            link.classList.toggle('active', active);
            if (active) {
                link.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        }
    }
}
