import axios from 'axios';

const sharedApi = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export async function fetchSharedList(tokenParam) {
    const response = await sharedApi.get(`/shared/${tokenParam}`);
    return response.data.data;
}

export async function addSharedItem(tokenParam, data) {
    const response = await sharedApi.post(`/shared/${tokenParam}/items`, data);
    return response.data.data;
}

export async function updateSharedItem(tokenParam, itemId, data) {
    const response = await sharedApi.put(`/shared/${tokenParam}/items/${itemId}`, data);
    return response.data.data;
}

export async function toggleSharedItem(tokenParam, itemId) {
    const response = await sharedApi.patch(`/shared/${tokenParam}/items/${itemId}/toggle`);
    return response.data.data;
}

export async function deleteSharedItem(tokenParam, itemId) {
    const response = await sharedApi.delete(`/shared/${tokenParam}/items/${itemId}`);
    return response.data.data;
}

export async function sendHeartbeat(tokenParam, sessionUuid) {
    await sharedApi.post(`/shared/${tokenParam}/heartbeat`, { session_uuid: sessionUuid });
}

export async function fetchSaveStatus(tokenParam, jwtToken) {
    const response = await sharedApi.get(`/shared/${tokenParam}/save-status`, {
        headers: jwtToken ? { Authorization: `Bearer ${jwtToken}` } : {},
    });
    return response.data.data;
}

export async function saveToAccount(tokenParam, jwtToken) {
    const response = await sharedApi.post(`/shared/${tokenParam}/save`, {}, {
        headers: { Authorization: `Bearer ${jwtToken}` },
    });
    return response.data.data;
}

export default sharedApi;
