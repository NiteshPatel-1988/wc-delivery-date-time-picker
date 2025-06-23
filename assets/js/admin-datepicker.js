jQuery(function($) {
    var input = $('#wc_delivery_blackout_dates');
    var selectedDates = [];

    input.datepicker({
        dateFormat: 'yy-mm-dd',
        beforeShowDay: function(date) {
            var string = $.datepicker.formatDate('yy-mm-dd', date);
            return [true, selectedDates.includes(string) ? 'ui-state-highlight' : ''];
        },
        onSelect: function(dateText) {
            if (!selectedDates.includes(dateText)) {
                selectedDates.push(dateText);
            } else {
                selectedDates = selectedDates.filter(function(d) {
                    return d !== dateText;
                });
            }
            input.val(selectedDates.join(','));
        }
    });

    // Load existing values (if any)
    if (input.val()) {
        selectedDates = input.val().split(',');
    }
});
