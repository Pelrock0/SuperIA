import React, { useState, useEffect, useCallback, useRef } from 'react';
import api from '../../lib/api';
import similarText from '../../lib/similarText';

const DUPLICATE_THRESHOLD = 0.80;

const CATEGORY_OPTIONS = [
    { value: 'frutas_verduras', label: 'Frutas', badge: '#6ffbbe', badgeText: '#002113' },
    { value: 'lacteos_huevos', label: 'Lacteos', badge: '#b3ebff', badgeText: '#001f27' },
    { value: 'carnes_pescados', label: 'Carnes', badge: '#ffdad6', badgeText: '#93000a' },
    { value: 'bebidas', label: 'Bebidas', badge: '#c1e8ff', badgeText: '#001e2b' },
    { value: 'panaderia', label: 'Pan', badge: '#e6e8ea', badgeText: '#41484c' },
    { value: 'congelados', label: 'Congelados', badge: '#b3ebff', badgeText: '#001f27' },
    { value: 'limpieza', label: 'Limpieza', badge: '#e6e8ea', badgeText: '#41484c' },
    { value: 'higiene_personal', label: 'Higiene', badge: '#e6e8ea', badgeText: '#41484c' },
    { value: 'conservas', label: 'Conservas', badge: '#e6e8ea', badgeText: '#41484c' },
    { value: 'otros', label: 'Otros', badge: '#e0e3e5', badgeText: '#71787d' },
];

const UNITS = ['ud', 'kg', 'g', 'L', 'ml', 'pack'];

export default function AddItemModal({ listId, existingItems = [], onAdd, onIncrementExisting, onClose }) {
    const [name, setName] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [unit, setUnit] = useState('ud');
    const [category, setCategory] = useState(null);
    const [suggestions, setSuggestions] = useState([]);
    const [isSearching, setIsSearching] = useState(false);
    const [duplicateMatch, setDuplicateMatch] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const inputRef = useRef(null);
    const debounceRef = useRef(null);

    useEffect(() => {
        if (inputRef.current) inputRef.current.focus();
    }, []);

    const fetchSuggestions = useCallback(async (query) => {
        if (query.length < 2) { setSuggestions([]); return; }
        setIsSearching(true);
        try {
            const res = await api.get(`/suggestions?q=${encodeURIComponent(query)}`);
            setSuggestions(res.data?.data?.suggestions || []);
        } catch { setSuggestions([]); }
        finally { setIsSearching(false); }
    }, []);

    const handleNameChange = (val) => {
        setName(val);
        setDuplicateMatch(null);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchSuggestions(val), 150);
    };

    const handleSelectSuggestion = (s) => {
        setName(s.name);
        if (s.quantity) setQuantity(String(s.quantity));
        if (s.unit) setUnit(s.unit);
        if (s.category) setCategory(s.category);
        setSuggestions([]);
    };

    const findDuplicate = () => {
        const trimmed = name.trim();
        if (!trimmed) return null;
        for (const item of existingItems) {
            if (similarText(trimmed, item.name) > DUPLICATE_THRESHOLD) return item;
        }
        return null;
    };

    const handleSubmit = async () => {
        if (!name.trim()) return;
        const match = findDuplicate();
        if (match && !duplicateMatch) {
            setDuplicateMatch(match);
            return;
        }
        setIsSubmitting(true);
        const payload = { name: name.trim() };
        if (quantity) payload.quantity = Number(quantity);
        if (unit) payload.unit = unit;
        if (category) payload.category = category;
        const success = await onAdd(payload);
        if (success) onClose();
        setIsSubmitting(false);
    };

    const handleAddAnyway = async () => {
        setDuplicateMatch(null);
        setIsSubmitting(true);
        const payload = { name: name.trim() };
        if (quantity) payload.quantity = Number(quantity);
        if (unit) payload.unit = unit;
        if (category) payload.category = category;
        const success = await onAdd(payload);
        if (success) onClose();
        setIsSubmitting(false);
    };

    const handleIncrement = async () => {
        if (duplicateMatch && onIncrementExisting) {
            await onIncrementExisting(duplicateMatch.id, Number(quantity) || 1);
        }
        onClose();
    };

    const getCategoryBadge = (cat) => {
        const opt = CATEGORY_OPTIONS.find(c => c.value === cat);
        return opt || { label: cat, badge: '#e0e3e5', badgeText: '#71787d' };
    };

    return (
        <>
            {/* Backdrop */}
            <div
                onClick={onClose}
                style={{
                    position: 'fixed', inset: 0, zIndex: 40,
                    background: 'rgba(0, 39, 54, 0.4)',
                    backdropFilter: 'blur(2px)',
                }}
            />

            {/* Bottom Sheet */}
            <div
                data-testid="add-item-modal"
                style={{
                    position: 'fixed', bottom: 0, left: '50%', transform: 'translateX(-50%)',
                    zIndex: 50,
                    width: '100%', maxWidth: 520,
                    maxHeight: '90vh',
                    background: '#ffffff',
                    borderRadius: '32px 32px 0 0',
                    boxShadow: '0 -8px 40px -10px rgba(0,39,54,0.2)',
                    display: 'flex', flexDirection: 'column',
                    overflow: 'hidden',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                {/* Handle */}
                <div style={{ display: 'flex', justifyContent: 'center', padding: '16px 0 8px' }}>
                    <div style={{ width: 48, height: 6, background: '#c1c7cd', borderRadius: 9999 }} />
                </div>

                {/* Scrollable content */}
                <div style={{ flex: 1, overflowY: 'auto', padding: '0 24px 24px', display: 'flex', flexDirection: 'column', gap: 32 }}>
                    {/* Product name input */}
                    <div style={{ marginTop: 8 }}>
                        <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', color: '#71787d', marginBottom: 8, paddingLeft: 4 }}>
                            Producto
                        </label>
                        <div style={{ display: 'flex', alignItems: 'center' }}>
                            <input
                                ref={inputRef}
                                type="text"
                                value={name}
                                onChange={(e) => handleNameChange(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleSubmit(); } }}
                                enterKeyHint="send"
                                placeholder="¿Que necesitas?"
                                data-testid="modal-product-input"
                                style={{
                                    width: '100%', background: 'transparent', border: 'none',
                                    fontSize: 28, fontWeight: 700, color: '#191c1e',
                                    outline: 'none', padding: 0,
                                    fontFamily: "'Inter', sans-serif",
                                }}
                            />
                            <div style={{ width: 2, height: 32, background: '#002736', animation: 'pulse 1s infinite', flexShrink: 0 }} />
                        </div>
                    </div>

                    {/* Autocomplete suggestions */}
                    {(suggestions.length > 0 || isSearching) && (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {suggestions.map((s, i) => {
                                const badge = getCategoryBadge(s.category);
                                return (
                                    <div
                                        key={i}
                                        onClick={() => handleSelectSuggestion(s)}
                                        style={{
                                            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                                            padding: 12, background: '#f2f4f6', borderRadius: 12,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                            <span className="material-symbols-outlined" style={{ color: '#71787d' }}>search</span>
                                            <span style={{ fontWeight: 500, color: '#191c1e' }}>{s.name}</span>
                                        </div>
                                        {s.category && (
                                            <span style={{
                                                fontSize: 10, fontWeight: 700, padding: '4px 8px',
                                                background: badge.badge, color: badge.badgeText,
                                                borderRadius: 6, letterSpacing: '0.05em', textTransform: 'uppercase',
                                            }}>
                                                {badge.label}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                            {isSearching && (
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, paddingLeft: 4 }}>
                                    <span className="material-symbols-outlined" style={{ fontSize: 14, color: '#10b981', fontVariationSettings: "'FILL' 1" }}>auto_awesome</span>
                                    <span style={{ fontSize: 12, fontStyle: 'italic', color: '#10b981' }}>Buscando mas...</span>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Duplicate warning */}
                    {duplicateMatch && (
                        <div data-testid="duplicate-warning" style={{ background: '#FFF7ED', padding: 16, borderRadius: 12 }}>
                            <div style={{ display: 'flex', gap: 12, marginBottom: 12 }}>
                                <span className="material-symbols-outlined" style={{ color: '#ea580c' }}>warning</span>
                                <p style={{ fontSize: 14, fontWeight: 500, color: '#9a3412' }}>
                                    Ya tienes {duplicateMatch.name} en la lista. ¿Aumentar cantidad?
                                </p>
                            </div>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <button
                                    onClick={handleAddAnyway}
                                    data-testid="add-anyway"
                                    style={{
                                        flex: 1, padding: '8px 16px', background: 'rgba(255,255,255,0.6)',
                                        fontSize: 12, fontWeight: 700, color: '#9a3412',
                                        borderRadius: 8, border: 'none', cursor: 'pointer',
                                    }}
                                >
                                    Anadir igualmente
                                </button>
                                <button
                                    onClick={handleIncrement}
                                    data-testid="increment-quantity"
                                    style={{
                                        flex: 1, padding: '8px 16px', background: '#ea580c',
                                        fontSize: 12, fontWeight: 700, color: '#ffffff',
                                        borderRadius: 8, border: 'none', cursor: 'pointer',
                                    }}
                                >
                                    Aumentar cantidad
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Quantity + Unit + Price */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        <div>
                            <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', color: '#71787d', marginBottom: 8, paddingLeft: 4 }}>
                                Cantidad
                            </label>
                            <div style={{ display: 'flex', alignItems: 'center', background: '#f2f4f6', borderRadius: 12, padding: 4 }}>
                                <input
                                    type="number"
                                    value={quantity}
                                    onChange={(e) => setQuantity(e.target.value)}
                                    min="0"
                                    step="0.5"
                                    data-testid="modal-quantity"
                                    style={{
                                        width: '100%', background: 'transparent', border: 'none',
                                        fontSize: 18, fontWeight: 700, color: '#191c1e',
                                        outline: 'none', padding: '8px 12px',
                                        fontFamily: "'Inter', sans-serif",
                                    }}
                                />
                                <select
                                    value={unit}
                                    onChange={(e) => setUnit(e.target.value)}
                                    style={{
                                        background: '#e6e8ea', border: 'none', borderRadius: 8,
                                        fontSize: 14, fontWeight: 700, color: '#003e54',
                                        padding: '8px 12px', marginRight: 4,
                                        outline: 'none', cursor: 'pointer',
                                        fontFamily: "'Inter', sans-serif",
                                    }}
                                >
                                    {UNITS.map(u => <option key={u} value={u}>{u}</option>)}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', color: '#71787d', marginBottom: 8, paddingLeft: 4 }}>
                                Precio est.
                            </label>
                            <div style={{ display: 'flex', alignItems: 'center', background: '#f2f4f6', borderRadius: 12, padding: '10px 12px' }}>
                                <span style={{ fontWeight: 700, color: '#41484c', marginRight: 4 }}>€</span>
                                <input
                                    type="number"
                                    placeholder="0.00"
                                    min="0"
                                    step="0.01"
                                    data-testid="modal-price"
                                    style={{
                                        width: '100%', background: 'transparent', border: 'none',
                                        fontSize: 18, fontWeight: 700, color: '#191c1e',
                                        outline: 'none', padding: 0,
                                        fontFamily: "'Inter', sans-serif",
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Category selector */}
                    <div>
                        <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', color: '#71787d', marginBottom: 8, paddingLeft: 4 }}>
                            Categoria
                        </label>
                        <div style={{
                            display: 'flex', flexWrap: 'wrap', gap: 8, paddingBottom: 8,
                        }}>
                            {CATEGORY_OPTIONS.map(opt => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => setCategory(category === opt.value ? null : opt.value)}
                                    style={{
                                        flexShrink: 0, padding: '10px 20px',
                                        borderRadius: 9999, fontSize: 14, fontWeight: 700,
                                        border: 'none', cursor: 'pointer',
                                        background: category === opt.value ? '#003e54' : '#f2f4f6',
                                        color: category === opt.value ? '#ffffff' : '#41484c',
                                        boxShadow: category === opt.value ? '0 4px 12px rgba(0,39,54,0.15)' : 'none',
                                    }}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Action area — inside scroll so keyboard doesn't cover it */}
                    <div style={{
                        display: 'flex', flexDirection: 'column', gap: 12,
                        paddingTop: 8,
                    }}>
                        <button
                            onClick={handleSubmit}
                            disabled={!name.trim() || isSubmitting}
                            data-testid="modal-add-button"
                            style={{
                                width: '100%', padding: 16,
                                background: name.trim() ? 'linear-gradient(to right, #002736, #003e54)' : '#e6e8ea',
                                color: name.trim() ? '#ffffff' : '#71787d',
                                fontWeight: 700, fontSize: 16, borderRadius: 12,
                                border: 'none', cursor: name.trim() ? 'pointer' : 'default',
                                fontFamily: "'Inter', sans-serif",
                            }}
                        >
                            {isSubmitting ? 'Anadiendo...' : 'Anadir'}
                        </button>
                        <button
                            onClick={onClose}
                            style={{
                                width: '100%', textAlign: 'center',
                                fontSize: 14, fontWeight: 700, color: '#71787d',
                                background: 'none', border: 'none', cursor: 'pointer',
                                padding: 8, paddingBottom: 'max(8px, env(safe-area-inset-bottom))',
                            }}
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
