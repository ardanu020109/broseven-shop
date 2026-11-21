import axios from 'axios';

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let unauthorizedHandler = null;

export function setUnauthorizedHandler(handler) {
    unauthorizedHandler = handler;
}

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { response, config } = error;

        if (!response || !config) {
            return Promise.reject(error);
        }

        if (response.status === 419 && !config._csrfRetried) {
            config._csrfRetried = true;
            await http.get('/auth/csrf');
            return http(config);
        }

        if (response.status === 401 && !config.skipAuthRedirect && unauthorizedHandler) {
            unauthorizedHandler();
        }

        return Promise.reject(error);
    },
);

export default http;
