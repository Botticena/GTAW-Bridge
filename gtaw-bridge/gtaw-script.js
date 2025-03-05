jQuery(document).ready(function($){
    // Helper: get cookie value.
    function getCookie(name) {
        var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]) : null;
    }
    
    // Do not show modal if already logged in.
    if($('body').hasClass('logged-in')) return;
    
    var gtawCookie = getCookie('gtaw_user_data');
    if (!gtawCookie) return;
    var userData;
    try {
        userData = JSON.parse(atob(gtawCookie));
    } catch(e) {
        console.log("Error parsing GTAW cookie.");
        return;
    }
    
    // Create modal container if needed.
    if ($('#gtaw-modal').length === 0) {
        $('body').append('<div id="gtaw-modal" class="gtaw-modal-overlay"><div class="gtaw-modal-content"></div></div>');
    }
    var $modal = $('#gtaw-modal .gtaw-modal-content');
    
    // Check for existing WP accounts.
    $.post(gtaw_ajax.ajax_url, { action: 'gtaw_check_account', nonce: gtaw_ajax.nonce }, function(response){
        if(response.success) {
            // If accounts exist, show returning modal.
            if(response.data.exists){
                showReturningModal($modal, userData, response.data.accounts);
            } else {
                // No account exists → show first login modal.
                showFirstLoginModal($modal, userData);
            }
        } else {
            showFirstLoginModal($modal, userData);
        }
    });
    
    // Modal for first login: account creation.
    function showFirstLoginModal($modal, data) {
        var chars = data.user.character;
        var html = '<h2>Select a Character to Create Your Account</h2>';
        if(chars && chars.length > 0) {
            html += '<select id="gtaw-character-select">';
            $.each(chars, function(i, char){
                html += '<option value="'+i+'" data-id="'+char.id+'" data-firstname="'+char.firstname+'" data-lastname="'+char.lastname+'">'+char.firstname+' '+char.lastname+'</option>';
            });
            html += '</select>';
        } else {
            html += '<p>No characters available.</p>';
        }
        html += '<button id="gtaw-create-account">Create Account & Login</button>';
        $modal.html(html);
        $('#gtaw-modal').show();
        
        $('#gtaw-create-account').on('click', function(){
            var $option = $('#gtaw-character-select option:selected');
            var charData = {
                id: $option.data('id'),
                firstname: $option.data('firstname'),
                lastname: $option.data('lastname')
            };
            $.post(gtaw_ajax.ajax_url, {
                action: 'gtaw_create_account',
                nonce: gtaw_ajax.nonce,
                character: charData
            }, function(response){
                if(response.success) {
                    alert(response.data);
                    $('#gtaw-modal').hide();
                    location.reload();
                } else {
                    alert("Error: " + response.data);
                }
            });
        });
    }
    
    // Modal for returning users: shows two sections.
    function showReturningModal($modal, data, accounts) {
        var allChars = data.user.character;
        var connectedIds = [];
        // Collect all connected character IDs (ensure they are strings)
        $.each(accounts, function(i, acc){
            if(acc.active && acc.active.id){
                connectedIds.push(String(acc.active.id));
            }
        });
        var newChars = [];
        $.each(allChars, function(i, char){
            // Convert GTAW character id to string for comparison
            if ( connectedIds.indexOf(String(char.id)) === -1 ){
                newChars.push(char);
            }
        });

        var html = '<h2>Account Options</h2>';
        // Section: Login with existing account.
        html += '<h3>Login with an Existing Account</h3>';
        if(accounts.length > 0) {
            html += '<select id="gtaw-login-select">';
            $.each(accounts, function(i, acc){
                if(acc.active && acc.active.id){
                    html += '<option value="'+i+'" data-id="'+acc.active.id+'" data-firstname="'+acc.active.firstname+'" data-lastname="'+acc.active.lastname+'">'+acc.active.firstname+' '+acc.active.lastname+'</option>';
                }
            });
            html += '</select>';
            html += '<button id="gtaw-login-account">Login</button>';
        } else {
            html += '<p>No connected accounts found.</p>';
        }
        // Section: Register new account.
        html += '<hr /><h3>Register a New Character</h3>';
        if(newChars.length > 0) {
            html += '<select id="gtaw-new-select">';
            $.each(newChars, function(i, char){
                html += '<option value="'+i+'" data-id="'+char.id+'" data-firstname="'+char.firstname+'" data-lastname="'+char.lastname+'">'+char.firstname+' '+char.lastname+'</option>';
            });
            html += '</select>';
            html += '<button id="gtaw-register-new">Register New Account</button>';
        } else {
            html += '<p>No new characters available.</p>';
        }
        $modal.html(html);
        $('#gtaw-modal').show();

        // Login existing account.
        $('#gtaw-login-account').on('click', function(){
            var $option = $('#gtaw-login-select option:selected');
            var charData = {
                id: $option.data('id'),
                firstname: $option.data('firstname'),
                lastname: $option.data('lastname')
            };
            $.post(gtaw_ajax.ajax_url, {
                action: 'gtaw_login_account',
                nonce: gtaw_ajax.nonce,
                character: charData
            }, function(response){
                if(response.success) {
                    alert(response.data);
                    $('#gtaw-modal').hide();
                    location.reload();
                } else {
                    alert("Error: " + response.data);
                }
            });
        });

        // Register new account.
        $('#gtaw-register-new').on('click', function(){
            var $option = $('#gtaw-new-select option:selected');
            var charData = {
                id: $option.data('id'),
                firstname: $option.data('firstname'),
                lastname: $option.data('lastname')
            };
            $.post(gtaw_ajax.ajax_url, {
                action: 'gtaw_create_account',
                nonce: gtaw_ajax.nonce,
                character: charData
            }, function(response){
                if(response.success) {
                    alert(response.data);
                    $('#gtaw-modal').hide();
                    location.reload();
                } else {
                    alert("Error: " + response.data);
                }
            });
        });
    }
});
