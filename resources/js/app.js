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

    const path = window.location.pathname

    const tabPrefixes = [
        { prefix: '/dashboard', tab: 'dashboard' },
        { prefix: '/bookings', tab: 'booking' },
        { prefix: '/fingerprints/staff', tab: 'fingerprintStaff' },
        { prefix: '/fingerprints', tab: 'fingerprintAdmin' },
        { prefix: '/visas', tab: 'visaAdmin' },
        { prefix: '/fares', tab: 'ticketAdmin' },
        { prefix: '/settings', tab: 'settings' },
        { prefix: '/reports', tab: 'reports' },
        { prefix: '/districts', tab: 'appManagement' },
        { prefix: '/banks', tab: 'appManagement' },
        { prefix: '/branches', tab: 'appManagement' },
        { prefix: '/booking-conditions', tab: 'appManagement' },
        { prefix: '/city-codes', tab: 'appManagement' },
        { prefix: '/airlines', tab: 'appManagement' },
        { prefix: '/classes', tab: 'appManagement' },
        { prefix: '/airline-classes', tab: 'appManagement' },
        { prefix: '/airline-cities', tab: 'appManagement' },
        { prefix: '/customers', tab: 'appManagement' },
        { prefix: '/visa-agents', tab: 'appManagement' },
        { prefix: '/ticket-agents', tab: 'appManagement' },
        { prefix: '/flight-date-gaps', tab: 'appManagement' },
        { prefix: '/visa-agent-costs', tab: 'appManagement' },
        { prefix: '/visa-selling-prices', tab: 'appManagement' },
        { prefix: '/currency-rates', tab: 'appManagement' },
        { prefix: '/transaction-types', tab: 'appManagement' },
        { prefix: '/routes', tab: 'appManagement' },
        { prefix: '/ticket-fares', tab: 'appManagement' },
        { prefix: '/packages', tab: 'appManagement' },
        { prefix: '/users', tab: 'appManagement' },
    ]

    let activeTab = null
    for (const { prefix, tab } of tabPrefixes) {
        if (path === prefix || path.startsWith(prefix + '/')) {
            activeTab = tab
            break
        }
    }

    // Reference pattern: clear all nav items, then activate the matching one
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('bg-white', 'text-slate-800')
        item.classList.add('text-slate-400')
    })

    if (activeTab) {
        const activeItem = document.querySelector(`.nav-item[data-tab="${activeTab}"]`)
        if (activeItem) {
            activeItem.classList.add('bg-white', 'text-slate-800')
            activeItem.classList.remove('text-slate-400')
        }
    }
})