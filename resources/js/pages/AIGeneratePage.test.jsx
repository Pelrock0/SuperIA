import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

vi.mock('../lib/listGenerationApi', () => ({
    generateList: vi.fn(),
    confirmNewList: vi.fn(),
    confirmExistingList: vi.fn(),
}));

vi.mock('../lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('../components/dashboard/SelectListModal', () => ({
    default: ({ lists, onSelect, onCancel }) => (
        <div data-testid="select-list-modal">
            {lists.map((l) => (
                <button key={l.id} onClick={() => onSelect(l.id)} data-testid={`select-list-${l.id}`}>
                    {l.name}
                </button>
            ))}
            <button onClick={onCancel} data-testid="cancel-select">Cancel</button>
        </div>
    ),
}));

import AIGeneratePage from './AIGeneratePage';
import { generateList, confirmNewList, confirmExistingList } from '../lib/listGenerationApi';
import api from '../lib/api';

const mockProducts = [
    { nombre: 'Arroz', cantidad_tipica: 1.0, unidad_tipica: 'kg', categoria: 'otros', reason: 'Base del plato' },
    { nombre: 'Pollo', cantidad_tipica: 1.5, unidad_tipica: 'kg', categoria: 'carnes_pescados', reason: null },
];

const renderPage = () => {
    return render(
        <MemoryRouter>
            <AIGeneratePage />
        </MemoryRouter>,
    );
};

describe('AIGeneratePage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders prompt form with description and people controls', () => {
        renderPage();
        expect(screen.getByTestId('description-input')).toBeInTheDocument();
        expect(screen.getByTestId('people-count')).toHaveTextContent('2');
        expect(screen.getByTestId('generate-button')).toBeInTheDocument();
    });

    it('generate button disabled when description empty', () => {
        renderPage();
        expect(screen.getByTestId('generate-button')).toBeDisabled();
    });

    it('people +/- buttons adjust count', async () => {
        const user = userEvent.setup();
        renderPage();
        expect(screen.getByTestId('people-count')).toHaveTextContent('2');
        await user.click(screen.getByTestId('people-plus'));
        expect(screen.getByTestId('people-count')).toHaveTextContent('3');
        await user.click(screen.getByTestId('people-minus'));
        expect(screen.getByTestId('people-count')).toHaveTextContent('2');
    });

    it('people minus disabled at 1', async () => {
        const user = userEvent.setup();
        renderPage();
        await user.click(screen.getByTestId('people-minus')); // 2→1
        expect(screen.getByTestId('people-minus')).toBeDisabled();
    });

    it('shows loading state during generation', async () => {
        generateList.mockImplementation(() => new Promise(() => {}));
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Paella');
        await user.click(screen.getByTestId('generate-button'));
        expect(screen.getByTestId('generating-loading')).toBeInTheDocument();
    });

    it('renders preview after successful generation', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 2, description_used: 'Paella' } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Paella');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => {
            expect(screen.getByTestId('preview-section')).toBeInTheDocument();
            expect(screen.getByText('Arroz')).toBeInTheDocument();
            expect(screen.getByText('Pollo')).toBeInTheDocument();
        });
    });

    it('shows product count in preview', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 4 } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => {
            expect(screen.getByText('2 productos para 4 personas')).toBeInTheDocument();
        });
    });

    it('removes product from preview', async () => {
        generateList.mockResolvedValue({ products: [...mockProducts], meta: { people: 2 } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByText('Arroz')).toBeInTheDocument());
        await user.click(screen.getByTestId('remove-item-0'));
        expect(screen.queryByText('Arroz')).toBeNull();
        expect(screen.getByText('Pollo')).toBeInTheDocument();
    });

    it('edits quantity inline', async () => {
        generateList.mockResolvedValue({ products: [...mockProducts], meta: { people: 2 } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByTestId('quantity-input-0')).toBeInTheDocument());
        const input = screen.getByTestId('quantity-input-0');
        await user.clear(input);
        await user.type(input, '3');
        expect(input).toHaveValue(3);
    });

    it('shows error on generation failure', async () => {
        generateList.mockRejectedValue({ response: { data: { error: { code: 'GENERATION_FAILED', message: 'Error' } } } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => {
            expect(screen.getByTestId('generation-error')).toBeInTheDocument();
        });
    });

    it('shows rate limit error', async () => {
        generateList.mockRejectedValue({ response: { data: { error: { code: 'GENERATION_LIMIT', message: 'Limite alcanzado' } } } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => {
            expect(screen.getByText('Limite alcanzado')).toBeInTheDocument();
        });
    });

    it('confirm new list shows name input and creates list', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 2 } });
        confirmNewList.mockResolvedValue({ id: 99, name: 'Mi cena' });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByTestId('create-new-list')).toBeInTheDocument());
        await user.click(screen.getByTestId('create-new-list'));
        expect(screen.getByTestId('list-name-input')).toBeInTheDocument();
        await user.type(screen.getByTestId('list-name-input'), 'Mi cena');
        await user.click(screen.getByTestId('confirm-create'));
        await waitFor(() => {
            expect(confirmNewList).toHaveBeenCalledWith(mockProducts, 'Mi cena');
            expect(screen.getByTestId('confirm-success')).toBeInTheDocument();
        });
    });

    it('confirm add to existing opens modal and appends', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 2 } });
        api.get.mockResolvedValue({ data: { data: { active: [{ id: 1, name: 'Compra', emoji: '🛒' }] } } });
        confirmExistingList.mockResolvedValue({ id: 1 });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByTestId('add-to-existing')).toBeInTheDocument());
        await user.click(screen.getByTestId('add-to-existing'));
        await waitFor(() => expect(screen.getByTestId('select-list-modal')).toBeInTheDocument());
        await user.click(screen.getByTestId('select-list-1'));
        await waitFor(() => {
            expect(confirmExistingList).toHaveBeenCalledWith(mockProducts, 1);
        });
    });

    it('shows freemium error on confirm', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 2 } });
        confirmNewList.mockRejectedValue({ response: { data: { error: { code: 'FREEMIUM_LIMIT' } } } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByTestId('create-new-list')).toBeInTheDocument());
        await user.click(screen.getByTestId('create-new-list'));
        await user.type(screen.getByTestId('list-name-input'), 'Test');
        await user.click(screen.getByTestId('confirm-create'));
        await waitFor(() => {
            expect(screen.getByText(/limite de 3 listas/i)).toBeInTheDocument();
        });
    });

    it('regenerate button calls generateList again', async () => {
        generateList.mockResolvedValue({ products: mockProducts, meta: { people: 2 } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByTestId('preview-section')).toBeInTheDocument());
        expect(screen.getByTestId('generate-button')).toHaveTextContent('Regenerar');
        await user.click(screen.getByTestId('generate-button'));
        expect(generateList).toHaveBeenCalledTimes(2);
    });

    it('shows empty state when all products removed', async () => {
        generateList.mockResolvedValue({ products: [mockProducts[0]], meta: { people: 2 } });
        const user = userEvent.setup();
        renderPage();
        await user.type(screen.getByTestId('description-input'), 'Cena');
        await user.click(screen.getByTestId('generate-button'));
        await waitFor(() => expect(screen.getByText('Arroz')).toBeInTheDocument());
        await user.click(screen.getByTestId('remove-item-0'));
        expect(screen.getByTestId('empty-preview')).toBeInTheDocument();
    });
});
