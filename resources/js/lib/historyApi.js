import api from './api';

export async function fetchHistory(page = 1) {
    const response = await api.get(`/history?page=${page}`);
    return response.data;
}

export async function duplicateList(listId) {
    const response = await api.post(`/lists/${listId}/duplicate`);
    return response.data.data;
}

export async function fetchStats() {
    const response = await api.get('/stats');
    return response.data.data;
}
