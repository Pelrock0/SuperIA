import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { generateList, confirmNewList, confirmExistingList } from '../lib/listGenerationApi';
import api from '../lib/api';
import SelectListModal from '../components/dashboard/SelectListModal';

export default function AIGeneratePage() {
    const navigate = useNavigate();

    // Step 1: prompt
    const [description, setDescription] = useState('');
    const [people, setPeople] = useState(2);
    const [isGenerating, setIsGenerating] = useState(false);

    // Step 2: preview
    const [products, setProducts] = useState(null);
    const [meta, setMeta] = useState(null);
    const [error, setError] = useState('');

    // Confirm
    const [listName, setListName] = useState('');
    const [showNameInput, setShowNameInput] = useState(false);
    const [isConfirming, setIsConfirming] = useState(false);
    const [showSelectList, setShowSelectList] = useState(false);
    const [activeLists, setActiveLists] = useState([]);
    const [confirmed, setConfirmed] = useState(false);

    const handleGenerate = async () => {
        setIsGenerating(true);
        setError('');
        try {
            const result = await generateList(description, people);
            setProducts(result.products);
            setMeta(result.meta);
        } catch (err) {
            const code = err.response?.data?.error?.code;
            const message = err.response?.data?.error?.message;
            if (code === 'GENERATION_LIMIT') {
                setError(message || 'Has alcanzado tu limite de 5 generaciones diarias.');
            } else if (code === 'AI_LIMIT') {
                setError(message || 'Has alcanzado tu limite diario de operaciones IA.');
            } else {
                setError(message || 'Error al generar la lista. Intentalo de nuevo.');
            }
        } finally {
            setIsGenerating(false);
        }
    };

    const handleRegenerate = async () => {
        setIsGenerating(true);
        setError('');
        try {
            const result = await generateList(description, people);
            setProducts(result.products);
            setMeta(result.meta);
        } catch (err) {
            const message = err.response?.data?.error?.message;
            setError(message || 'Error al regenerar. Intentalo de nuevo.');
        } finally {
            setIsGenerating(false);
        }
    };

    const handleRemoveProduct = (idx) => {
        setProducts((prev) => prev.filter((_, i) => i !== idx));
    };

    const handleQuantityChange = (idx, value) => {
        setProducts((prev) =>
            prev.map((p, i) => (i === idx ? { ...p, cantidad_tipica: parseFloat(value) || 0 } : p)),
        );
    };

    const handleConfirmNew = async () => {
        if (!listName.trim()) return;
        setIsConfirming(true);
        setError('');
        try {
            const list = await confirmNewList(products, listName.trim());
            setConfirmed(true);
            setTimeout(() => navigate(`/app/listas/${list.id}`), 1000);
        } catch (err) {
            const code = err.response?.data?.error?.code;
            if (code === 'FREEMIUM_LIMIT') {
                setError('Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista primero.');
            } else {
                const msg = err.response?.data?.message || err.response?.data?.error?.message || JSON.stringify(err.response?.data?.errors || 'Error al crear la lista.');
                setError(msg);
            }
        } finally {
            setIsConfirming(false);
        }
    };

    const handleConfirmExisting = async (listId) => {
        setShowSelectList(false);
        setIsConfirming(true);
        setError('');
        try {
            const list = await confirmExistingList(products, listId);
            setConfirmed(true);
            setTimeout(() => navigate(`/app/listas/${list.id}`), 1000);
        } catch (err) {
            setError('Error al anadir items a la lista.');
        } finally {
            setIsConfirming(false);
        }
    };

    const openSelectList = async () => {
        try {
            const response = await api.get('/lists');
            setActiveLists(response.data.data.active || []);
            setShowSelectList(true);
        } catch {
            setError('Error al cargar las listas.');
        }
    };

    const isPreview = products !== null && products.length > 0;

    return (
        <div style={{ minHeight: '100vh', background: '#f7f9fb', fontFamily: "'Inter', sans-serif", paddingBottom: '160px' }}>
            {/* TopAppBar */}
            <header
                style={{
                    position: 'sticky',
                    top: 0,
                    zIndex: 50,
                    background: '#f7f9fb',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '16px 24px',
                    width: '100%',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                    <span
                        className="material-symbols-outlined"
                        style={{ color: '#002736', cursor: 'pointer' }}
                        onClick={() => navigate('/app')}
                    >
                        arrow_back
                    </span>
                    <h1 style={{ fontSize: '20px', fontWeight: 700, letterSpacing: '-0.025em', color: '#002736', margin: 0 }}>
                        Superlistia
                    </h1>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#10b981' }}>
                    <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>auto_awesome</span>
                    <span style={{ fontSize: '14px', fontWeight: 700 }}>Generar lista con IA</span>
                </div>
                <span
                    className="material-symbols-outlined"
                    style={{ color: '#71787d', cursor: 'pointer' }}
                    onClick={() => navigate('/app')}
                >
                    account_circle
                </span>
            </header>

            <main style={{ maxWidth: '672px', margin: '0 auto', padding: '32px 24px' }}>
                {error && (
                    <div
                        role="alert"
                        data-testid="generation-error"
                        style={{
                            background: '#ffdad6',
                            color: '#93000a',
                            padding: '12px 16px',
                            borderRadius: '12px',
                            fontSize: '14px',
                            marginBottom: '24px',
                        }}
                    >
                        {error}
                    </div>
                )}

                {confirmed && (
                    <div
                        role="status"
                        data-testid="confirm-success"
                        style={{
                            background: 'rgba(111, 251, 190, 0.3)',
                            color: '#002a1a',
                            padding: '12px 16px',
                            borderRadius: '12px',
                            fontSize: '14px',
                            marginBottom: '24px',
                        }}
                    >
                        Lista creada. Redirigiendo...
                    </div>
                )}

                {/* Step 1: Prompt */}
                <section data-testid="prompt-section" style={{ marginBottom: '48px' }}>
                    <div
                        style={{
                            background: '#ffffff',
                            borderRadius: '24px',
                            padding: '24px',
                            boxShadow: '0 4px 24px rgba(0, 39, 54, 0.04)',
                        }}
                    >
                        <label
                            htmlFor="description"
                            style={{
                                display: 'block',
                                fontSize: '11px',
                                fontWeight: 700,
                                textTransform: 'uppercase',
                                letterSpacing: '0.05em',
                                color: '#41484c',
                                marginBottom: '16px',
                                marginLeft: '8px',
                            }}
                        >
                            ¿Que tienes en mente?
                        </label>
                        <textarea
                            id="description"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Describe que necesitas... ej: Cena de cumpleanos para 8 personas, Semana de dieta mediterranea"
                            maxLength={500}
                            rows={5}
                            disabled={isGenerating}
                            data-testid="description-input"
                            style={{
                                width: '100%',
                                background: '#f2f4f6',
                                border: 'none',
                                borderRadius: '12px',
                                padding: '16px',
                                color: '#191c1e',
                                fontSize: '14px',
                                minHeight: '140px',
                                resize: 'none',
                                outline: 'none',
                                fontFamily: "'Inter', sans-serif",
                            }}
                        />

                        <div style={{ marginTop: '24px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div>
                                <span style={{ fontSize: '14px', fontWeight: 500, color: '#41484c', marginLeft: '8px' }}>
                                    Comensales
                                </span>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '16px',
                                        marginTop: '8px',
                                        background: '#f2f4f6',
                                        borderRadius: '9999px',
                                        padding: '8px 16px',
                                        width: 'fit-content',
                                    }}
                                >
                                    <button
                                        type="button"
                                        onClick={() => setPeople((p) => Math.max(1, p - 1))}
                                        disabled={people <= 1 || isGenerating}
                                        data-testid="people-minus"
                                        style={{
                                            width: '32px',
                                            height: '32px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            borderRadius: '9999px',
                                            background: '#ffffff',
                                            color: '#002736',
                                            border: 'none',
                                            boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
                                            cursor: people <= 1 || isGenerating ? 'not-allowed' : 'pointer',
                                            opacity: people <= 1 || isGenerating ? 0.5 : 1,
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ fontSize: '18px' }}>remove</span>
                                    </button>
                                    <span
                                        data-testid="people-count"
                                        style={{ fontWeight: 700, color: '#002736', width: '16px', textAlign: 'center' }}
                                    >
                                        {people}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setPeople((p) => Math.min(50, p + 1))}
                                        disabled={people >= 50 || isGenerating}
                                        data-testid="people-plus"
                                        style={{
                                            width: '32px',
                                            height: '32px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            borderRadius: '9999px',
                                            background: '#ffffff',
                                            color: '#002736',
                                            border: 'none',
                                            boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
                                            cursor: people >= 50 || isGenerating ? 'not-allowed' : 'pointer',
                                            opacity: people >= 50 || isGenerating ? 0.5 : 1,
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ fontSize: '18px' }}>add</span>
                                    </button>
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={isPreview ? handleRegenerate : handleGenerate}
                                disabled={!description.trim() || isGenerating}
                                data-testid="generate-button"
                                style={{
                                    background: '#10B981',
                                    color: '#ffffff',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '8px',
                                    padding: '16px 32px',
                                    borderRadius: '12px',
                                    fontWeight: 700,
                                    border: 'none',
                                    boxShadow: '0 8px 20px rgba(16, 185, 129, 0.3)',
                                    cursor: !description.trim() || isGenerating ? 'not-allowed' : 'pointer',
                                    opacity: !description.trim() || isGenerating ? 0.5 : 1,
                                    transition: 'all 0.2s',
                                    fontSize: '14px',
                                    fontFamily: "'Inter', sans-serif",
                                }}
                            >
                                <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>auto_awesome</span>
                                <span>{isGenerating ? 'Generando...' : isPreview ? 'Regenerar' : 'Generar lista'}</span>
                            </button>
                        </div>
                    </div>
                </section>

                {/* Loading */}
                {isGenerating && !isPreview && (
                    <div data-testid="generating-loading" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '48px 0', gap: '16px' }}>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <div style={{ width: '12px', height: '12px', background: '#10b981', borderRadius: '9999px', opacity: 0.4 }} />
                            <div style={{ width: '12px', height: '12px', background: '#10b981', borderRadius: '9999px', opacity: 0.7 }} />
                            <div style={{ width: '12px', height: '12px', background: '#10b981', borderRadius: '9999px' }} />
                        </div>
                        <p role="status" aria-live="polite" style={{ color: '#10b981', fontWeight: 500, margin: 0 }}>Pensando tu lista...</p>
                    </div>
                )}

                {/* Step 2: Preview */}
                {isPreview && !isGenerating && (
                    <>
                        <section data-testid="preview-section" style={{ marginBottom: '24px' }}>
                            <p style={{ fontSize: '14px', color: '#41484c', marginBottom: '24px', marginLeft: '8px' }}>
                                {products.length} {products.length === 1 ? 'producto' : 'productos'} para {meta?.people || people} {(meta?.people || people) === 1 ? 'persona' : 'personas'}
                            </p>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                {products.map((product, idx) => (
                                    <div
                                        key={idx}
                                        data-testid={`preview-item-${idx}`}
                                        style={{
                                            background: '#ffffff',
                                            padding: '20px',
                                            borderRadius: '16px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            transition: 'background 0.2s',
                                            boxShadow: '0 4px 24px rgba(0, 39, 54, 0.04)',
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '16px', flex: 1, minWidth: 0 }}>
                                            <div>
                                                <p style={{ fontWeight: 700, color: '#002736', margin: 0 }}>{product.nombre}</p>
                                                {product.reason && (
                                                    <p style={{ fontSize: '12px', color: '#41484c', margin: '4px 0 0 0' }}>{product.reason}</p>
                                                )}
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                                                <input
                                                    type="number"
                                                    value={product.cantidad_tipica ?? ''}
                                                    onChange={(e) => handleQuantityChange(idx, e.target.value)}
                                                    min="0"
                                                    step="0.5"
                                                    data-testid={`quantity-input-${idx}`}
                                                    style={{
                                                        width: '60px',
                                                        padding: '6px 12px',
                                                        background: '#f2f4f6',
                                                        border: 'none',
                                                        borderRadius: '8px',
                                                        fontSize: '14px',
                                                        fontWeight: 700,
                                                        color: '#003e54',
                                                        textAlign: 'right',
                                                        outline: 'none',
                                                        fontFamily: "'Inter', sans-serif",
                                                    }}
                                                />
                                                <span style={{ fontSize: '12px', color: '#41484c', fontWeight: 500 }}>
                                                    {product.unidad_tipica || ''}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveProduct(idx)}
                                                aria-label={`Eliminar ${product.nombre}`}
                                                data-testid={`remove-item-${idx}`}
                                                style={{
                                                    color: '#ba1a1a',
                                                    background: 'none',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    padding: '4px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    opacity: 0.6,
                                                }}
                                            >
                                                <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>close</span>
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </>
                )}

                {/* Empty preview */}
                {products !== null && products.length === 0 && !isGenerating && (
                    <div data-testid="empty-preview" style={{ textAlign: 'center', padding: '48px 0' }}>
                        <p style={{ color: '#71787d', margin: 0 }}>Has eliminado todos los productos. Genera de nuevo o vuelve al dashboard.</p>
                    </div>
                )}

                {showSelectList && (
                    <SelectListModal
                        lists={activeLists}
                        productName="items generados"
                        onSelect={handleConfirmExisting}
                        onCancel={() => setShowSelectList(false)}
                    />
                )}
            </main>

            {/* Bottom Sticky Actions */}
            {isPreview && !isGenerating && products.length > 0 && (
                <div
                    style={{
                        position: 'fixed',
                        bottom: 0,
                        left: 0,
                        width: '100%',
                        zIndex: 50,
                        padding: '16px 24px 32px',
                        background: 'rgba(255, 255, 255, 0.7)',
                        backdropFilter: 'blur(20px)',
                        WebkitBackdropFilter: 'blur(20px)',
                        borderRadius: '32px 32px 0 0',
                        boxShadow: '0 -8px 32px rgba(0, 39, 54, 0.08)',
                    }}
                >
                    <div style={{ maxWidth: '672px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        {!showNameInput ? (
                            <>
                                <button
                                    type="button"
                                    onClick={openSelectList}
                                    disabled={isConfirming || confirmed || products.length === 0}
                                    data-testid="add-to-existing"
                                    style={{
                                        width: '100%',
                                        background: '#002736',
                                        color: '#ffffff',
                                        padding: '20px',
                                        borderRadius: '16px',
                                        fontWeight: 700,
                                        border: 'none',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: '12px',
                                        boxShadow: '0 8px 20px rgba(0, 39, 54, 0.15)',
                                        cursor: isConfirming || confirmed ? 'not-allowed' : 'pointer',
                                        opacity: isConfirming || confirmed ? 0.5 : 1,
                                        fontSize: '16px',
                                        fontFamily: "'Inter', sans-serif",
                                    }}
                                >
                                    <span>Anadir {products.length} items a la lista</span>
                                    <span className="material-symbols-outlined" style={{ fontSize: '18px' }}>shopping_basket</span>
                                </button>
                                <div style={{ display: 'flex', justifyContent: 'center' }}>
                                    <button
                                        type="button"
                                        onClick={() => setShowNameInput(true)}
                                        disabled={isConfirming || confirmed || products.length === 0}
                                        data-testid="create-new-list"
                                        style={{
                                            background: 'none',
                                            border: 'none',
                                            color: '#00677d',
                                            fontWeight: 700,
                                            fontSize: '14px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '8px',
                                            padding: '8px',
                                            cursor: isConfirming || confirmed ? 'not-allowed' : 'pointer',
                                            opacity: isConfirming || confirmed ? 0.5 : 1,
                                            fontFamily: "'Inter', sans-serif",
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ fontSize: '14px' }}>add_circle</span>
                                        Anadir a lista nueva
                                    </button>
                                </div>
                            </>
                        ) : (
                            <div data-testid="name-input-section">
                                <label
                                    htmlFor="list-name"
                                    style={{
                                        display: 'block',
                                        fontSize: '11px',
                                        fontWeight: 700,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        color: '#41484c',
                                        marginBottom: '8px',
                                    }}
                                >
                                    Nombre de la lista
                                </label>
                                <div style={{ display: 'flex', gap: '12px' }}>
                                    <input
                                        type="text"
                                        id="list-name"
                                        value={listName}
                                        onChange={(e) => setListName(e.target.value)}
                                        placeholder="ej: Cena de cumpleanos"
                                        maxLength={60}
                                        data-testid="list-name-input"
                                        style={{
                                            flex: 1,
                                            padding: '12px 16px',
                                            background: '#f2f4f6',
                                            border: '1px solid #c1c7cd',
                                            borderRadius: '12px',
                                            color: '#191c1e',
                                            fontSize: '14px',
                                            outline: 'none',
                                            fontFamily: "'Inter', sans-serif",
                                        }}
                                    />
                                    <button
                                        type="button"
                                        onClick={handleConfirmNew}
                                        disabled={!listName.trim() || isConfirming}
                                        data-testid="confirm-create"
                                        style={{
                                            background: '#002736',
                                            color: '#ffffff',
                                            padding: '12px 24px',
                                            borderRadius: '12px',
                                            fontWeight: 700,
                                            border: 'none',
                                            cursor: !listName.trim() || isConfirming ? 'not-allowed' : 'pointer',
                                            opacity: !listName.trim() || isConfirming ? 0.5 : 1,
                                            fontSize: '14px',
                                            fontFamily: "'Inter', sans-serif",
                                        }}
                                    >
                                        {isConfirming ? 'Creando...' : 'Crear'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowNameInput(false)}
                                        style={{
                                            background: 'none',
                                            border: 'none',
                                            color: '#71787d',
                                            cursor: 'pointer',
                                            padding: '12px',
                                            fontFamily: "'Inter', sans-serif",
                                        }}
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
