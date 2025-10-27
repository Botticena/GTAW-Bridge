/**
 * GTAW Bridge Admin JavaScript
 * Handles common admin interface functionality
 */
jQuery(document).ready(function($) {
    
    /**
     * Log clearing functionality
     */
    $('.gtaw-logs-table-container').each(function() {
        const $container = $(this);
        const $clearButton = $container.find('#clear-logs');
        const $status = $container.find('#logs-status');
        
        $clearButton.on('click', function() {
            const module = $(this).data('module');
            
            if (!module) {
                console.error('No module specified for log clearing');
                return;
            }
            
            if (confirm("Are you sure you want to clear all logs?")) {
                $(this).prop('disabled', true);
                $status.html('<span style="color: blue;">Clearing logs...</span>');
                
                $.post(ajaxurl, {
                    action: "gtaw_clear_logs",
                    module: module
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">Logs cleared successfully.</span>');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                        $clearButton.prop('disabled', false);
                    }
                }).fail(function() {
                    $status.html('<span style="color: red;">Request failed. Please try again.</span>');
                    $clearButton.prop('disabled', false);
                });
            }
        });
    });
    
    /**
     * Tab navigation active state
     */
    $('.nav-tab-wrapper .nav-tab').each(function() {
        const $tab = $(this);
        const currentUrl = window.location.href;
        const tabUrl = $tab.attr('href');
        
        if (currentUrl.indexOf(tabUrl) !== -1) {
            $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
        }
    });
});