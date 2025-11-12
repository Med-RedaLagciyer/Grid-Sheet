/*
 * Welcome to your app's main JavaScript file!
 * All your JavaScript dependencies will be imported here
 */

// Import styles (changed from .css to .scss)
import './styles/app.scss';

// Import Tabler theme script (should load first)
import './vendor/tabler/dist/js/tabler-theme.min.js';

// Import Tabler main library
import './vendor/tabler/dist/js/tabler.min.js';

// Import jQuery
import $ from 'jquery';
window.$ = window.jQuery = $;

// Import Axios
import axios from 'axios';
window.axios = axios;

// Import Moment.js
import moment from 'moment';
window.moment = moment;

// Import SweetAlert2
import Swal from 'sweetalert2';
window.Swal = Swal;

// Import Toastr
import toastr from 'toastr';
window.toastr = toastr;

// Import Smooth Scroll
import SmoothScroll from 'smooth-scroll';

// Import FOS Routing
import Routing from 'fos-router';

// Import the routes JSON file
const routes = require('../public/js/fos_js_routes.json');
Routing.setRoutingData(routes);

// Expose globally
window.Routing = Routing;

// Import SheetJS for Excel handling
import * as XLSX from 'xlsx';
window.XLSX = XLSX;

// Import Select2
import 'select2';

// Import Ladda
import * as Ladda from 'ladda';
window.Ladda = Ladda;

// Import Notyf
import { Notyf } from 'notyf';
window.Notyf = Notyf;

// Initialize Notyf globally (optional)
window.notyf = new Notyf({
    duration: 3000,
    position: { x: 'right', y: 'top' }
});

// Import DataTables
import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-responsive-bs5';

// Make it available globally
window.DataTable = DataTable;


// DOM ready handler
document.addEventListener('DOMContentLoaded', () => {
    var themeConfig = {
        theme: 'light',
        'theme-base': 'slate',
        'theme-font': 'sans-serif',
        'theme-primary': 'cyan',
        'theme-radius': '0',
    };
    
    var url = new URL(window.location);
    var form = document.getElementById('offcanvasSettings');
    var resetButton = document.getElementById('reset-changes');
    
    if (form && resetButton) {
        var checkItems = function () {
            for (var key in themeConfig) {
                var value = window.localStorage['tabler-' + key] || themeConfig[key];
                if (!!value) {
                    var radios = form.querySelectorAll(`[name="${key}"]`);
                    if (!!radios) {
                        radios.forEach((radio) => {
                            radio.checked = radio.value === value;
                        });
                    }
                }
            }
        };
        
        form.addEventListener('change', function (event) {
            var target = event.target,
                name = target.name,
                value = target.value;
            for (var key in themeConfig) {
                if (name === key) {
                    document.documentElement.setAttribute('data-bs-' + key, value);
                    window.localStorage.setItem('tabler-' + key, value);
                    url.searchParams.set(key, value);
                }
            }
            window.history.pushState({}, '', url);
        });
        
        resetButton.addEventListener('click', function () {
            for (var key in themeConfig) {
                var value = themeConfig[key];
                document.documentElement.removeAttribute('data-bs-' + key);
                window.localStorage.removeItem('tabler-' + key);
                url.searchParams.delete(key);
            }
            checkItems();
            window.history.pushState({}, '', url);
        });
        
        checkItems();
    }

    const scroll = new SmoothScroll('a[href*="#"]', {
        speed: 800,
        speedAsDuration: true
    });
});

console.log('Tabler dashboard loaded! 🚀');