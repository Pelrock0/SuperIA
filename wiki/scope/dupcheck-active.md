# Scope — FEAT-DUPCHECK-ACTIVE

**Complexity:** MEDIUM · **Status:** S6 (cerrada, desplegada en prod 2026-07-19) · **Context:** default (list-items)

## Problema

Al añadir un ítem cuyo nombre coincidía con un ítem **ya comprado** (sección "Ya en el carro"), el sistema mostraba incorrectamente el aviso *"Ya tienes X en la lista"* y proponía "Aumentar cantidad". El check de duplicado debía operar **solo contra ítems pendientes** (`is_purchased = false`).

## Comportamiento implementado

Al añadir un ítem cuyo nombre coincide (normalizado) con uno comprado:

1. **No se dispara** el aviso de duplicado (ni warning ni propuesta de incremento).
2. El backend **elimina** el/los ítem(s) comprado(s) homónimos de la misma lista (misma forma normalizada + misma unidad).
3. El backend **crea** el nuevo ítem pendiente.

Resultado: queda solo el nuevo pendiente. El histórico de compra se preserva en `producto_historial` (registrado al marcar como comprado), así que borrar el `ListItem` comprado no pierde el dato histórico.

## Match singular/plural

El match reconoce variantes singular/plural en español (`pan ↔ panes`, `tomate ↔ tomates`, `lápiz ↔ lápices`) mediante un normalizador determinista **compartido front + back** (`SpanishInflector`). La regla `LOWER(TRIM(name))` anterior era demasiado estricta y el `similarText > 0.80` del frontend fallaba en palabras cortas.

## Alcance

- **Backend:** `ListItemService::create()` y `createOrIncrement()` — borran comprados homónimos dentro de la transacción del add. Nuevo helper `App\Support\Inflector\SpanishInflector`.
- **Frontend:** `AddItemInput.jsx` y `AddItemModal.jsx` — `findDuplicate` filtra `is_purchased=false` + match normalizado. Mirror JS `resources/js/lib/spanishInflector.js`.
- **Sin** migración, **sin** nuevos endpoints, **sin** cambios visuales (`DuplicateWarning` reutilizado).

## Verificación

63 tests backend + 70 frontend (helper + servicio + componentes). Validado end-to-end en navegador (registro real: comprado "Panes" → añadir "pan" → borra y crea). Ver [technical-design/dupcheck-active.md](../technical-design/dupcheck-active.md).

## Known limitations

- Stems singulares terminados en `s` (`bus/buses`, `país/países`) no se normalizan bien (poco habituales en compra).
- `SharedListPage` no pasa `existingItems` → el check no opera en listas compartidas (igual que antes; fuera de scope).
