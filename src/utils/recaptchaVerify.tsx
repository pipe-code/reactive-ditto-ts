import instance from './axiosInstance';

const RECAPTCHA_SCORE_THRESHOLD = 0.5;

/**
 * Loads the reCAPTCHA v3 script if it hasn't been added yet.
 */
function loadRecaptchaScript(siteKey: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.getElementById('recaptcha-script')) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.id = 'recaptcha-script';
        script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load reCAPTCHA script'));
        document.head.appendChild(script);
    });
}

/**
 * Executes reCAPTCHA v3 and verifies the resulting token server-side against
 * POST /wp-json/recaptcha/v1/verify (see inc/recaptcha.php).
 * Returns true only if the score passes the threshold.
 */
const recaptchaVerify = async (action: string = 'submit'): Promise<boolean> => {
    const siteKey = (window as any)._recaptchaSiteKey_;

    if (!siteKey) {
        console.warn('reCAPTCHA site key is not configured.');
        return false;
    }

    try {
        await loadRecaptchaScript(siteKey);

        const token: string = await new Promise((resolve, reject) => {
            (window as any).grecaptcha.ready(() => {
                (window as any).grecaptcha
                    .execute(siteKey, { action })
                    .then(resolve)
                    .catch(reject);
            });
        });

        const response = await instance.post('recaptcha/v1/verify', { token });
        const data = response.data;

        return data.success === true && (data.score ?? 0) >= RECAPTCHA_SCORE_THRESHOLD;
    } catch (error) {
        console.error('reCAPTCHA verification failed:', error);
        return false;
    }
};

export default recaptchaVerify;
