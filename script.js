document.addEventListener('DOMContentLoaded', function () {
    const popup = document.querySelector('.stark-message-popup');
    const closeButton = document.getElementById('stark-message-close');
    const cookieName = 'stark_message_closed_' + starkMessage.cookie_suffix;

    if (popup && closeButton) {
        closeButton.addEventListener('click', function () {
            document.cookie = `${cookieName}=1; max-age=${starkMessage.cookie_lifetime}; path=/`;
            popup.style.display = 'none';
        });
    }
});
