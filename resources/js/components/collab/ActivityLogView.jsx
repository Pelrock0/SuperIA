import React, { useEffect, useState } from 'react';
import { getActivityLog } from '../../lib/shareApi';

const POLL_INTERVAL_MS = 10000;

const ACTION_LABELS = {
    item_added: (name) => `anadio "${name}"`,
    item_checked: (name) => `marco "${name}" como comprado`,
    item_unchecked: (name) => `desmarco "${name}"`,
    item_edited: (name) => `edito "${name}"`,
    item_deleted: (name) => `elimino "${name}"`,
    list_cleared: () => 'limpio los items comprados',
};

const ACTOR_LABELS = {
    owner: 'Propietario',
    anonymous: 'Colaborador',
};

function formatRelative(timestamp) {
    if (!timestamp) return '';
    const diffMs = Date.now() - new Date(timestamp).getTime();
    const diffSec = Math.max(0, Math.floor(diffMs / 1000));
    if (diffSec < 60) return 'hace unos segundos';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `hace ${diffMin} min`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `hace ${diffHr} h`;
    const diffDay = Math.floor(diffHr / 24);
    return `hace ${diffDay} d`;
}

export default function ActivityLogView({ listId }) {
    const [entries, setEntries] = useState([]);
    const [isOpen, setIsOpen] = useState(false);
    const [isLoaded, setIsLoaded] = useState(false);

    useEffect(() => {
        if (!isOpen) return undefined;

        let cancelled = false;
        let timer = null;

        const fetchEntries = async () => {
            try {
                const data = await getActivityLog(listId);
                if (!cancelled) {
                    setEntries(data);
                    setIsLoaded(true);
                }
            } catch {
                // ignorar
            }
        };

        const schedule = () => {
            if (cancelled) return;
            timer = setTimeout(async () => {
                if (document.visibilityState === 'visible') {
                    await fetchEntries();
                }
                schedule();
            }, POLL_INTERVAL_MS);
        };

        fetchEntries();
        schedule();

        return () => {
            cancelled = true;
            if (timer) clearTimeout(timer);
        };
    }, [isOpen, listId]);

    return (
        <div className="bg-white border border-gray-200 rounded-lg" data-testid="activity-log">
            <button
                type="button"
                onClick={() => setIsOpen((prev) => !prev)}
                className="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                aria-expanded={isOpen}
                aria-controls="activity-log-panel"
            >
                <span>Actividad reciente</span>
                <span aria-hidden="true">{isOpen ? '▲' : '▼'}</span>
            </button>

            {isOpen && (
                <div id="activity-log-panel" className="px-4 py-3 border-t border-gray-100">
                    {!isLoaded ? (
                        <p className="text-xs text-gray-500" data-testid="activity-loading">Cargando actividad...</p>
                    ) : entries.length === 0 ? (
                        <p className="text-xs text-gray-500" data-testid="activity-empty">
                            Aun no hay actividad en esta lista.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {entries.map((entry) => (
                                <li key={entry.id} className="text-xs text-gray-600 flex items-start gap-2">
                                    <span className="font-medium text-gray-700">
                                        {ACTOR_LABELS[entry.actor_type] || entry.actor_type}
                                    </span>
                                    <span className="flex-1">
                                        {(ACTION_LABELS[entry.action] || (() => entry.action))(entry.item_name)}
                                    </span>
                                    <span className="text-gray-400 whitespace-nowrap">
                                        {formatRelative(entry.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
