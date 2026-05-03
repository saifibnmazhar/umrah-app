<div x-data="{ 
    show: false, 
    message: '', 
    type: 'info' 
}" 
x-init="$watch('$store.toast', (value) => { if(value) { message = value.message; type = value.type; show = true; setTimeout(() => show = false, 3000); $store.toast = null } })"
x-show="show"
x-transition:enter="transition ease-out duration-300"
x-transition:enter-start="translate-x-full opacity-0"
x-transition:enter-end="translate-x-0 opacity-100"
x-transition:leave="transition ease-in duration-300"
x-transition:leave-start="translate-x-0 opacity-100"
x-transition:leave-end="translate-x-full opacity-0"
class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium"
:class="{
    'bg-slate-700': type === 'info',
    'bg-emerald-600': type === 'success', 
    'bg-red-500': type === 'error',
    'bg-amber-500': type === 'warning'
}">
    <span x-text="message"></span>
</div>