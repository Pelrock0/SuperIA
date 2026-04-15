import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import CreateListModal from '../components/lists/CreateListModal';
import ReplenishmentBanner from '../components/dashboard/ReplenishmentBanner';
import WeeklySummaryBanner from '../components/dashboard/WeeklySummaryBanner';

export default function DashboardPage() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [lists, setLists] = useState({ active: [], archived: [], collaborated: [] });
    const [isLoading, setIsLoading] = useState(true);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [createError, setCreateError] = useState('');
    const [actionError, setActionError] = useState('');
    const [listMenu, setListMenu] = useState(null);

    const fetchLists = useCallback(async () => {
        try {
            const response = await api.get('/lists');
            setLists(response.data.data);
        } catch {
            setActionError('Error al cargar las listas.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchLists();
    }, [fetchLists]);

    const handleCreate = async (data) => {
        setCreateError('');
        try {
            const response = await api.post('/lists', data);
            setShowCreateModal(false);
            navigate(`/app/listas/${response.data.data.id}`);
            return true;
        } catch (err) {
            const msg = err.response?.data?.error?.message || 'Error al crear la lista.';
            setCreateError(msg);
            return false;
        }
    };

    const handleArchive = async (id) => {
        try {
            await api.patch(`/lists/${id}/archive`);
            await fetchLists();
        } catch {
            setActionError('Error al archivar la lista.');
        }
    };

    const handleRestore = async (id) => {
        setActionError('');
        try {
            await api.patch(`/lists/${id}/restore`);
            await fetchLists();
        } catch (err) {
            setActionError(err.response?.data?.error?.message || 'Error al restaurar la lista.');
        }
    };

    const handleDelete = async (id) => {
        try {
            await api.delete(`/lists/${id}`);
            await fetchLists();
        } catch {
            setActionError('Error al eliminar la lista.');
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 86400000);
        if (diff === 0) return 'Hoy';
        if (diff === 1) return 'Ayer';
        return `${diff} dias`;
    };

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#f7f9fb' }} data-testid="dashboard-loading">
                <div className="text-gray-500" role="status" aria-live="polite">Cargando listas...</div>
            </div>
        );
    }

    const hasLists = lists.active.length > 0 || lists.archived.length > 0 || (lists.collaborated || []).length > 0;

    return (
        <div className="min-h-screen pb-32" style={{ backgroundColor: '#f7f9fb', fontFamily: "'Inter', sans-serif" }}>
            {/* TopAppBar */}
            <header className="w-full sticky top-0 z-40" style={{ backgroundColor: '#f7f9fb' }}>
                <div className="flex justify-between items-center px-6 py-4 max-w-5xl mx-auto">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm" style={{ backgroundColor: '#003e54' }}>
                            {user?.name?.[0]?.toUpperCase() || 'U'}
                        </div>
                        <h1 className="font-bold tracking-tighter text-2xl" style={{ color: '#002736' }}>Superia</h1>
                    </div>
                    <div className="flex items-center gap-4">
                        <Link to="/app/historial" className="hover:opacity-80 transition-opacity">
                            <span className="material-symbols-outlined" style={{ color: '#71787d' }}>history</span>
                        </Link>
                        <Link to="/app/profile" className="hover:opacity-80 transition-opacity">
                            <span className="material-symbols-outlined" style={{ color: '#71787d' }}>settings</span>
                        </Link>
                        <button onClick={logout} className="hover:opacity-80 transition-opacity">
                            <span className="material-symbols-outlined" style={{ color: '#71787d' }}>logout</span>
                        </button>
                    </div>
                </div>
            </header>

            <main className="px-6 pt-6 max-w-5xl mx-auto">
                {/* Welcome */}
                <section className="mb-10">
                    <h2 className="text-3xl font-bold tracking-tighter mb-1" style={{ color: '#002736' }}>
                        Hola, {user?.name?.split(' ')[0] || 'Usuario'}
                    </h2>
                    <p style={{ color: '#41484c' }}>
                        {lists.active.length > 0
                            ? `Tienes ${lists.active.length} lista${lists.active.length > 1 ? 's' : ''} activa${lists.active.length > 1 ? 's' : ''}.`
                            : 'Crea tu primera lista de compra.'}
                    </p>
                </section>

                {actionError && (
                    <div className="bg-red-50 text-red-700 p-3 rounded-xl text-sm mb-6" role="alert">{actionError}</div>
                )}

                {/* AI Concierge Banner */}
                {hasLists && (
                    <section
                        className="mb-10 rounded-[24px] p-8 text-white relative overflow-hidden"
                        style={{ background: 'linear-gradient(to bottom right, #002736, #003e54)', boxShadow: '0 24px 48px -12px rgba(0,39,54,0.2)' }}
                    >
                        <div className="relative z-10 flex flex-col gap-4">
                            <div className="flex items-center gap-2">
                                <span className="px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase" style={{ backgroundColor: '#6ffbbe', color: '#002113' }}>AI Concierge</span>
                                <span className="material-symbols-outlined" style={{ color: '#6ffbbe' }}>auto_awesome</span>
                            </div>
                            <h3 className="text-2xl font-bold leading-tight">Genera listas con IA</h3>
                            <p className="max-w-xs" style={{ color: '#9ecde8' }}>Describe lo que necesitas y la IA creara tu lista de compra completa.</p>
                            <Link
                                to="/app/generar"
                                className="px-6 py-3 rounded-xl font-bold w-fit mt-2 hover:opacity-90 transition-all active:scale-95 inline-block"
                                style={{ backgroundColor: '#6ffbbe', color: '#002113' }}
                                data-testid="generate-with-ai"
                            >
                                Generar lista ✨
                            </Link>
                        </div>
                        <div className="absolute -right-10 -bottom-10 opacity-20" style={{ transform: 'rotate(12deg)' }}>
                            <span className="material-symbols-outlined" style={{ fontSize: '180px' }}>shopping_basket</span>
                        </div>
                    </section>
                )}

                {hasLists && (
                    <>
                        <WeeklySummaryBanner />
                        <ReplenishmentBanner activeLists={lists.active} onAction={fetchLists} />
                    </>
                )}

                {!hasLists ? (
                    <div className="text-center py-20">
                        <span className="material-symbols-outlined text-6xl mb-4 block" style={{ color: '#c1c7cd' }}>shopping_cart</span>
                        <h3 className="text-xl font-bold mb-2" style={{ color: '#002736' }}>Sin listas todavia</h3>
                        <p className="mb-6" style={{ color: '#41484c' }}>Crea tu primera lista de compra</p>
                        <button
                            onClick={() => { setCreateError(''); setShowCreateModal(true); }}
                            className="px-8 py-3 rounded-xl font-bold text-white hover:opacity-90"
                            style={{ background: 'linear-gradient(to right, #002736, #003e54)' }}
                        >
                            Crear lista
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                            {lists.active.map((list) => (
                                <div
                                    key={list.id}
                                    className="rounded-[20px] p-6 group cursor-pointer transition-all"
                                    style={{ backgroundColor: '#ffffff', boxShadow: '0 4px 24px 0 rgba(0,39,54,0.06)', border: '1px solid transparent' }}
                                    onClick={() => navigate(`/app/listas/${list.id}`)}
                                >
                                    <div className="flex justify-between items-start mb-6">
                                        <div className="p-3 rounded-2xl" style={{ backgroundColor: '#f2f4f6' }}>
                                            <span className="material-symbols-outlined" style={{ color: '#002736' }}>shopping_cart</span>
                                        </div>
                                        <span className="text-xs font-medium px-2 py-1 rounded-lg" style={{ color: '#41484c', backgroundColor: '#f2f4f6' }}>
                                            {formatDate(list.updated_at)}
                                        </span>
                                    </div>
                                    <h4 className="text-xl font-bold mb-2" style={{ color: '#002736' }}>{list.name}</h4>
                                    <div className="flex items-center gap-2 mb-6" style={{ color: '#41484c' }}>
                                        <span className="material-symbols-outlined text-sm">checklist</span>
                                        <span className="text-sm font-medium">{list.items_completed || 0} / {list.items_total || 0} articulos</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        {list.is_shared && <span className="text-[12px] font-bold" style={{ color: '#00677d' }}>COMPARTIDA</span>}
                                        <div style={{ position: 'relative', marginLeft: 'auto' }}>
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setListMenu(listMenu === list.id ? null : list.id);
                                                }}
                                                style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 4, display: 'flex', alignItems: 'center' }}
                                            >
                                                <span className="material-symbols-outlined" style={{ color: '#71787d', fontSize: 20 }}>more_horiz</span>
                                            </button>
                                            {listMenu === list.id && (
                                                <>
                                                    <div onClick={(e) => { e.stopPropagation(); setListMenu(null); }} style={{ position: 'fixed', inset: 0, zIndex: 30 }} />
                                                    <div style={{
                                                        position: 'absolute', right: 0, top: '100%', marginTop: 4,
                                                        background: '#ffffff', borderRadius: 12, padding: 6, minWidth: 180,
                                                        boxShadow: '0 8px 32px rgba(0,39,54,0.12)', zIndex: 31,
                                                    }}>
                                                        <button
                                                            onClick={(e) => { e.stopPropagation(); setListMenu(null); handleArchive(list.id); }}
                                                            style={{
                                                                display: 'flex', alignItems: 'center', gap: 8, width: '100%',
                                                                padding: '8px 12px', background: 'none', border: 'none',
                                                                borderRadius: 8, cursor: 'pointer', fontSize: 13, color: '#191c1e',
                                                            }}
                                                            onMouseOver={(e) => e.currentTarget.style.background = '#f2f4f6'}
                                                            onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                                        >
                                                            <span className="material-symbols-outlined" style={{ fontSize: 18, color: '#71787d' }}>archive</span>
                                                            Archivar
                                                        </button>
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setListMenu(null);
                                                                if (window.confirm('¿Eliminar esta lista?')) handleDelete(list.id);
                                                            }}
                                                            style={{
                                                                display: 'flex', alignItems: 'center', gap: 8, width: '100%',
                                                                padding: '8px 12px', background: 'none', border: 'none',
                                                                borderRadius: 8, cursor: 'pointer', fontSize: 13, color: '#ba1a1a',
                                                            }}
                                                            onMouseOver={(e) => e.currentTarget.style.background = '#ffdad6'}
                                                            onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                                        >
                                                            <span className="material-symbols-outlined" style={{ fontSize: 18 }}>delete</span>
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {(lists.collaborated || []).length > 0 && (
                            <div className="mb-10">
                                <h3 className="text-lg font-semibold mb-4" style={{ color: '#41484c' }}>
                                    <span className="material-symbols-outlined align-middle mr-1" style={{ fontSize: 20 }}>group</span>
                                    Listas compartidas conmigo ({lists.collaborated.length})
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {lists.collaborated.map((list) => (
                                        <div
                                            key={`collab-${list.id}`}
                                            className="rounded-[20px] p-6 cursor-pointer transition-all hover:shadow-lg"
                                            style={{ background: 'linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%)', boxShadow: '0 4px 24px 0 rgba(0,39,54,0.06)', border: '1px solid #e0f2fe' }}
                                            onClick={() => navigate(`/app/listas/${list.id}`)}
                                        >
                                            <div className="flex justify-between items-start mb-4">
                                                <div className="p-3 rounded-2xl" style={{ backgroundColor: 'rgba(0,39,54,0.06)' }}>
                                                    <span className="material-symbols-outlined" style={{ color: '#002736' }}>shopping_cart</span>
                                                </div>
                                                <span className="text-[11px] font-bold px-2 py-1 rounded-lg" style={{ color: '#005236', backgroundColor: '#d1fae5' }}>
                                                    COLABORADOR
                                                </span>
                                            </div>
                                            <h4 className="text-xl font-bold mb-1" style={{ color: '#002736' }}>{list.name}</h4>
                                            <p className="text-xs mb-4" style={{ color: '#71787d' }}>
                                                de {list.owner_name} &middot; {list.collaborator_mode === 'edit' ? 'Puede editar' : 'Solo lectura'}
                                            </p>
                                            <div className="flex items-center gap-2" style={{ color: '#41484c' }}>
                                                <span className="material-symbols-outlined text-sm">checklist</span>
                                                <span className="text-sm font-medium">{list.items_completed || 0} / {list.items_total || 0} articulos</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {lists.archived.length > 0 && (
                            <div className="mb-10">
                                <h3 className="text-lg font-semibold mb-4" style={{ color: '#41484c' }}>Archivadas ({lists.archived.length})</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 opacity-60">
                                    {lists.archived.map((list) => (
                                        <div
                                            key={list.id}
                                            className="rounded-[16px] p-4 cursor-pointer hover:opacity-80"
                                            style={{ backgroundColor: '#ffffff', boxShadow: '0 2px 12px rgba(0,39,54,0.04)' }}
                                            onClick={() => navigate(`/app/listas/${list.id}`)}
                                        >
                                            <h4 className="font-bold mb-1" style={{ color: '#002736' }}>{list.name}</h4>
                                            <p className="text-xs" style={{ color: '#71787d' }}>{list.items_total || 0} articulos</p>
                                            <div className="flex gap-2 mt-3">
                                                <button onClick={(e) => { e.stopPropagation(); handleRestore(list.id); }} className="text-xs font-medium hover:opacity-70" style={{ color: '#00677d' }}>Restaurar</button>
                                                <button onClick={(e) => { e.stopPropagation(); handleDelete(list.id); }} className="text-xs font-medium hover:opacity-70" style={{ color: '#ba1a1a' }}>Eliminar</button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </main>

            {hasLists && (
                <button
                    onClick={() => { setCreateError(''); setShowCreateModal(true); }}
                    className="fixed bottom-8 right-6 w-16 h-16 rounded-2xl shadow-2xl flex items-center justify-center hover:scale-105 active:scale-95 transition-all z-50 text-white"
                    style={{ background: 'linear-gradient(to top right, #002736, #003e54)' }}
                >
                    <span className="material-symbols-outlined text-3xl">add</span>
                </button>
            )}

            {showCreateModal && (
                <CreateListModal onClose={() => setShowCreateModal(false)} onSubmit={handleCreate} error={createError} />
            )}
        </div>
    );
}
