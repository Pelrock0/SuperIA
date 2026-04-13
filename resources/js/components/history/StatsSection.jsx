import React from 'react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

const COLORS = ['#4f46e5', '#7c3aed', '#ec4899', '#f59e0b', '#10b981'];

const CATEGORY_LABELS = {
    frutas_verduras: 'Frutas y verduras',
    carnes_pescados: 'Carnes y pescados',
    lacteos_huevos: 'Lacteos y huevos',
    panaderia: 'Panaderia',
    bebidas: 'Bebidas',
    congelados: 'Congelados',
    limpieza: 'Limpieza',
    higiene_personal: 'Higiene personal',
    conservas: 'Conservas',
    otros: 'Otros',
};

export default function StatsSection({ stats }) {
    if (!stats || !stats.has_enough_data) {
        return (
            <div className="bg-white border border-gray-200 rounded-lg p-6 mb-6 text-center" data-testid="stats-not-enough">
                <p className="text-gray-500 text-sm">Completa al menos 3 listas para ver estadisticas.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6 mb-8" data-testid="stats-section">
            {/* Monthly Spend */}
            {stats.monthly_spend.length > 0 && (
                <div className="bg-white border border-gray-200 rounded-lg p-6" data-testid="monthly-spend-chart">
                    <h3 className="text-sm font-semibold text-gray-900 mb-4">Gasto mensual estimado</h3>
                    <ResponsiveContainer width="100%" height={200}>
                        <BarChart data={stats.monthly_spend}>
                            <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                            <YAxis tick={{ fontSize: 12 }} />
                            <Tooltip formatter={(value) => [`${Number(value).toFixed(2)}€`, 'Total']} />
                            <Bar dataKey="total" fill="#4f46e5" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                    <p className="text-xs text-gray-400 mt-2">Los importes son estimaciones salvo confirmacion real.</p>
                </div>
            )}

            {/* Top Categories */}
            {stats.top_categories.length > 0 && (
                <div className="bg-white border border-gray-200 rounded-lg p-6" data-testid="top-categories">
                    <h3 className="text-sm font-semibold text-gray-900 mb-4">Categorias mas compradas</h3>
                    <div className="flex gap-6">
                        <div className="w-32 h-32 flex-shrink-0">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={stats.top_categories}
                                        dataKey="count"
                                        nameKey="category"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={50}
                                    >
                                        {stats.top_categories.map((_, idx) => (
                                            <Cell key={idx} fill={COLORS[idx % COLORS.length]} />
                                        ))}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="flex-1 space-y-2">
                            {stats.top_categories.map((cat, idx) => (
                                <div key={cat.category} className="flex items-center gap-2 text-sm">
                                    <span className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: COLORS[idx % COLORS.length] }} />
                                    <span className="flex-1 text-gray-700">{CATEGORY_LABELS[cat.category] || cat.category}</span>
                                    <span className="text-gray-500">{cat.percentage}%</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Top Products */}
            {stats.top_products.length > 0 && (
                <div className="bg-white border border-gray-200 rounded-lg p-6" data-testid="top-products">
                    <h3 className="text-sm font-semibold text-gray-900 mb-4">Productos mas frecuentes</h3>
                    <ol className="space-y-1">
                        {stats.top_products.map((product, idx) => (
                            <li key={product.name} className="flex items-center gap-2 text-sm">
                                <span className="text-gray-400 w-5 text-right">{idx + 1}.</span>
                                <span className="flex-1 text-gray-700">{product.name}</span>
                                <span className="text-gray-500">{product.count}x</span>
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </div>
    );
}
