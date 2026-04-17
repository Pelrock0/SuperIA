import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from './AuthContext';

vi.mock('../lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
    getToken: vi.fn(() => null),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

vi.mock('../lib/webauthnApi', () => ({
    authenticate: vi.fn(),
    markDeviceRegistered: vi.fn(),
}));

import * as webauthnApi from '../lib/webauthnApi';

let capturedAuth;

function Probe() {
    capturedAuth = useAuth();
    return <div data-testid="authed">{capturedAuth.isAuthenticated ? 'yes' : 'no'}</div>;
}

async function setup() {
    render(
        <AuthProvider>
            <Probe />
        </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId('authed')).toHaveTextContent('no'));
}

describe('AuthContext.loginWithPasskey', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        capturedAuth = undefined;
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('calls markDeviceRegistered after successful passkey auth', async () => {
        webauthnApi.authenticate.mockResolvedValueOnce({
            token: 'tok',
            user: { id: 1, name: 'Test', email: 'u@test.com' },
        });

        await setup();
        await capturedAuth.loginWithPasskey(null);

        await waitFor(() => expect(screen.getByTestId('authed')).toHaveTextContent('yes'));
        expect(webauthnApi.markDeviceRegistered).toHaveBeenCalledTimes(1);
    });

    it('does NOT call markDeviceRegistered when passkey auth fails', async () => {
        webauthnApi.authenticate.mockRejectedValueOnce(new Error('cancelled'));

        await setup();
        await expect(capturedAuth.loginWithPasskey(null)).rejects.toThrow(/cancelled/);

        expect(webauthnApi.markDeviceRegistered).not.toHaveBeenCalled();
    });
});
