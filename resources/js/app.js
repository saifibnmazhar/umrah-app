import Alpine from 'alpinejs'
import './booking.js'

window.Alpine = Alpine

Alpine.store('currency', {
    mode: localStorage.getItem('currency_mode') || 'SAR',
    rate: window.__currencyRate || 0,

    init() {
        this.rate = window.__currencyRate || 0
    },

    toggle() {
        this.mode = this.mode === 'SAR' ? 'BDT' : 'SAR'
        localStorage.setItem('currency_mode', this.mode)
        this.convertAll()
        window.dispatchEvent(new CustomEvent('currency-toggled'))
    },

    format(amount, decimals = 2, rate = null) {
        const num = Number(amount) || 0
        const effectiveRate = rate !== null ? rate : this.rate
        if (this.mode === 'SAR') {
            return 'SAR ' + num.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
        }
        const bdt = num * effectiveRate
        return 'BDT ' + bdt.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
    },

    convertAll() {
        document.querySelectorAll('[data-sar]').forEach(el => {
            const sar = parseFloat(el.dataset.sar)
            const dec = parseInt(el.dataset.dec) || 2
            const rate = el.dataset.rate ? parseFloat(el.dataset.rate) : null
            if (!isNaN(sar)) {
                el.textContent = this.format(sar, dec, rate)
            }
        })
    }
})

Alpine.magic('currency', () => {
    return (amount, decimals = 2, rate = null) => Alpine.store('currency').format(amount, decimals, rate)
})

Alpine.start()

Alpine.store('toast', {})

window.showToast = function(message, type = 'info') {
    Alpine.store('toast', { message, type })
}

document.addEventListener('DOMContentLoaded', () => {
    Alpine.store('currency').init()
    Alpine.store('currency').convertAll()
})