$(document).ready(async function () {
    lastVerificationEmailAt = $(this).attr('data-last-verification-email-at');
    let lastDate = new Date(lastVerificationEmailAt);
    let allowedTime = new Date(lastDate.getTime() + 2 * 60 * 1000);
    let now = new Date();

    if (now >= allowedTime) {
        try {
            const request = await axios.post(
                Routing.generate('send_email')
            );
        } catch (error) {
            console.log(error);
        }
    }

    $('body').on('click', '#resend-verification-email', async function (e) {
        e.preventDefault();
        try {
            countVerificationEmails = $(this).attr('data-count-verification-emails');
            if (countVerificationEmails.length >=3) {
                window.notyf.error('You have reached the maximum number of verification email requests. Please try later.');
                return;
            }
            const request = await axios.post(
                Routing.generate('send_email')
            )
                ;
            const response = await request.data;
            window.notyf.dismissAll();
        } catch (error) {
            window.notyf.dismissAll();
            console.log(error);
        if (error.response && error.response.data) {
            const message = error.response.data;
            window.notyf.error(message);
            } else {
                window.notyf.error('Something went wrong!');
            }
        }
    })
});