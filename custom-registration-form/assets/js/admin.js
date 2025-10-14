jQuery(document).ready(function($) {
    // Listen for changes on the status dropdowns
    $('.status-changer').on('change', function() {
        var selectElement = $(this);
        var submissionId = selectElement.data('id');
        var newStatus = selectElement.val();
        var spinner = selectElement.next('.spinner');

        // Show the spinner for visual feedback
        spinner.addClass('is-active');
        
        // Prepare the data to be sent
        var data = {
            action: 'update_submission_status', // The WordPress AJAX action hook
            _ajax_nonce: crf_ajax_object.nonce, // The security nonce
            submission_id: submissionId,
            new_status: newStatus
        };

        // Send the AJAX request
        $.post(crf_ajax_object.ajax_url, data, function(response) {
            // Hide the spinner
            spinner.removeClass('is-active');
            
            // Handle the response
            if (response.success) {
                // Optional: Add a visual success indicator, e.g., flash the row green
                selectElement.closest('tr').css('background-color', '#d4edda').animate({
                    backgroundColor: ''
                }, 1500);
            } else {
                // On failure, show an alert and revert the dropdown
                alert('Error: ' + response.data.message);
                // You might need to reload the page or revert the select value here
                location.reload(); 
            }
        });
    });
});