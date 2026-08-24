$(document).ready(function(){
    $('#form-supercheckout').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        var flag = true;
        var result;
        
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
    $('#form-login').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        var flag = true;
        var result;
        
        if($('#facebook_login_display').is(":checked") == true){
            result = velovalidation.checkMandatory($('#facebook_login_app_id'));
            if (result != true) {
                flag = false;
                $('#facebook_login_app_id').after("<div class='text-danger'>" + result + "</div>");
                $('#facebook_login_app_id').parent().parent().addClass("has-error");
            }
            result = velovalidation.checkMandatory($('#facebook_login_app_secret'));
            if (result != true) {
                flag = false;
                $('#facebook_login_app_secret').after("<div class='text-danger'>" + result + "</div>");
                $('#facebook_login_app_secret').parent().parent().addClass("has-error");
            }
        }
        if($('#google_login_display').is(":checked") == true){
            result = velovalidation.checkMandatory($('#google_login_app_id'));
            if (result != true) {
                flag = false;
                $('#google_login_app_id').after("<div class='text-danger'>" + result + "</div>");
                $('#google_login_app_id').parent().parent().addClass("has-error");
            }
            result = velovalidation.checkMandatory($('#google_login_client_id'));
            if (result != true) {
                flag = false;
                $('#google_login_client_id').after("<div class='text-danger'>" + result + "</div>");
                $('#google_login_client_id').parent().parent().addClass("has-error");
            }
            result = velovalidation.checkMandatory($('#google_login_app_secret'));
            if (result != true) {
                flag = false;
                $('#google_login_app_secret').after("<div class='text-danger'>" + result + "</div>");
                $('#google_login_app_secret').parent().parent().addClass("has-error");
            }
        }
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
    $('#form-shipping-method').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        $('.form-group').removeClass("kb-tab-error");
        var flag = true;
        var result;
        
        $('.shipping-title').each(function(){
            result = velovalidation.checkMandatory($(this));
            if (result != true) {
                flag = false;
                $(this).parent().parent().parent().addClass("has_error");
                $(this).after('<div class="text-danger">'+ result +'</div>');
                $('.err').addClass("kb-tab-error");
            }
        });
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
    $('#form-payment-method').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        $('.form-group').removeClass("kb-tab-error");
        var flag = true;
        var result;
        
        $('.payment-title').each(function(){
            result = velovalidation.checkMandatory($(this));
            if (result != true) {
                flag = false;
                $(this).parent().parent().parent().addClass("has_error");
                $(this).after('<div class="text-danger">'+ result +'</div>');
            }
        });
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
    $('#form-confirm').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        $('.form-group').removeClass("kb-tab-error");
        var flag = true;
        var result;
        
        result = velovalidation.checkMandatory($('#image_width_id'));
        if (result != true) {
            flag = false;
            $('#image_width_id').after("<div class='text-danger'>" + result + "</div>");
            $('#image_width_id').parent().parent().addClass("has-error");
        }else{
            result = velovalidation.isPositiveNumber($('#image_width_id'));
            if (result != true) {
                flag = false;
                $('#image_width_id').after("<div class='text-danger'>" + result + "</div>");
                $('#image_width_id').parent().parent().addClass("has-error");
            }
        }
        result = velovalidation.checkMandatory($('#image_height_id'));
        if (result != true) {
            flag = false;
            $('#image_height_id').after("<div class='text-danger'>" + result + "</div>");
            $('#image_height_id').parent().parent().addClass("has-error");
        }else{
            result = velovalidation.isPositiveNumber($('#image_height_id'));
            if (result != true) {
                flag = false;
                $('#image_height_id').after("<div class='text-danger'>" + result + "</div>");
                $('#image_height_id').parent().parent().addClass("has-error");
            }
        }
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
    $('#form-newsletter').submit(function(e){
        e.preventDefault();
        $('.text-danger').remove();
        $('.form-group').removeClass("has-error");
        $('.form-group').removeClass("kb-tab-error");
        var flag = true;
        var result;
        
        result = velovalidation.checkMandatory($('#mailchimp_api'));
        result1 = velovalidation.checkMandatory($('#klaviyo_api'));
        result2 = velovalidation.checkMandatory($('#sendinblue_api'));
        
        if ($("#mailchimp_enable").is(":checked")) {
 
            if (result != true) {
                flag = false;
                $('#mailchimp_list_button').after("<div class='text-danger'>" + result + "</div>");
                $('#mailchimp_list_button').parent().parent().addClass("has-error");
            }else{
                var regex = /-/;
                if(!regex.test($('#mailchimp_api').val())){
                    flag = false;
                    $('#mailchimp_list_button').after("<div class='text-danger'>" + error_invalid_key + "</div>");
                    $('#mailchimp_list_button').parent().parent().addClass("has-error");
                }
            }
        
        }
        
        
        if ($("#klaviyo_enable").is(":checked")) {
            if (result1 != true) {
                flag = false;
                $('#klaviyo_list_button').after("<div class='text-danger'>" + result1 + "</div>");
                $('#klaviyo_list_button').parent().parent().addClass("has-error");
            }
        }
        
        if ($("#sendinblue_enable").is(":checked")) {
            if (result2 != true) {
                flag = false;
                $('#sendinblue_list_button').after("<div class='text-danger'>" + result2 + "</div>");
                $('#sendinblue_list_button').parent().parent().addClass("has-error");
            }
        }
        
        if (flag == true) {
            $(this).unbind('submit').submit();
        }
        return flag;
    });
     
});