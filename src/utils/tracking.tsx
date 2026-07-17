// This is an SPA: after the initial page load, every navigation (React
// Router's navigate(), any in-app <Link>/<NavLink>) is a client-side
// history.pushState — never a browser reload. GTM's container load,
// gtag('config', …) (header.php), and any standalone Meta Pixel snippet a
// project adds only run ONCE, on that first real page load. None of them see
// an SPA route change by themselves, so a URL/page-view-based conversion
// trigger (e.g. "fires on /thank-you") configured in GTM or an ad platform's
// UI will never fire for a redirect that never reloads the browser.
//
// This is not a hypothetical: a project built on this base shipped its
// tracking codes, and the client immediately reported the form-submission
// conversion wasn't registering — this exact gap was the reason. Wire these
// two helpers into any project that adds analytics:
//
// 1. pushVirtualPageview(path) from Layout.tsx (below) on every route change
//    — every project should have this from day one, whether or not tracking
//    codes are installed yet.
// 2. trackConversion(eventName, params) at the exact moment a form/purchase/
//    signup succeeds — NOT reliant on the URL it redirects to afterwards.
//    Call it right before navigating to a "thank you"/confirmation route.

// Fires on every SPA route change so URL/page-view-based triggers (a GTM
// "History Change" trigger, GA4's own page_view count) see it like a real
// page load would.
export const pushVirtualPageview = (path: string) => {
    const dataLayer = (window as any).dataLayer;
    if (Array.isArray(dataLayer)) {
        dataLayer.push({ event: 'virtual_page_view', page_path: path });
    }
    if (typeof (window as any).gtag === 'function') {
        (window as any).gtag('event', 'page_view', { page_path: path });
    }
};

// Fires a named conversion event directly, independent of any URL/page-view
// trigger. Call this at the point of success (form submitted, purchase
// completed) — never assume the page it redirects to afterwards will be
// tracked on its own.
export const trackConversion = (eventName: string, params: Record<string, any> = {}) => {
    const dataLayer = (window as any).dataLayer;
    if (Array.isArray(dataLayer)) {
        dataLayer.push({ event: eventName, ...params });
    }
    if (typeof (window as any).gtag === 'function') {
        (window as any).gtag('event', eventName, params);
    }
    // Only if a project adds a standalone Meta Pixel snippet (not GTM-managed) —
    // harmless no-op otherwise. Meta's own standard event names are Capitalized
    // (e.g. 'Lead', 'Purchase') — pass those as eventName when targeting Meta.
    if (typeof (window as any).fbq === 'function') {
        (window as any).fbq('track', eventName);
    }
};
