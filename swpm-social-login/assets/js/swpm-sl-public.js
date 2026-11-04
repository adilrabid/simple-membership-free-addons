/* global swpm_sl_data */

document.addEventListener('DOMContentLoaded', function () {
    const socialLoginButtons = document.querySelectorAll('.swpm_sl_login_btn');
    socialLoginButtons.forEach(function (socialBtn) {
        socialBtn.addEventListener('click', handleSocialBtnClick);
    })
})

function handleSocialBtnClick(e) {
    e.preventDefault();

    const socialBtn = this;
    socialBtn.setAttribute('disabled', true);

    const loginWidget = socialBtn.closest('.swpm-login-widget-form');

    const rememberMeCheckbox = loginWidget?.querySelector('#swpm-rememberme');

    let isRememberMeChecked = false;
    if (rememberMeCheckbox) {
        isRememberMeChecked = rememberMeCheckbox.checked;
    }
    
    const provider = socialBtn.dataset.provider;

    const payload = {
        action: 'swpm_sl_get_auth_url',
        swpm_sl_nonce: swpm_sl_data.nonce,
        remember_me: isRememberMeChecked ? 1 : 0,
        provider: provider,
        referer_url: window.location.href,
    }

    // console.log(payload);

    fetch(swpm_sl_data.ajaxUrl, {
        method: 'post',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(payload),
    })
        .then(res => {
            if (!res.ok) {
                throw new Error("Request failed");
            }

            return res.json()
        })
        .then(res => {
            if (!res.success) {
                throw new Error(res.data?.message || 'Something went wrong!');
            }
            
            // Redirect to social authentication url.
            window.location.href = res.data.auth_url;
        }).catch((err) => {
            alert(err.message);
            socialBtn.removeAttribute('disabled');
        })
}