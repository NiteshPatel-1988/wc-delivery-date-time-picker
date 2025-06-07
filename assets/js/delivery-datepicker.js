jQuery(function($){
    $('#delivery_date').datepicker({
        minDate: 1,
        dateFormat: 'yy-mm-dd',
        beforeShowDay: function(date) {
            var string = jQuery.datepicker.formatDate('yy-mm-dd', date);
            var holidays = delivery_blackout_dates;
            return [holidays.indexOf(string) === -1];
        }
    });
});
