import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '1m', target: 20 }, // Ramp up to 20 users
        { duration: '3m', target: 20 }, // Stay at 20 users
        { duration: '1m', target: 50 }, // Ramp up to 50 users
        { duration: '3m', target: 50 }, // Stay at 50 users
        { duration: '1m', target: 0 },  // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<2000'], // 95% of requests should be below 2s
        http_req_failed: ['rate<0.01'],    // Less than 1% failure rate
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const CHAT_STREAM_URL = `${BASE_URL}/api/chat/stream`;
const AUTH_TOKEN = __ENV.AUTH_TOKEN;

export default function () {
    const payload = JSON.stringify({
        texto: 'Olá TUT\'S, explica-me brevemente o que são Grafos.',
        uc: 'Redes de Computadores',
        context_type: 'uc'
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${AUTH_TOKEN}`,
            'Accept': 'text/event-stream',
        },
        timeout: '120s',
    };

    const res = http.post(CHAT_STREAM_URL, payload, params);

    check(res, {
        'status is 200': (r) => r.status === 200,
        'content-type is event-stream': (r) => r.headers['Content-Type'] === 'text/event-stream',
        'is not a technical error': (r) => !r.body.includes('❌'),
    });

    // Simulate reading the stream for a while
    sleep(Math.random() * 5 + 5);
}
