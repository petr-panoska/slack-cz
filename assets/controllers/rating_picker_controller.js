import { Controller } from '@hotwired/stimulus';

// Click-to-set star rating (whole stars only, no halves). Mirrors the read-only
// star display on the line detail page, but interactive: clicking star N sets
// the hidden input to N; clicking the already-active top star again clears it.
export default class extends Controller {
    static targets = ['input', 'star'];

    select(event) {
        const value = parseInt(event.params.value, 10);
        const current = parseInt(this.inputTarget.value, 10) || 0;
        this.setValue(value === current ? null : value);
    }

    setValue(value) {
        this.inputTarget.value = value ?? '';
        this.starTargets.forEach((star) => {
            const starValue = parseInt(star.dataset.ratingPickerValueParam, 10);
            star.classList.toggle('rating-picker__star--active', value != null && starValue <= value);
        });
    }
}
