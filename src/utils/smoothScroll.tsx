// Slow, eased programmatic scroll. Native `scroll-behavior: smooth` is often
// too fast for a long jump (e.g. header CTA → footer form); this gives a
// controllable, cinematic ease.
const easeInOutCubic = (t: number) =>
    t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

const smoothScrollTo = (targetY: number, duration = 1600) => {
    const startY = window.scrollY;
    const distance = targetY - startY;
    if (distance === 0) return;
    const start = performance.now();

    const step = (now: number) => {
        const progress = Math.min(1, (now - start) / duration);
        window.scrollTo(0, startY + distance * easeInOutCubic(progress));
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};

export default smoothScrollTo;
