// Navigation JavaScript Functions for BM Umrah Booking

function toggleMobileMenu() {
    var menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

function setActiveNav(activePage) {
    document.querySelectorAll('.nav-item').forEach(function(item) {
        item.classList.remove('bg-white', 'text-slate-800');
        item.classList.add('text-slate-400');
    });
    var activeItem = document.querySelector('.nav-item[data-tab="' + activePage + '"]');
    if (activeItem) {
        activeItem.classList.add('bg-white', 'text-slate-800');
        activeItem.classList.remove('text-slate-400');
    }
}

function autoSetActiveNav() {
    var path = window.location.pathname.split('/').pop();
    var pageMap = {
        'dashboard.html': 'dashboard',
        'booking.html': 'booking',
        'fingerprint-admin.html': 'fingerprintAdmin',
        'fingerprint-staff.html': 'fingerprintStaff',
        'visa-admin.html': 'visaAdmin',
        'fare-admin.html': 'ticketAdmin',
        'settings.html': 'settings'
    };
    var reportPages = [
        'statement.html', 'profit-loss-report.html', 'fingerprint-report.html',
        'visa-report.html', 'visa-agent-report.html', 'ticket-agent-report.html',
        'due-report.html', 'reissue-refund-report.html', 'user-wise-sales-report.html',
        'pending-outbound-ticket-report.html', 'payment-receiving-report.html'
    ];
    
    if (reportPages.indexOf(path) !== -1) {
        setActiveNav('reports');
    } else if (pageMap[path]) {
        setActiveNav(pageMap[path]);
    }
}

function loadNavigation() {
    fetch('nav.html')
        .then(function(response) { return response.text(); })
        .then(function(html) {
            var navContainer = document.getElementById('navContainer');
            if (navContainer) {
                navContainer.innerHTML = html;
                autoSetActiveNav();
            }
        })
        .catch(function(error) {
            console.error('Error loading navigation:', error);
        });
}