(function($) {
    'use strict';

    class CountdownTimer {
        constructor(element) {
            this.element = element;
            this.data = JSON.parse(element.dataset.countdown);
            this.endTime = new Date(this.data.endTime).getTime();
            this.offset = new Date(this.data.serverTime).getTime() - Date.now();
            
            this.initializeHTML();
            this.start();
        }

        initializeHTML() {
            this.element.innerHTML = `
                <div class="countdown-unit">
                    <span class="countdown-value" data-days>00</span>
                </div>
                <span class="countdown-separator">:</span>
                <div class="countdown-unit">
                    <span class="countdown-value" data-hours>00</span>
                </div>
                <span class="countdown-separator">:</span>
                <div class="countdown-unit">
                    <span class="countdown-value" data-minutes>00</span>
                </div>
            `;

            this.elements = {
                days: this.element.querySelector('[data-days]'),
                hours: this.element.querySelector('[data-hours]'),
                minutes: this.element.querySelector('[data-minutes]')
            };
        }

        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 60000);
        }

        stop() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }

        update() {
            const now = Date.now() + this.offset;
            const distance = this.endTime - now;

            if (distance < 0) {
                this.stop();
                this.element.style.display = 'none';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

            this.elements.days.textContent = String(days).padStart(2, '0');
            this.elements.hours.textContent = String(hours).padStart(2, '0');
            this.elements.minutes.textContent = String(minutes).padStart(2, '0');
        }
    }

    // Inicializar countdown
    document.querySelectorAll('.wc-countdown').forEach(element => {
        new CountdownTimer(element);
    });

})(jQuery);