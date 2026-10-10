const SMART_PHARMACY_APP = window.__smartPharmacyAppState || (window.__smartPharmacyAppState = {
    initialized: false,
    screenshotTool: {
        button: null,
        visible: false,
        keyListenerBound: false,
        loadingPromise: null,
        isCapturing: false,
    },
});

const SCREENSHOT_TOOL_SHORTCUT = {
    key: 'b',
    ctrl: false,
    shift: true,
    alt: false,
    meta: false,
};

const SCREENSHOT_TOOL_SCRIPT_SRC = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmartPharmacyApp, { once: true });
} else {
    initSmartPharmacyApp();
}

function initSmartPharmacyApp() {
    if (SMART_PHARMACY_APP.initialized) {
        return;
    }

    SMART_PHARMACY_APP.initialized = true;

    const body = document.body;
    const toggleButton = document.querySelector('.menu-toggle');

    if (toggleButton) {
        toggleButton.addEventListener('click', function () {
            body.classList.toggle('sidebar-open');
        });
    }

    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            let valid = true;
            const fields = form.querySelectorAll('input[required], select[required], textarea[required]');

            fields.forEach(function (field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#d92d20';
                } else {
                    field.style.borderColor = '';
                }
            });

            if (!valid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });

    initBillingPreview();
    initCustomerSearch();
    initCustomerStepControls();
    initFlashToasts();
    initOrderModal();
    initHiddenScreenshotTool();
}

function initBillingPreview() {
    const medicineSelect = document.getElementById('medicine_id');
    const quantityInput = document.getElementById('quantity_sold');
    const priceBox = document.getElementById('price_preview');
    const subtotalBox = document.getElementById('line_subtotal');
    const stockBox = document.getElementById('stock_preview');

    if (!medicineSelect || !quantityInput || !priceBox || !subtotalBox) {
        return;
    }

    function updatePreview() {
        const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
        const price = parseFloat(selectedOption?.dataset.price || '0');
        const stock = parseInt(selectedOption?.dataset.stock || '0', 10);
        const quantity = parseInt(quantityInput.value || '0', 10);
        const subtotal = price * quantity;

        priceBox.textContent = 'Unit Price: Rs. ' + price.toFixed(2);
        subtotalBox.textContent = 'Line Subtotal: Rs. ' + subtotal.toFixed(2);
        if (stockBox) {
            stockBox.textContent = 'Available stock: ' + stock;
        }

        if (quantity > stock && stock > 0) {
            quantityInput.setCustomValidity('Quantity cannot be more than available stock.');
        } else {
            quantityInput.setCustomValidity('');
        }
    }

    medicineSelect.addEventListener('change', updatePreview);
    quantityInput.addEventListener('input', updatePreview);
    updatePreview();
}

function initOrderModal() {
    const modal = document.getElementById('orderModal');
    if (!modal) {
        return;
    }

    let hideTimer = null;

    const openModal = function () {
        window.clearTimeout(hideTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        window.requestAnimationFrame(function () {
            modal.classList.add('active');
        });
    };

    const closeModal = function () {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');

        hideTimer = window.setTimeout(function () {
            if (!modal.classList.contains('active')) {
                modal.hidden = true;
            }
        }, 180);
    };

    if (!modal.dataset.boundModal) {
        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-modal-close]')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) {
                event.preventDefault();
                closeModal();
            }
        });

        modal.dataset.boundModal = '1';
    }

    if (modal.dataset.autoshow === '1') {
        openModal();
    } else {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('active');
    }

    window.closeOrderModal = closeModal;
    window.openOrderModal = openModal;
}

function initCustomerSearch() {
    const form = document.getElementById('shopSearchForm');
    const input = document.getElementById('medicineSearch');
    const suggestionsBox = document.getElementById('searchSuggestions');
    const categoryFilter = document.getElementById('categoryFilter');

    if (!form || !input || !suggestionsBox) {
        return;
    }

    const suggestionsUrl = form.dataset.suggestionsUrl || 'medicine_suggestions.php';
    let debounceTimer = null;
    let activeIndex = -1;
    let latestButtons = [];

    function clearSuggestions() {
        suggestionsBox.innerHTML = '';
        suggestionsBox.hidden = true;
        activeIndex = -1;
        latestButtons = [];
    }

    function showLoadingState() {
        suggestionsBox.innerHTML = '<div class="search-suggestions-state">Searching medicines...</div>';
        suggestionsBox.hidden = false;
    }

    function renderSuggestions(items) {
        if (!items.length) {
            suggestionsBox.innerHTML = '<div class="search-suggestions-state empty">No medicines found. Try a different keyword.</div>';
            suggestionsBox.hidden = false;
            activeIndex = -1;
            latestButtons = [];
            return;
        }

        const currentQuery = input.value.trim();

        suggestionsBox.innerHTML = items.map(function (item) {
            const stockClass = item.stock <= 0 ? 'badge-danger' : (item.stock <= 5 ? 'badge-warning' : 'badge-success');
            const stockLabel = item.stock <= 0 ? 'Out of stock' : (item.stock <= 5 ? 'Low stock' : 'In stock');
            return (
                '<button type="button" class="search-suggestion" data-name="' + escapeHtml(item.name) + '">' +
                    '<img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">' +
                    '<div class="search-suggestion-body">' +
                        '<strong>' + highlightMatch(item.name, currentQuery) + '</strong>' +
                        '<span>' + highlightMatch(item.category, currentQuery) + '</span>' +
                    '</div>' +
                    '<div class="search-suggestion-meta">' +
                        '<span>' + escapeHtml('Rs. ' + Number(item.price).toFixed(2)) + '</span>' +
                        '<span class="badge ' + stockClass + '">' + escapeHtml(stockLabel) + '</span>' +
                    '</div>' +
                '</button>'
            );
        }).join('');

        suggestionsBox.hidden = false;
        latestButtons = Array.from(suggestionsBox.querySelectorAll('.search-suggestion'));
        activeIndex = -1;
    }

    function setActiveSuggestion(nextIndex) {
        if (!latestButtons.length) {
            return;
        }

        activeIndex = ((nextIndex % latestButtons.length) + latestButtons.length) % latestButtons.length;
        latestButtons.forEach(function (button, index) {
            button.classList.toggle('active', index === activeIndex);
            if (index === activeIndex) {
                button.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function submitActiveSuggestion() {
        if (activeIndex < 0 || !latestButtons[activeIndex]) {
            form.submit();
            return;
        }

        latestButtons[activeIndex].click();
    }

    async function fetchSuggestions() {
        const params = new URLSearchParams();
        params.set('q', input.value.trim());
        if (categoryFilter && categoryFilter.value) {
            params.set('category', categoryFilter.value);
        }

        if (!input.value.trim()) {
            clearSuggestions();
            return;
        }

        showLoadingState();

        try {
            const response = await fetch(suggestionsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) {
                clearSuggestions();
                return;
            }

            const payload = await response.json();
            renderSuggestions(payload.items || []);
        } catch (error) {
            clearSuggestions();
        }
    }

    input.addEventListener('input', function () {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(fetchSuggestions, 180);
    });

    input.addEventListener('keydown', function (event) {
        if (suggestionsBox.hidden) {
            if (event.key === 'Enter') {
                form.submit();
            }
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveSuggestion(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveSuggestion(activeIndex - 1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            submitActiveSuggestion();
        } else if (event.key === 'Escape') {
            clearSuggestions();
        }
    });

    if (categoryFilter) {
        categoryFilter.addEventListener('change', fetchSuggestions);
    }

    suggestionsBox.addEventListener('click', function (event) {
        const button = event.target.closest('.search-suggestion');
        if (!button) {
            return;
        }

        input.value = button.dataset.name || input.value;
        clearSuggestions();
        form.submit();
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            clearSuggestions();
        }
    });
}

function initCustomerStepControls() {
    document.querySelectorAll('.qty-stepper').forEach(function (stepper) {
        const input = stepper.querySelector('input[type="number"]');
        const decrease = stepper.querySelector('[data-stepper="decrease"]');
        const increase = stepper.querySelector('[data-stepper="increase"]');

        if (!input || !decrease || !increase) {
            return;
        }

        decrease.addEventListener('click', function () {
            const min = parseInt(input.min || '1', 10);
            const current = parseInt(input.value || '1', 10);
            input.value = Math.max(min, current - 1);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        increase.addEventListener('click', function () {
            const max = parseInt(input.max || '999', 10);
            const current = parseInt(input.value || '1', 10);
            input.value = Math.min(max, current + 1);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
}

function initFlashToasts() {
    document.querySelectorAll('.toast-stack .alert').forEach(function (toast) {
        toast.classList.add('toast');
        window.setTimeout(function () {
            toast.classList.add('toast-hide');
        }, 3200);
    });
}

function initHiddenScreenshotTool() {
    const state = SMART_PHARMACY_APP.screenshotTool;

    if (!state.button) {
        state.button = createScreenshotButton();
        document.body.appendChild(state.button);
    }

    if (!state.keyListenerBound) {
        document.addEventListener('keydown', handleScreenshotShortcut, true);
        state.keyListenerBound = true;
    }
}

function createScreenshotButton() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'screenshot-fab';
    button.setAttribute('aria-label', 'Capture full page screenshot');
    button.setAttribute('aria-hidden', 'true');
    button.title = 'Capture full page screenshot';
    button.innerHTML = '<span class="screenshot-fab-icon">⧉</span><span class="screenshot-fab-label">Screenshot</span>';
    button.addEventListener('click', function () {
        captureVisiblePageScreenshot();
    });
    return button;
}

function handleScreenshotShortcut(event) {
    if (SMART_PHARMACY_APP.screenshotTool.isCapturing) {
        return;
    }

    if (!matchesScreenshotShortcut(event)) {
        return;
    }

    event.preventDefault();
    toggleScreenshotToolVisibility();
}

function matchesScreenshotShortcut(event) {
    return Boolean(
        event.key &&
        event.key.toLowerCase() === SCREENSHOT_TOOL_SHORTCUT.key &&
        event.ctrlKey === SCREENSHOT_TOOL_SHORTCUT.ctrl &&
        event.shiftKey === SCREENSHOT_TOOL_SHORTCUT.shift &&
        event.altKey === SCREENSHOT_TOOL_SHORTCUT.alt &&
        event.metaKey === SCREENSHOT_TOOL_SHORTCUT.meta
    );
}

function toggleScreenshotToolVisibility(forceVisible) {
    const state = SMART_PHARMACY_APP.screenshotTool;
    const button = state.button;

    if (!button) {
        return;
    }

    const shouldShow = typeof forceVisible === 'boolean' ? forceVisible : !state.visible;
    state.visible = shouldShow;
    button.classList.toggle('is-visible', shouldShow);
    button.classList.toggle('is-hidden', !shouldShow);
    button.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
}

async function captureVisiblePageScreenshot() {
    const state = SMART_PHARMACY_APP.screenshotTool;
    const button = state.button;

    if (!button || state.isCapturing) {
        return;
    }

    state.isCapturing = true;
    const wasVisible = state.visible;
    button.classList.add('is-capturing');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.style.visibility = 'hidden';
    button.style.pointerEvents = 'none';

    await nextAnimationFrame();
    await nextAnimationFrame();

    try {
        const html2canvas = await loadHtml2CanvasOnce();
        const fullWidth = Math.max(
            document.documentElement.scrollWidth,
            document.body.scrollWidth,
            window.innerWidth
        );
        const fullHeight = Math.max(
            document.documentElement.scrollHeight,
            document.body.scrollHeight,
            window.innerHeight
        );

        const canvas = await html2canvas(document.body, {
            backgroundColor: getPageBackgroundColor(),
            useCORS: true,
            allowTaint: true,
            scale: Math.min((window.devicePixelRatio || 1) * 2, 3),
            width: fullWidth,
            height: fullHeight,
            windowWidth: fullWidth,
            windowHeight: fullHeight,
            scrollX: 0,
            scrollY: 0,
        });

        const blob = await canvasToBlob(canvas);
        if (!blob) {
            throw new Error('Screenshot capture returned an empty image.');
        }

        downloadScreenshotBlob(blob, buildScreenshotFileName());
    } catch (error) {
        window.alert('Screenshot capture failed. Please try again.');
    } finally {
        button.classList.remove('is-capturing');
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.style.visibility = '';
        button.style.pointerEvents = '';
        state.isCapturing = false;
        toggleScreenshotToolVisibility(wasVisible);
    }
}

function loadHtml2CanvasOnce() {
    const state = SMART_PHARMACY_APP.screenshotTool;

    if (typeof window.html2canvas === 'function') {
        return Promise.resolve(window.html2canvas);
    }

    if (state.loadingPromise) {
        return state.loadingPromise;
    }

    state.loadingPromise = new Promise(function (resolve, reject) {
        const existingScript = document.querySelector('script[data-smart-pharmacy-html2canvas="1"]');
        if (existingScript && typeof window.html2canvas === 'function') {
            resolve(window.html2canvas);
            return;
        }

        const script = document.createElement('script');
        script.src = SCREENSHOT_TOOL_SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        script.dataset.smartPharmacyHtml2Canvas = '1';

        script.onload = function () {
            if (typeof window.html2canvas === 'function') {
                resolve(window.html2canvas);
            } else {
                reject(new Error('html2canvas failed to initialize.'));
            }
        };

        script.onerror = function () {
            reject(new Error('Unable to load html2canvas.'));
        };

        document.head.appendChild(script);
    }).catch(function (error) {
        state.loadingPromise = null;
        throw error;
    });

    return state.loadingPromise;
}

function canvasToBlob(canvas) {
    return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
            resolve(blob);
        }, 'image/png');
    });
}

function downloadScreenshotBlob(blob, fileName) {
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = fileName;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(function () {
        URL.revokeObjectURL(downloadUrl);
    }, 1500);
}

function buildScreenshotFileName() {
    const stamp = new Date();
    const datePart = stamp.getFullYear().toString() +
        padNumber(stamp.getMonth() + 1) +
        padNumber(stamp.getDate());
    const timePart = padNumber(stamp.getHours()) + padNumber(stamp.getMinutes()) + padNumber(stamp.getSeconds());
    return 'smart-pharmacy-screenshot-' + datePart + '-' + timePart + '.png';
}

function getPageBackgroundColor() {
    const computed = window.getComputedStyle(document.body);
    return computed.backgroundColor && computed.backgroundColor !== 'rgba(0, 0, 0, 0)' ? computed.backgroundColor : '#ffffff';
}

function padNumber(value) {
    return String(value).padStart(2, '0');
}

function nextAnimationFrame() {
    return new Promise(function (resolve) {
        window.requestAnimationFrame(function () {
            resolve();
        });
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function highlightMatch(value, query) {
    const safeValue = escapeHtml(value);
    const trimmedQuery = String(query || '').trim();

    if (!trimmedQuery) {
        return safeValue;
    }

    const escapedQuery = trimmedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const matcher = new RegExp(escapedQuery, 'ig');

    return safeValue.replace(matcher, function (match) {
        return '<mark class="search-highlight">' + match + '</mark>';
    });
}
