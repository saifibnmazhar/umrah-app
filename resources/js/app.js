import Alpine from 'alpinejs'

Alpine.start()

window.Alpine = Alpine

Alpine.store('toast', null)

window.showToast = function(message, type = 'info') {
    Alpine.store('toast', { message, type })
}