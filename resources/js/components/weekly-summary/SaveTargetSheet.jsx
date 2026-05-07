import React, { useEffect, useMemo, useRef, useState } from 'react';

const FREEMIUM_LIMIT = 3;
const DESKTOP_BREAKPOINT = 768;
const FOCUSABLE_SELECTOR =
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function useIsDesktop() {
    const [isDesktop, setIsDesktop] = useState(() =>
        typeof window !== 'undefined' && typeof window.matchMedia === 'function'
            ? window.matchMedia(`(min-width: ${DESKTOP_BREAKPOINT}px)`).matches
            : false,
    );

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return undefined;
        }
        const mql = window.matchMedia(`(min-width: ${DESKTOP_BREAKPOINT}px)`);
        const handler = (event) => setIsDesktop(event.matches);
        if (typeof mql.addEventListener === 'function') {
            mql.addEventListener('change', handler);
            return () => mql.removeEventListener('change', handler);
        }
        mql.addListener(handler);
        return () => mql.removeListener(handler);
    }, []);

    return isDesktop;
}

export default function SaveTargetSheet({
    isOpen,
    onClose,
    onConfirm,
    activeLists,
    selectedCount,
    isSubmitting = false,
}) {
    const [chosenListId, setChosenListId] = useState(null);
    const [createNew, setCreateNew] = useState(false);
    const dialogRef = useRef(null);
    const previouslyFocusedRef = useRef(null);
    const isDesktop = useIsDesktop();

    const freemiumExceeded = (activeLists?.length ?? 0) >= FREEMIUM_LIMIT;
    const canConfirm = !isSubmitting && (chosenListId !== null || createNew);

    const chosenList = useMemo(
        () => (activeLists ?? []).find((list) => list.id === chosenListId) ?? null,
        [activeLists, chosenListId],
    );

    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }
        previouslyFocusedRef.current = document.activeElement;
        setChosenListId(null);
        setCreateNew(false);
        const node = dialogRef.current;
        if (node) {
            node.focus();
        }
        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                onClose();
                return;
            }
            if (event.key !== 'Tab') {
                return;
            }
            const root = dialogRef.current;
            if (!root) {
                return;
            }
            const focusable = Array.from(root.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
                (el) => !el.hasAttribute('disabled') && el.tabIndex !== -1,
            );
            if (focusable.length === 0) {
                event.preventDefault();
                root.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;
            if (event.shiftKey) {
                if (active === first || !root.contains(active)) {
                    event.preventDefault();
                    last.focus();
                }
            } else if (active === last || !root.contains(active)) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = previousOverflow;
            if (previouslyFocusedRef.current && typeof previouslyFocusedRef.current.focus === 'function') {
                previouslyFocusedRef.current.focus();
            }
        };
    }, [isOpen, onClose]);

    if (!isOpen) {
        return null;
    }

    const chooseExisting = (listId) => {
        setChosenListId(listId);
        setCreateNew(false);
    };

    const chooseNew = () => {
        if (freemiumExceeded) {
            return;
        }
        setChosenListId(null);
        setCreateNew(true);
    };

    const handleConfirm = () => {
        if (!canConfirm) {
            return;
        }
        if (createNew) {
            onConfirm({ targetListId: null });
            return;
        }
        onConfirm({ targetListId: chosenListId });
    };

    const handleBackdropClick = (event) => {
        if (event.target === event.currentTarget) {
            onClose();
        }
    };

    const confirmLabel = (() => {
        if (isSubmitting) return 'Guardando…';
        if (!canConfirm) return 'Selecciona una lista';
        if (createNew) return 'Guardar en nueva lista';
        if (chosenList) return `Guardar en "${chosenList.name}"`;
        return 'Guardar';
    })();

    const focusRing = '0 0 0 3px rgba(0, 62, 84, 0.35)';
    const sheetStyle = isDesktop
        ? {
              width: '100%',
              maxWidth: '480px',
              background: '#ffffff',
              borderRadius: '24px',
              padding: '24px',
              boxShadow: '0 24px 64px rgba(0,0,0,0.25)',
              maxHeight: '85vh',
              overflowY: 'auto',
              fontFamily: "'Inter', sans-serif",
          }
        : {
              width: '100%',
              maxWidth: '480px',
              background: '#ffffff',
              borderRadius: '24px 24px 0 0',
              padding: '8px 24px 32px',
              boxShadow: '0 -16px 48px rgba(0,0,0,0.2)',
              maxHeight: '90vh',
              overflowY: 'auto',
              fontFamily: "'Inter', sans-serif",
          };

    return (
        <div
            data-testid="save-target-backdrop"
            onClick={handleBackdropClick}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(0, 0, 0, 0.5)',
                display: 'flex',
                alignItems: isDesktop ? 'center' : 'flex-end',
                justifyContent: 'center',
                zIndex: 100,
                padding: isDesktop ? '24px' : 0,
            }}
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="save-target-sheet-title"
                tabIndex={-1}
                data-testid="save-target-sheet"
                style={sheetStyle}
            >
                {!isDesktop && (
                    <div
                        aria-hidden="true"
                        style={{
                            width: '40px',
                            height: '4px',
                            background: '#cbd5e0',
                            borderRadius: '2px',
                            margin: '8px auto 16px',
                        }}
                    />
                )}
                <h2
                    id="save-target-sheet-title"
                    style={{ fontWeight: 700, fontSize: '20px', color: '#003e54', margin: 0 }}
                >
                    Guardar en…
                </h2>
                <p
                    aria-live="polite"
                    style={{ fontSize: '13px', color: '#71787d', margin: '4px 0 20px' }}
                >
                    {selectedCount} {selectedCount === 1 ? 'item seleccionado' : 'items seleccionados'}
                </p>

                {(activeLists?.length ?? 0) === 0 ? (
                    <div
                        data-testid="save-target-empty"
                        style={{ fontSize: '14px', color: '#71787d', padding: '16px 0' }}
                    >
                        Aún no tienes listas activas. Crea una nueva lista para guardar la selección.
                    </div>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        {activeLists.map((list) => {
                            const isSelected = chosenListId === list.id;
                            return (
                                <button
                                    key={list.id}
                                    type="button"
                                    onClick={() => chooseExisting(list.id)}
                                    aria-pressed={isSelected}
                                    data-testid={`save-target-list-${list.id}`}
                                    onFocus={(e) => {
                                        e.currentTarget.style.boxShadow = focusRing;
                                    }}
                                    onBlur={(e) => {
                                        e.currentTarget.style.boxShadow = 'none';
                                    }}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '14px',
                                        padding: '14px',
                                        borderRadius: '14px',
                                        background: isSelected ? '#ebf8ff' : '#f7f9fb',
                                        border: isSelected ? '2px solid #003e54' : '2px solid transparent',
                                        cursor: 'pointer',
                                        textAlign: 'left',
                                        width: '100%',
                                        fontFamily: 'inherit',
                                        outline: 'none',
                                    }}
                                >
                                    <span
                                        aria-hidden="true"
                                        style={{
                                            width: '40px',
                                            height: '40px',
                                            background: '#fff',
                                            borderRadius: '10px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: '20px',
                                        }}
                                    >
                                        {list.emoji || '🛒'}
                                    </span>
                                    <span style={{ flex: 1 }}>
                                        <span
                                            style={{
                                                display: 'block',
                                                fontWeight: 700,
                                                fontSize: '15px',
                                                color: '#191c1e',
                                            }}
                                        >
                                            {list.name}
                                        </span>
                                        <span style={{ display: 'block', fontSize: '12px', color: '#71787d' }}>
                                            {list.items_total ?? 0} items · activa
                                        </span>
                                    </span>
                                    <span
                                        aria-hidden="true"
                                        style={{
                                            width: '22px',
                                            height: '22px',
                                            borderRadius: '50%',
                                            background: isSelected ? '#003e54' : 'transparent',
                                            border: isSelected ? '2px solid #003e54' : '2px solid #cbd5e0',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: '13px',
                                            fontWeight: 700,
                                        }}
                                    >
                                        {isSelected ? '✓' : ''}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}

                <div style={{ height: '1px', background: '#e6e8ea', margin: '16px 0' }} aria-hidden="true" />

                <button
                    type="button"
                    onClick={chooseNew}
                    disabled={freemiumExceeded}
                    aria-pressed={createNew}
                    aria-describedby={freemiumExceeded ? 'save-target-new-list-hint' : undefined}
                    data-testid="save-target-new-list"
                    onFocus={(e) => {
                        if (!e.currentTarget.disabled) {
                            e.currentTarget.style.boxShadow = focusRing;
                        }
                    }}
                    onBlur={(e) => {
                        e.currentTarget.style.boxShadow = 'none';
                    }}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '14px',
                        padding: '14px',
                        borderRadius: '14px',
                        background: createNew ? '#ebf8ff' : '#f7f9fb',
                        border: createNew ? '2px solid #003e54' : '2px solid transparent',
                        cursor: freemiumExceeded ? 'not-allowed' : 'pointer',
                        textAlign: 'left',
                        width: '100%',
                        fontFamily: 'inherit',
                        outline: 'none',
                    }}
                >
                    <span
                        aria-hidden="true"
                        style={{
                            width: '40px',
                            height: '40px',
                            background: freemiumExceeded ? '#e6e8ea' : 'rgba(111, 251, 190, 0.3)',
                            color: freemiumExceeded ? '#41484c' : '#002113',
                            borderRadius: '10px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '22px',
                            fontWeight: 700,
                        }}
                    >
                        ＋
                    </span>
                    <span style={{ flex: 1 }}>
                        <span
                            style={{
                                display: 'block',
                                fontWeight: 700,
                                fontSize: '15px',
                                color: freemiumExceeded ? '#41484c' : '#003e54',
                            }}
                        >
                            Nueva lista
                        </span>
                        <span
                            id={freemiumExceeded ? 'save-target-new-list-hint' : undefined}
                            style={{
                                display: 'block',
                                fontSize: '12px',
                                color: freemiumExceeded ? '#41484c' : '#71787d',
                            }}
                        >
                            {freemiumExceeded
                                ? 'Has alcanzado el límite de 3 listas activas'
                                : 'Crear una lista nueva con la selección'}
                        </span>
                    </span>
                </button>

                <div style={{ marginTop: '24px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={!canConfirm}
                        data-testid="save-target-confirm"
                        onFocus={(e) => {
                            if (!e.currentTarget.disabled) {
                                e.currentTarget.style.boxShadow = focusRing;
                            }
                        }}
                        onBlur={(e) => {
                            e.currentTarget.style.boxShadow = 'none';
                        }}
                        style={{
                            background: canConfirm ? '#003e54' : '#cbd5e0',
                            color: '#ffffff',
                            padding: '16px',
                            borderRadius: '14px',
                            fontWeight: 700,
                            fontSize: '15px',
                            border: 'none',
                            cursor: canConfirm ? 'pointer' : 'not-allowed',
                            opacity: canConfirm ? 1 : 0.6,
                            fontFamily: 'inherit',
                            outline: 'none',
                        }}
                    >
                        {confirmLabel}
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        data-testid="save-target-cancel"
                        onFocus={(e) => {
                            e.currentTarget.style.boxShadow = focusRing;
                        }}
                        onBlur={(e) => {
                            e.currentTarget.style.boxShadow = 'none';
                        }}
                        style={{
                            background: 'transparent',
                            color: '#41484c',
                            padding: '12px',
                            border: 'none',
                            cursor: 'pointer',
                            fontWeight: 600,
                            fontSize: '14px',
                            fontFamily: 'inherit',
                            outline: 'none',
                        }}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    );
}
