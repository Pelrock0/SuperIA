import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { dismissSummary, fetchLatestSummary } from '../../lib/weeklySummaryApi';

export default function WeeklySummaryBanner() {
    const [summary, setSummary] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [dismissing, setDismissing] = useState(false);
    const navigate = useNavigate();

    const load = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await fetchLatestSummary();
            setSummary(data);
        } catch {
            setSummary(null);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const handleDismiss = async () => {
        setDismissing(true);
        try {
            await dismissSummary();
            setSummary(null);
        } catch {
            // silent — worst case the banner stays
        } finally {
            setDismissing(false);
        }
    };

    if (isLoading || !summary) {
        return null;
    }

    const productCount = summary.products?.length ?? 0;

    return (
        <section
            className="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6"
            data-testid="weekly-summary-banner"
            aria-labelledby="weekly-summary-title"
        >
            <div className="flex items-start justify-between">
                <div className="flex-1 min-w-0">
                    <h2 id="weekly-summary-title" className="text-sm font-semibold text-indigo-900 mb-1">
                        Resumen semanal
                    </h2>
                    <p className="text-xs text-indigo-700">
                        {productCount} {productCount === 1 ? 'producto sugerido' : 'productos sugeridos'} para esta semana.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleDismiss}
                    disabled={dismissing}
                    className="text-indigo-400 hover:text-indigo-600 ml-2 flex-shrink-0"
                    aria-label="Descartar resumen semanal"
                    data-testid="dismiss-banner"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                </button>
            </div>

            <button
                type="button"
                onClick={() => navigate('/app/resumen')}
                className="mt-3 text-xs bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                data-testid="view-summary"
            >
                Ver resumen
            </button>
        </section>
    );
}
