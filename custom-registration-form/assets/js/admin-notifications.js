jQuery(document).ready(function($) {
    function updateSubmissionCounter() {
        $.ajax({
            url: crf_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'crf_get_new_submission_count',
                nonce: crf_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    var count = parseInt(response.data, 10);
                    var menu_item = $('#toplevel_page_custom-registrations .wp-menu-name');
                    var counter = menu_item.find('.awaiting-mod');

                    if (count > 0) {
                        if (counter.length) {
                            counter.find('.pending-count').text(count);
                        } else {
                            menu_item.append(' <span class="awaiting-mod"><span class="pending-count">' + count + '</span></span>');
                        }
                    } else {
                        counter.remove();
                    }
                }
            }
        });
    }

    // Check for new submissions every 30 seconds
    setInterval(updateSubmissionCounter, 30000);
});
