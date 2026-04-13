import React, { useEffect, useState } from 'react';

export default function UndoSnackbar({ message, onUndo, duration = 5000 }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), duration);
        return () => clearTimeout(timer);
    }, [duration]);

    if (!visible) return null;

    return (
        <div
            className="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50"
            role="status"
            data-testid="undo-snackbar"
        >
            <span className="text-sm">{message}</span>
            <button
                onClick={onUndo}
                className="text-indigo-300 font-medium text-sm hover:text-indigo-200"
            >
                Deshacer
            </button>
        </div>
    );
}
