(function($) {
    'use strict';

    class CountdownTimer {
        constructor(element) {
            if (!element) return;
            
            this.element = element;
            this.initialize();
        }

        initialize() {
            try {
                // Validar que tenemos datos válidos
                const rawData = this.element.dataset.countdown;
                if (!rawData) {
                    console.warn('No countdown data found');
                    return;
                }

                // Intentar parsear los datos JSON con manejo de errores
                this.data = JSON.parse(rawData);
                
                if (!this.data.endTime || !this.data.serverTime) {
                    console.warn('Invalid countdown data format');
                    return;
                }

                // Convertir las fechas y calcular el offset
                this.endTime = new Date(this.data.endTime).getTime();
                this.serverTime = new Date(this.data.serverTime).getTime();
                this.offset = this.serverTime - Date.now();

                // Solo inicializar si tenemos datos válidos
                if (isNaN(this.endTime) || isNaN(this.serverTime)) {
                    console.warn('Invalid date values in countdown');
                    return;
                }

                this.initializeHTML();
                this.start();
            } catch (error) {
                console.error('Error initializing countdown:', error);
                this.element.style.display = 'none';
            }
        }

        initializeHTML() {
            const template = `
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

            this.element.innerHTML = template;

            // Almacenar referencias a los elementos DOM
            this.elements = {
                days: this.element.querySelector('[data-days]'),
                hours: this.element.querySelector('[data-hours]'),
                minutes: this.element.querySelector('[data-minutes]')
            };
        }

        start() {
            if (!this.elements) return;
            
            this.update();
            // Actualizar cada minuto
            this.interval = setInterval(() => this.update(), 60000);
        }

        stop() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }

        update() {
            try {
                const now = Date.now() + this.offset;
                const distance = this.endTime - now;

                // Si el countdown ha terminado
                if (distance < 0) {
                    this.stop();
                    this.element.style.display = 'none';
                    // Opcionalmente, disparar un evento
                    this.element.dispatchEvent(new CustomEvent('countdownComplete'));
                    return;
                }

                // Calcular valores
                const days = Math.max(0, Math.floor(distance / (1000 * 60 * 60 * 24)));
                const hours = Math.max(0, Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)));
                const minutes = Math.max(0, Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)));

                // Actualizar DOM solo si los elementos existen
                if (this.elements.days) {
                    this.elements.days.textContent = String(days).padStart(2, '0');
                }
                if (this.elements.hours) {
                    this.elements.hours.textContent = String(hours).padStart(2, '0');
                }
                if (this.elements.minutes) {
                    this.elements.minutes.textContent = String(minutes).padStart(2, '0');
                }
            } catch (error) {
                console.error('Error updating countdown:', error);
                this.stop();
            }
        }
    }

    // Inicializar countdown con manejo de errores
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const countdownElements = document.querySelectorAll('.wc-countdown');
            countdownElements.forEach(element => {
                new CountdownTimer(element);
            });
        } catch (error) {
            console.error('Error initializing countdowns:', error);
        }
    });

})(jQuery);