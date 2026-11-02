import './bootstrap';

// Alpine ships with Livewire (loaded via @livewireScripts), so components register on its init event.
document.addEventListener('alpine:init', () => {
    /**
     * Counts a statistic up from zero when it scrolls into view.
     * Respects prefers-reduced-motion by showing the final value immediately.
     */
    window.Alpine.data('countUp', (target, duration = 1600) => ({
        display: 0,
        init() {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || !('IntersectionObserver' in window)) {
                this.display = target;
                return;
            }
            const observer = new IntersectionObserver((entries) => {
                if (!entries[0].isIntersecting) return;
                observer.disconnect();
                const start = performance.now();
                const step = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    this.display = Math.round(target * (1 - Math.pow(1 - progress, 3)));
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });
            observer.observe(this.$el);
        },
        get formatted() {
            return this.display.toLocaleString();
        },
    }));
});
