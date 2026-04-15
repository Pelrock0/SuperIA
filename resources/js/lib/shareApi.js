import api from './api';

export async function listShareTokens(listId) {
    const response = await api.get(`/lists/${listId}/share`);
    return response.data.data.tokens;
}

export async function createShareToken(listId, mode) {
    const response = await api.post(`/lists/${listId}/share`, { mode });
    return response.data.data.token;
}

export async function revokeShareToken(listId, tokenId) {
    await api.delete(`/lists/${listId}/share/${tokenId}`);
}

export async function getCollaboratorsCount(listId) {
    const response = await api.get(`/lists/${listId}/collaborators/count`);
    return response.data.data.count;
}

export async function getActivityLog(listId) {
    const response = await api.get(`/lists/${listId}/activity`);
    return response.data.data.entries;
}

export async function getCollaborators(listId) {
    const response = await api.get(`/lists/${listId}/collaborators`);
    return response.data.data;
}
