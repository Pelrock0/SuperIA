import api from './api';

export async function estimatePrices(listId) {
    const response = await api.post(`/lists/${listId}/estimate-prices`);
    return response.data.data;
}

export async function confirmPrices(listId, total, items = []) {
    const response = await api.post(`/lists/${listId}/confirm-prices`, { total, items });
    return response.data.data;
}
