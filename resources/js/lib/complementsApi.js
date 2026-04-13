import api from './api';

export async function fetchComplements(product, listId) {
    const response = await api.get('/suggestions/complements', {
        params: { product, list_id: listId },
    });
    return response.data.data;
}
