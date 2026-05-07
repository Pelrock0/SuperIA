import api from './api';

export async function fetchLatestSummary() {
    const response = await api.get('/weekly-summary/latest');
    return response.data.data.summary;
}

export async function dismissSummary() {
    await api.post('/weekly-summary/dismiss');
}

export async function saveSummarySelection(summaryId, payload) {
    const response = await api.post(`/weekly-summary/${summaryId}/save`, payload);
    return response.data.data;
}

export async function fetchActiveLists() {
    const response = await api.get('/lists');
    const data = response.data.data;
    return Array.isArray(data?.active) ? data.active : [];
}

export async function updateWeeklySummaryEmail(enabled) {
    const response = await api.post('/settings/weekly-summary-email', { enabled });
    return response.data.data;
}
