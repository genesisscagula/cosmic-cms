import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Replace Laravel's terse generic throttle message with a customer-friendly
// retry hint while preserving deliberate endpoint-specific 429 messages.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 429) {
            const currentMessage = String(error.response?.data?.message || '');
            if (!currentMessage || /too many attempts/i.test(currentMessage)) {
                error.response.data = {
                    ...(error.response.data || {}),
                    message: 'Please wait a moment before trying again. You can retry in 1–2 minutes.',
                };
            }
        }
        return Promise.reject(error);
    },
);
