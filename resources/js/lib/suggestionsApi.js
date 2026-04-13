import api from './api';

export async function fetchSuggestions(query, { includeAi = false } = {}) {
    const params = { q: query };
    if (includeAi) params.include_ai = 1;

    const response = await api.get('/suggestions', { params });
    return response.data.data;
}
