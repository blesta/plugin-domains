(function() {
    'use strict';

    // DOM Elements
    var form = document.getElementById('domainLookupForm');
    var searchInput = document.getElementById('domain');
    var loadingState = document.getElementById('loadingState');
    var resultsContent = document.getElementById('resultsContent');

    // Initialize
    init();

    function init() {
        if (form) {
            form.addEventListener('submit', handleSearch);
        }
        if (searchInput) {
            searchInput.focus();
        }
    }

    function handleSearch(e) {
        e.preventDefault();

        var domain = searchInput.value.trim();
        if (!domain) {
            return;
        }

        // Show loading state
        showLoading();

        // Get CSRF token from form
        var csrfTokenInput = document.querySelector('input[name="_csrf_token"]');
        var csrfToken = csrfTokenInput ? csrfTokenInput.value : '';

        // Make AJAX request
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                'domain': domain,
                '_csrf_token': csrfToken
            })
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            console.log('Response data:', data);
            if (data && data.success) {
                displayResults(data);
            } else {
                displayError(data && data.message ? data.message : 'An error occurred');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            displayError('Failed to lookup domain: ' + error.message);
        });
    }

    function showLoading() {
        loadingState.style.display = 'block';
        resultsContent.style.display = 'none';
    }

    function displayResults(data) {
        loadingState.style.display = 'none';
        resultsContent.style.display = 'block';

        // Update domain name
        var searchedDomain = document.getElementById('searchedDomain');
        if (searchedDomain) {
            searchedDomain.textContent = data.domain;
        }

        // Update alert and badge
        var resultAlert = document.getElementById('resultAlert');
        var statusBadge = document.getElementById('domainStatusBadge');
        var whoisInfo = document.getElementById('whoisInfo');

        if (data.available) {
            resultAlert.className = 'alert alert-success d-flex align-items-center justify-content-between mb-3';
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Available';
            whoisInfo.style.display = 'none';
        } else {
            resultAlert.className = 'alert alert-danger d-flex align-items-center justify-content-between mb-3';
            statusBadge.className = 'badge bg-danger';
            statusBadge.textContent = 'Taken';
            whoisInfo.style.display = 'block';

            // Populate WHOIS data
            if (data.whois_data) {
                updateElement('whoisRegistrar', data.whois_data.registrar);
                updateElement('whoisRegDate', data.whois_data.reg_date);
                updateElement('whoisExpDate', data.whois_data.exp_date);
                updateElement('whoisStatus', data.whois_data.status);
                updateElement('whoisNameServers', data.whois_data.name_servers);
                updateElement('whoisDNSSEC', data.whois_data.dnssec);
            }
        }
    }

    function displayError(message) {
        loadingState.style.display = 'none';
        resultsContent.style.display = 'block';

        var resultAlert = document.getElementById('resultAlert');
        resultAlert.className = 'alert alert-danger d-flex align-items-center justify-content-between mb-3';
        resultAlert.innerHTML = '<div class="d-flex align-items-center">' +
            '<i class="bi bi-exclamation-triangle me-3" style="font-size: 1.5rem;"></i>' +
            '<strong>' + escapeHtml(message) + '</strong>' +
            '</div>';

        var whoisInfo = document.getElementById('whoisInfo');
        if (whoisInfo) {
            whoisInfo.style.display = 'none';
        }
    }

    function updateElement(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.textContent = value || '-';
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
