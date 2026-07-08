// JS twin of the SCSS `md` breakpoint (`_breakpoints.scss`, `media-down(md)`).
// The -0.02px matches Bootstrap's sub-pixel rounding guard — JS and CSS must
// flip to mobile at exactly the same width (at a plain 768px an iPad portrait
// viewport would get the desktop layout with mobile behavior).
const MOBILE_MEDIA = '(max-width: 767.98px)';

export function isMobile() {
    return window.matchMedia(MOBILE_MEDIA).matches;
}
