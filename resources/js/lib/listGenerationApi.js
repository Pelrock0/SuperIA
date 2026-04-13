import api from './api';

export async function generateList(description, people) {
    const response = await api.post('/generate-list', { description, people });
    return response.data.data;
}

export async function confirmNewList(items, name) {
    const response = await api.post('/generate-list/confirm-new', { items, name });
    return response.data.data;
}

export async function confirmExistingList(items, listId) {
    const response = await api.post('/generate-list/confirm-existing', { items, list_id: listId });
    return response.data.data;
}
