import api from './api';

export async function fetchReplenishmentSuggestions() {
    const response = await api.get('/dashboard/replenishment');
    return response.data.data.suggestions || [];
}

export async function acceptReplenishment(productoNombre, listId) {
    const response = await api.post('/replenishment/accept', {
        producto_nombre: productoNombre,
        list_id: listId,
    });
    return response.data.data;
}

export async function ignoreReplenishment(productoNombre) {
    await api.post('/replenishment/ignore', { producto_nombre: productoNombre });
}

export async function silenceReplenishment(productoNombre) {
    await api.post('/replenishment/silence', { producto_nombre: productoNombre });
}
