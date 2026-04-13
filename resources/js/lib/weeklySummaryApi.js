import api from './api';

export async function fetchLatestSummary() {
    const response = await api.get('/weekly-summary/latest');
    return response.data.data.summary;
}

export async function dismissSummary() {
    await api.post('/weekly-summary/dismiss');
}

export async function convertSummaryToList(summaryId) {
    const response = await api.post(`/weekly-summary/${summaryId}/convert-to-list`);
    return response.data.data;
}

export async function updateWeeklySummaryEmail(enabled) {
    const response = await api.post('/settings/weekly-summary-email', { enabled });
    return response.data.data;
}
