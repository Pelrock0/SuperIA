import React, { useEffect, useState } from 'react';
import { getCollaboratorsCount } from '../../lib/shareApi';

const POLL_INTERVAL_MS = 10000;

export default function CollaboratorIndicator({ listId }) {
    const [count, setCount] = useState(0);

    useEffect(() => {
        let cancelled = false;
        let timer = null;

        const fetchCount = async () => {
            try {
                const value = await getCollaboratorsCount(listId);
                if (!cancelled) setCount(value);
            } catch {
                // ignorar fallos transitorios
            }
        };

        const schedule = () => {
            if (cancelled) return;
            timer = setTimeout(async () => {
                if (document.visibilityState === 'visible') {
                    await fetchCount();
                }
                schedule();
            }, POLL_INTERVAL_MS);
        };

        fetchCount();
        schedule();

        return () => {
            cancelled = true;
            if (timer) clearTimeout(timer);
        };
    }, [listId]);

    if (count === 0) {
        return null;
    }

    return (
        <div
            className="inline-flex items-center gap-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-1 rounded-full"
            data-testid="collaborator-indicator"
            role="status"
            aria-live="polite"
        >
            <span className="h-2 w-2 rounded-full bg-indigo-500" aria-hidden="true" />
            {count === 1 ? '1 persona viendo ahora' : `${count} personas viendo ahora`}
        </div>
    );
}
