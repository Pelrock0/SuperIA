import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    isSupported,
    base64UrlToArrayBuffer,
    arrayBufferToBase64Url,
    probeEnabled,
    resetEnabledCache,
    registerCredential,
    authenticate,
    listCredentials,
    renameCredential,
    deleteCredential,
} from './webauthnApi';
import api from './api';

vi.mock('./api', () => ({
    default: {
        post: vi.fn(),
        get: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    setToken: vi.fn(),
}));

describe('webauthnApi', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        resetEnabledCache();
    });

    describe('isSupported', () => {
        it('returns true when navigator.credentials exists', () => {
            global.window.PublicKeyCredential = function () {};
            global.navigator.credentials = {
                create: vi.fn(),
                get: vi.fn(),
            };
            expect(isSupported()).toBe(true);
        });

        it('returns false when PublicKeyCredential missing', () => {
            delete global.window.PublicKeyCredential;
            expect(isSupported()).toBe(false);
        });
    });

    describe('base64url helpers', () => {
        it('encodes and decodes round-trip', () => {
            const buf = new Uint8Array([1, 2, 3, 4, 5]).buffer;
            const encoded = arrayBufferToBase64Url(buf);
            const decoded = base64UrlToArrayBuffer(encoded);
            expect(new Uint8Array(decoded)).toEqual(new Uint8Array([1, 2, 3, 4, 5]));
        });

        it('strips padding and replaces +/ with -_', () => {
            const buf = new Uint8Array([251, 255]).buffer;
            const encoded = arrayBufferToBase64Url(buf);
            expect(encoded).not.toContain('=');
            expect(encoded).not.toContain('+');
            expect(encoded).not.toContain('/');
        });
    });

    describe('probeEnabled', () => {
        it('returns true on 200 response', async () => {
            api.post.mockResolvedValueOnce({ data: { data: { handle: 'h', options: {} } } });
            const result = await probeEnabled();
            expect(result).toBe(true);
        });

        it('returns false on 404', async () => {
            const err = { response: { status: 404 } };
            api.post.mockRejectedValueOnce(err);
            const result = await probeEnabled();
            expect(result).toBe(false);
        });

        it('caches result between calls', async () => {
            api.post.mockResolvedValueOnce({ data: { data: {} } });
            await probeEnabled();
            await probeEnabled();
            expect(api.post).toHaveBeenCalledTimes(1);
        });

        it('returns true on non-404 errors', async () => {
            api.post.mockRejectedValueOnce({ response: { status: 500 } });
            const result = await probeEnabled();
            expect(result).toBe(true);
        });
    });

    describe('listCredentials', () => {
        it('returns credentials from API', async () => {
            const creds = [{ id: 1, name: 'iPhone' }];
            api.get.mockResolvedValueOnce({ data: { data: creds } });
            const result = await listCredentials();
            expect(result).toEqual(creds);
            expect(api.get).toHaveBeenCalledWith('/profile/webauthn-credentials');
        });
    });

    describe('renameCredential', () => {
        it('calls PATCH with name', async () => {
            api.patch.mockResolvedValueOnce({ data: { data: { id: 1, name: 'Nuevo' } } });
            const result = await renameCredential(1, 'Nuevo');
            expect(api.patch).toHaveBeenCalledWith('/profile/webauthn-credentials/1', { name: 'Nuevo' });
            expect(result.name).toBe('Nuevo');
        });
    });

    describe('deleteCredential', () => {
        it('calls DELETE', async () => {
            api.delete.mockResolvedValueOnce({ data: { data: { message: 'ok' } } });
            await deleteCredential(1);
            expect(api.delete).toHaveBeenCalledWith('/profile/webauthn-credentials/1');
        });
    });

    describe('registerCredential', () => {
        it('throws clear error if not supported', async () => {
            delete global.window.PublicKeyCredential;
            await expect(registerCredential('test')).rejects.toThrow(/no soporta/i);
        });

        it('maps NotAllowedError to cancelation message', async () => {
            global.window.PublicKeyCredential = function () {};
            global.navigator.credentials = {
                create: vi.fn().mockRejectedValue(Object.assign(new Error('x'), { name: 'NotAllowedError' })),
                get: vi.fn(),
            };
            api.post.mockResolvedValueOnce({
                data: { data: { handle: 'h', options: { challenge: 'Y2hhbA', user: { id: 'dWlk' } } } },
            });

            await expect(registerCredential('MyDevice')).rejects.toThrow(/cancelado/i);
        });
    });

    describe('authenticate', () => {
        it('throws clear error if not supported', async () => {
            delete global.window.PublicKeyCredential;
            await expect(authenticate()).rejects.toThrow(/no soporta/i);
        });

        it('posts empty body when no email provided', async () => {
            global.window.PublicKeyCredential = function () {};
            global.navigator.credentials = {
                create: vi.fn(),
                get: vi.fn().mockRejectedValue(Object.assign(new Error('x'), { name: 'NotAllowedError' })),
            };
            api.post.mockResolvedValueOnce({
                data: { data: { handle: 'h', options: { challenge: 'Y2hhbA' } } },
            });

            await expect(authenticate()).rejects.toThrow(/cancelada/i);
            expect(api.post).toHaveBeenCalledWith('/auth/webauthn/authenticate/begin', {});
        });

        it('posts email when provided', async () => {
            global.window.PublicKeyCredential = function () {};
            global.navigator.credentials = {
                create: vi.fn(),
                get: vi.fn().mockRejectedValue(Object.assign(new Error('x'), { name: 'NotAllowedError' })),
            };
            api.post.mockResolvedValueOnce({
                data: { data: { handle: 'h', options: { challenge: 'Y2hhbA' } } },
            });

            await expect(authenticate('user@test.com')).rejects.toThrow(/cancelada/i);
            expect(api.post).toHaveBeenCalledWith('/auth/webauthn/authenticate/begin', { email: 'user@test.com' });
        });
    });
});
