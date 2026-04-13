import api from './api';

export async function fetchHistory(page = 1) {
    const response = await api.get('/profile/history', { params: { page } });
    return response.data.data;
}

export async function clearHistory() {
    const response = await api.delete('/profile/history');
    return response.data.data;
}

export async function forgetProduct(productName) {
    const response = await api.delete(`/profile/history/${encodeURIComponent(productName)}`);
    return response.data.data;
}
