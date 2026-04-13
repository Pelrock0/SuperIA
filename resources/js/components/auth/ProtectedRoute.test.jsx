import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';

let mockAuth = {};

vi.mock('../../context/AuthContext', () => ({
    useAuth: () => mockAuth,
}));

import ProtectedRoute from './ProtectedRoute';

describe('ProtectedRoute', () => {
    function renderWithRoute(auth) {
        mockAuth = auth;
        return render(
            <MemoryRouter initialEntries={['/protected']}>
                <Routes>
                    <Route element={<ProtectedRoute />}>
                        <Route path="/protected" element={<div>Protected Content</div>} />
                    </Route>
                    <Route path="/login" element={<div>Login Page</div>} />
                </Routes>
            </MemoryRouter>
        );
    }

    it('shows loading when auth is loading', () => {
        renderWithRoute({ isAuthenticated: false, isLoading: true });
        expect(screen.getByTestId('auth-loading')).toBeInTheDocument();
    });

    it('renders children when authenticated', () => {
        renderWithRoute({ isAuthenticated: true, isLoading: false });
        expect(screen.getByText('Protected Content')).toBeInTheDocument();
    });

    it('redirects to login when not authenticated', () => {
        renderWithRoute({ isAuthenticated: false, isLoading: false });
        expect(screen.getByText('Login Page')).toBeInTheDocument();
    });
});
