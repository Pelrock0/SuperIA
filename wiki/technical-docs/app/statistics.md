# Technical Docs — History & Statistics

**Keywords:** stats, history, charts, spend, categories, recharts, historial, duplicate

## History (`GET /api/history`)

Paginated archived lists. 20 per page.

```json
{
  "data": [{
    "id", "name", "emoji", "category",
    "items_count": 15,
    "price_total": 42.50,
    "archived_at": "2026-03-15"
  }],
  "total": 45,
  "current_page": 1,
  "last_page": 3
}
```

Price total = `SUM(estimated_price)` from `list_items` (reflects confirmed prices if user ran HU-702).

## Duplicate List (`POST /api/lists/{list}/duplicate`)

Creates a new active list (freemium-gated) cloning all items from the archived source:
- Name: same
- Items: same name/quantity/unit/category, but WITHOUT `is_purchased=true` or `estimated_price`
- Fresh start — all items pending, no price carry-over

Throws `OverflowException` → HTTP 422 if freemium limit reached.

## Statistics (`GET /api/stats`)

```json
{
  "has_enough_data": true,
  "monthly_spend": [
    { "year": 2026, "month": 3, "total": 287.50 },
    ...
  ],
  "top_categories": [
    { "category": "Lácteos y huevos", "total": 95.20 },
    ...
  ],
  "top_products": [
    { "nombre": "Leche entera", "count": 18 },
    ...
  ]
}
```

Gate: `has_enough_data = false` if user has < 3 archived lists. Frontend shows "Necesitas más historial" message.

**Monthly spend**: 6-month window, groups by year+month.
**Top categories**: 5 categories by SUM(estimated_price) in last 6 months.
**Top products**: 10 products by purchase count from `producto_historial` in last 6 months.

## Frontend

- `recharts` library: `BarChart` for monthly spend, `PieChart`/bar for categories
- `recharts` requires explicit `width` or parent container with defined width
- Visualization accessible via `aria-label` attributes (required for accessibility)
