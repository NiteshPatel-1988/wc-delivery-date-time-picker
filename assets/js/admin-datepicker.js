jQuery(function($) {
    var storageInput = $('#delidaam_delivery_blackout_dates');
    if (!storageInput.length) {
        return;
    }

    var selectedDates = [];

    // The WooCommerce-rendered input only holds the saved CSV value now;
    // it stays hidden so the raw comma list never shows on screen. A
    // separate blank, read-only trigger takes over its id/label so the
    // calendar opens from the same spot, and the chip list below is the
    // only place the selected dates are actually displayed.
    storageInput
        .attr('id', 'delidaam_delivery_blackout_dates_storage')
        .hide();

    var trigger = $('<input type="text" id="delidaam_delivery_blackout_dates" class="delidaam-blackout-dates-trigger" readonly />')
        .attr('placeholder', delidaamAdminDatepicker.placeholder);
    storageInput.after(trigger);

    var chipsList = $('<div class="delidaam-blackout-dates-chips"></div>');
    trigger.after(chipsList);

    function renderChips() {
        chipsList.empty();

        selectedDates.forEach(function(dateText) {
            var removeButton = $('<button type="button" class="delidaam-blackout-date-remove"></button>')
                .attr('aria-label', delidaamAdminDatepicker.removeLabel.replace('%s', dateText))
                .text('×')
                .on('click', function() {
                    removeDate(dateText);
                });

            var chip = $('<span class="delidaam-blackout-date-chip"></span>')
                .append($('<span class="delidaam-blackout-date-chip-text"></span>').text(dateText))
                .append(removeButton);

            chipsList.append(chip);
        });
    }

    function removeDate(dateText) {
        selectedDates = selectedDates.filter(function(d) {
            return d !== dateText;
        });
        storageInput.val(selectedDates.join(','));
        renderChips();
        trigger.datepicker('refresh');
    }

    trigger.datepicker({
        dateFormat: 'yy-mm-dd',
        beforeShowDay: function(date) {
            var string = $.datepicker.formatDate('yy-mm-dd', date);
            return [true, selectedDates.includes(string) ? 'ui-state-highlight' : ''];
        },
        onSelect: function(dateText) {
            if (!selectedDates.includes(dateText)) {
                selectedDates.push(dateText);
                selectedDates.sort();
            } else {
                selectedDates = selectedDates.filter(function(d) {
                    return d !== dateText;
                });
            }
            storageInput.val(selectedDates.join(','));
            renderChips();
        }
    });

    // Load existing values (if any)
    if (storageInput.val()) {
        selectedDates = storageInput.val().split(',')
            .map(function(d) { return d.trim(); })
            .filter(Boolean);
    }
    renderChips();
});
