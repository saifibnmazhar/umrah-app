import Alpine from 'alpinejs'
import './booking.js'

window.Alpine = Alpine
Alpine.start()

Alpine.store('toast', {})

window.showToast = function(message, type = 'info') {
    Alpine.store('toast', { message, type })
}