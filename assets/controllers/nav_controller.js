import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['burger']

    toggle() {
        const open = this.element.classList.toggle('nav-open')
        this.burgerTarget.setAttribute('aria-expanded', String(open))
    }
}
