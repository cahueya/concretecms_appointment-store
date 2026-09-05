(function () {
    'use strict';

    function parseDays(value) {
        try {
            var days = JSON.parse(value || '[]');
            if (!Array.isArray(days)) {
                return [];
            }
            return days.filter(function (day) {
                return /^\d{4}-\d{2}-\d{2}$/.test(day);
            }).sort();
        } catch (e) {
            return [];
        }
    }

    function addParam(url, name, value) {
        var separator = url.indexOf('?') === -1 ? '?' : '&';
        return url + separator + encodeURIComponent(name) + '=' + encodeURIComponent(value);
    }

    function requestJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed with HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function postFormJson(url, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            if (data[key] !== null && data[key] !== undefined) {
                body.set(key, String(data[key]));
            }
        });

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                return {response: response, payload: payload};
            });
        });
    }

    function dateFromISO(day) {
        var parts = day.split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]), 12, 0, 0);
    }

    function formatDay(day) {
        var date = dateFromISO(day);
        try {
            return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).format(date);
        } catch (e) {
            return day;
        }
    }

    function localISO(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function buildFlatpickrLocale() {
        var language = document.documentElement.lang || navigator.language || 'en';
        var weekdaysLong = [];
        var weekdaysShort = [];
        var monthsLong = [];
        var monthsShort = [];
        var i;

        try {
            var sunday = new Date(2024, 0, 7, 12, 0, 0);
            for (i = 0; i < 7; i += 1) {
                var weekday = new Date(sunday.getTime());
                weekday.setDate(sunday.getDate() + i);
                weekdaysLong.push(new Intl.DateTimeFormat(language, {weekday: 'long'}).format(weekday));
                weekdaysShort.push(new Intl.DateTimeFormat(language, {weekday: 'short'}).format(weekday));
            }

            for (i = 0; i < 12; i += 1) {
                var month = new Date(2024, i, 1, 12, 0, 0);
                monthsLong.push(new Intl.DateTimeFormat(language, {month: 'long'}).format(month));
                monthsShort.push(new Intl.DateTimeFormat(language, {month: 'short'}).format(month));
            }
        } catch (e) {
            return {};
        }

        return {
            weekdays: {
                shorthand: weekdaysShort,
                longhand: weekdaysLong
            },
            months: {
                shorthand: monthsShort,
                longhand: monthsLong
            },
            firstDayOfWeek: /^en-US(?:-|$)/i.test(language) ? 0 : 1,
            rangeSeparator: ' – '
        };
    }

    function initPicker(root) {
        if (root.dataset.appointmentStoreInitialized === '1') {
            return;
        }
        root.dataset.appointmentStoreInitialized = '1';

        var productID = parseInt(root.dataset.productId || '0', 10);
        var mode = root.dataset.displayMode || 'auto';
        var days = parseDays(root.dataset.days);
        var reservedDays = parseDays(root.dataset.reservedDays);
        var calendarDays = days.concat(reservedDays).filter(function (day, index, allDays) {
            return allDays.indexOf(day) === index;
        }).sort();
        var slotsUrl = root.dataset.slotsUrl || '';
        var preflightUrl = root.dataset.preflightUrl || '';
        var machineFieldName = root.dataset.machineField || 'appointment_store_slot_id';
        var reservationTokenFieldName = root.dataset.reservationTokenField || 'appointment_store_reservation_token';
        var selectWrap = root.querySelector('.appointment-store-select-wrap');
        var select = root.querySelector('.appointment-store-select');
        var displayInput = root.querySelector('.appointment-store-display-value');
        var machineInput = root.querySelector('.appointment-store-machine-value');
        var reservationTokenInput = root.querySelector('.appointment-store-reservation-token');
        var datePicker = root.querySelector('.appointment-store-date-picker');
        var dateInput = root.querySelector('.appointment-store-date');
        var times = root.querySelector('.appointment-store-times');
        var status = root.querySelector('.appointment-store-status');
        var conflict = root.querySelector('.appointment-store-conflict');
        var conflictTitle = root.querySelector('.appointment-store-conflict-title');
        var conflictMessage = root.querySelector('.appointment-store-conflict-message');
        var conflictRefreshed = root.querySelector('.appointment-store-conflict-refreshed');
        var conflictAction = root.querySelector('.appointment-store-conflict-action');

        if (!productID || !select || !slotsUrl || !displayInput || !machineInput || !reservationTokenInput) {
            return;
        }

        // Keep the normal Community Store option as a no-JavaScript fallback. With
        // JavaScript enabled, submit the human-readable option value separately from
        // the internal slot ID used by Appointment Store.
        var optionFieldName = select.getAttribute('name') || '';
        if (optionFieldName) {
            displayInput.setAttribute('name', optionFieldName);
            machineInput.setAttribute('name', machineFieldName);
            reservationTokenInput.setAttribute('name', reservationTokenFieldName);
            select.removeAttribute('name');
            select.removeAttribute('required');
        }

        function setStatus(message, kind) {
            if (!status) {
                return;
            }
            status.textContent = message || '';
            status.classList.remove('text-danger', 'text-success', 'text-warning', 'text-muted');
            status.classList.add(
                kind === 'error' ? 'text-danger' :
                    (kind === 'success' ? 'text-success' : (kind === 'warning' ? 'text-warning' : 'text-muted'))
            );
        }

        function syncSelectedOption() {
            var previousSlotID = machineInput.value;
            var option = select.options[select.selectedIndex] || null;
            var slotID = option && option.value ? option.value : '';
            machineInput.value = slotID;
            displayInput.value = slotID && option ? (option.dataset.display || option.textContent.trim()) : '';
            if (slotID !== previousSlotID) {
                reservationTokenInput.value = '';
            }
        }

        function clearSelection() {
            select.value = '';
            syncSelectedOption();
            if (times) {
                Array.prototype.forEach.call(times.querySelectorAll('.appointment-store-time'), function (button) {
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-outline-primary');
                    button.setAttribute('aria-pressed', 'false');
                });
            }
        }

        var conflictStorageKey = 'appointment-store-conflict-' + productID;

        function focusAppointmentPicker() {
            if (selectWrap && !selectWrap.classList.contains('d-none')) {
                select.focus();
                return;
            }
            if (dateInput) {
                if (dateInput._flatpickr) {
                    dateInput._flatpickr.open();
                } else {
                    dateInput.focus();
                }
            }
        }

        function showConflict(reason) {
            if (!conflict) {
                return;
            }

            var title = root.dataset.unavailableTitle || 'Appointment no longer available';
            var message = root.dataset.conflictText || 'The selected appointment is no longer available. Please choose another available appointment.';

            if (reason === 'reserved') {
                title = root.dataset.reservedTitle || 'Appointment currently being booked';
                message = root.dataset.reservedText || 'This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.';
            } else if (reason === 'minimum_notice') {
                title = root.dataset.minimumNoticeTitle || 'Appointment can no longer be booked';
                message = root.dataset.minimumNoticeText || 'This appointment can no longer be booked because the minimum booking notice has passed.';
            }

            conflict.classList.remove('d-none');
            if (conflictTitle) {
                conflictTitle.textContent = title;
            }
            if (conflictMessage) {
                conflictMessage.textContent = message;
            }
            if (conflictRefreshed) {
                conflictRefreshed.textContent = root.dataset.refreshedText || 'Availability has been refreshed.';
            }
            if (conflictAction) {
                conflictAction.textContent = root.dataset.chooseAnotherText || 'Choose another appointment';
                conflictAction.classList.toggle('d-none', !days.length);
            }
        }

        if (conflictAction) {
            conflictAction.addEventListener('click', focusAppointmentPicker);
        }

        try {
            if (window.sessionStorage) {
                var storedConflictReason = window.sessionStorage.getItem(conflictStorageKey);
                if (storedConflictReason) {
                    window.sessionStorage.removeItem(conflictStorageKey);
                    showConflict(storedConflictReason === '1' ? 'unavailable' : storedConflictReason);
                }
            }
        } catch (e) {
        }

        select.addEventListener('change', syncSelectedOption);
        syncSelectedOption();

        // Appointment products use a reservation preflight before Community Store's
        // normal add-to-cart handler. This turns an expected booking race into an
        // inline product-page warning instead of Community Store's generic error page.
        var form = root.closest('form');
        var preflightPending = false;
        var preflightReadySlot = '';
        if (form) {
            form.addEventListener('click', function (event) {
                var button = event.target.closest('.store-btn-add-to-cart');
                if (!button) {
                    return;
                }

                if (!machineInput.value) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    if (!days.length && reservedDays.length) {
                        setStatus(
                            root.dataset.reservedText || 'This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.',
                            'warning'
                        );
                    } else {
                        setStatus(root.dataset.chooseText || 'Please choose an appointment.', 'error');
                        focusAppointmentPicker();
                    }
                    return;
                }

                // The second click is generated after a successful preflight and is
                // allowed to continue into Community Store's existing handler.
                if (preflightReadySlot && preflightReadySlot === machineInput.value) {
                    preflightReadySlot = '';
                    return;
                }

                if (!preflightUrl || preflightPending) {
                    if (preflightPending) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') {
                            event.stopImmediatePropagation();
                        }
                    }
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }

                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    if (typeof form.reportValidity === 'function') {
                        form.reportValidity();
                    }
                    return;
                }

                var tokenInput = form.querySelector('input[name="ccm_token"]');
                var selectedSlot = machineInput.value;
                reservationTokenInput.value = '';
                preflightPending = true;
                button.disabled = true;

                postFormJson(preflightUrl, {
                    productID: productID,
                    slotID: selectedSlot,
                    ccm_token: tokenInput ? tokenInput.value : ''
                }).then(function (result) {
                    if (result.response.ok && result.payload && result.payload.ok && result.payload.reservationToken) {
                        reservationTokenInput.value = String(result.payload.reservationToken);
                        preflightReadySlot = selectedSlot;
                        preflightPending = false;
                        button.disabled = false;
                        button.click();
                        return;
                    }

                    preflightPending = false;
                    button.disabled = false;
                    reservationTokenInput.value = '';

                    if (result.response.status === 409 || (result.payload && result.payload.conflict)) {
                        try {
                            if (window.sessionStorage) {
                                window.sessionStorage.setItem(
                                    conflictStorageKey,
                                    (result.payload && result.payload.reason) ? String(result.payload.reason) : 'unavailable'
                                );
                            }
                        } catch (e) {
                        }
                        window.location.reload();
                        return;
                    }

                    setStatus(
                        (result.payload && result.payload.message) || root.dataset.verifyErrorText || 'Unable to verify appointment availability. Please try again.',
                        'error'
                    );
                }).catch(function () {
                    preflightPending = false;
                    button.disabled = false;
                    reservationTokenInput.value = '';
                    setStatus(root.dataset.verifyErrorText || 'Unable to verify appointment availability. Please try again.', 'error');
                });
            }, true);
        }

        var useDatePicker = mode === 'calendar' || (mode === 'auto' && calendarDays.length >= 4);
        if (!useDatePicker || !calendarDays.length || !datePicker || !dateInput || !times || typeof window.flatpickr !== 'function') {
            if (reservedDays.length) {
                setStatus(
                    root.dataset.reservedSummaryText || 'Some appointments are currently reserved in active booking processes and may become available again if those bookings are not completed.',
                    'warning'
                );
            }
            return;
        }

        var availableDays = Object.create(null);
        var reservedDayMap = Object.create(null);
        days.forEach(function (day) {
            availableDays[day] = true;
        });
        reservedDays.forEach(function (day) {
            reservedDayMap[day] = true;
        });

        function selectSlot(button, slotID) {
            select.value = String(slotID);
            syncSelectedOption();

            Array.prototype.forEach.call(times.querySelectorAll('.appointment-store-time'), function (item) {
                item.classList.remove('btn-primary');
                item.classList.add('btn-outline-primary');
                item.setAttribute('aria-pressed', 'false');
            });

            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-primary');
            button.setAttribute('aria-pressed', 'true');
            setStatus(displayInput.value || button.textContent.trim(), 'success');
        }

        function renderSlots(day) {
            times.innerHTML = '';
            setStatus('');

            if (!day) {
                return;
            }
            if (!availableDays[day] && !reservedDayMap[day]) {
                setStatus(root.dataset.emptyText || 'No appointments are available on this date.', 'error');
                return;
            }

            var loading = document.createElement('div');
            loading.className = 'text-muted small';
            loading.textContent = '…';
            times.appendChild(loading);

            var url = addParam(addParam(slotsUrl, 'productID', productID), 'date', day);
            requestJson(url).then(function (payload) {
                times.innerHTML = '';
                var slots = Array.isArray(payload.slots) ? payload.slots : [];
                if (!slots.length) {
                    if (payload.state === 'reserved') {
                        reservedDayMap[day] = true;
                        delete availableDays[day];
                        setStatus(
                            root.dataset.reservedText || 'This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.',
                            'warning'
                        );
                    } else if (payload.state === 'minimum_notice') {
                        setStatus(
                            root.dataset.minimumNoticeText || 'This appointment can no longer be booked because the minimum booking notice has passed.',
                            'error'
                        );
                    } else {
                        setStatus(root.dataset.emptyText || 'No appointments are available on this date.', 'error');
                    }
                    return;
                }

                availableDays[day] = true;
                delete reservedDayMap[day];

                var heading = document.createElement('div');
                heading.className = 'form-label mb-2';
                heading.textContent = root.dataset.timesText || 'Available times';
                times.appendChild(heading);

                var dateLabel = document.createElement('div');
                dateLabel.className = 'small text-muted mb-2';
                dateLabel.textContent = formatDay(day);
                times.appendChild(dateLabel);

                var list = document.createElement('div');
                list.className = 'd-flex flex-wrap gap-2';

                slots.forEach(function (slot) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-primary appointment-store-time';
                    button.setAttribute('aria-pressed', 'false');
                    button.textContent = slot.start + '–' + slot.end + (slot.label ? ' · ' + slot.label : '');
                    button.addEventListener('click', function () {
                        selectSlot(button, slot.id);
                    });
                    list.appendChild(button);
                });

                times.appendChild(list);
            }).catch(function () {
                times.innerHTML = '';
                setStatus(root.dataset.errorText || 'Unable to load appointments.', 'error');
            });
        }

        var dateLabelElement = datePicker.querySelector('label[for="' + dateInput.id + '"]');
        var flatpickrInstance = window.flatpickr(dateInput, {
            allowInput: false,
            altInput: true,
            altInputClass: 'form-control appointment-store-date-display',
            altFormat: 'D, d M Y',
            dateFormat: 'Y-m-d',
            disableMobile: true,
            enable: calendarDays,
            minDate: calendarDays[0],
            maxDate: calendarDays[calendarDays.length - 1],
            locale: buildFlatpickrLocale(),
            onReady: function (selectedDates, dateStr, instance) {
                if (instance.altInput) {
                    instance.altInput.id = dateInput.id + '-display';
                    instance.altInput.setAttribute('autocomplete', 'off');
                    if (dateLabelElement) {
                        dateLabelElement.setAttribute('for', instance.altInput.id);
                    }
                }
            },
            onDayCreate: function (dObj, dStr, instance, dayElement) {
                var day = localISO(dayElement.dateObj);
                if (availableDays[day]) {
                    dayElement.classList.add('appointment-store-available-day');
                } else if (reservedDayMap[day]) {
                    dayElement.classList.add('appointment-store-reserved-day');
                    dayElement.setAttribute('title', root.dataset.reservedTitle || 'Appointment currently being booked');
                }
            },
            onChange: function (selectedDates, dateStr, instance) {
                clearSelection();
                if (!selectedDates.length) {
                    times.innerHTML = '';
                    setStatus('');
                    return;
                }
                renderSlots(instance.formatDate(selectedDates[0], 'Y-m-d'));
            }
        });

        if (!flatpickrInstance) {
            return;
        }

        if (selectWrap) {
            selectWrap.classList.add('d-none');
        }
        datePicker.classList.remove('d-none');
    }

    function initAll() {
        Array.prototype.forEach.call(document.querySelectorAll('.appointment-store-picker'), initPicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
}());
