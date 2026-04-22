// Tabs highlight only; log buttons live inline next to the table.
jQuery(document).ready(function($) {
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