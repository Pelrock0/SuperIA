import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import {
    computeLocalDecision,
    useBiometricPromptDecision,
    PROMPT_COOLDOWN_MS,
} from './useBiometricPromptDecision';
import * as webauthnApi from '../lib/webauthnApi';

const verifiedUser = { id: 1, email: 'u@test.com', email_verified_at: '2026-01-01T00:00:00Z' };
const unverifiedUser = { id: 1, email: 'u@test.com', email_verified_at: null };

function enableWebauthnSupport() {
    global.window.PublicKeyCredential = function () {};
    global.navigator.credentials = {
        create: vi.fn(),
        get: vi.fn(),
    };
}

function disableWebauthnSupport() {
    delete global.window.PublicKeyCredential;
}

describe('computeLocalDecision', () => {
    beforeEach(() => {
        enableWebauthnSupport();
        window.localStorage.clear();
        webauthnApi.resetEnabledCache();
    });

    afterEach(() => {
        disableWebauthnSupport();
    });

    it('returns allow=false when WebAuthn not supported', () => {
        disableWebauthnSupport();
        const result = computeLocalDecision(verifiedUser);
        expect(result.allow).toBe(false);
        expect(result.reason).toBe('unsupported');
    });

    it('returns allow=false when user is null', () => {
        const result = computeLocalDecision(null);
        expect(result.allow).toBe(false);
        expect(result.reason).toBe('email-not-verified');
    });

    it('returns allow=false when email not verified', () => {
        const result = computeLocalDecision(unverifiedUser);
        expect(result.allow).toBe(false);
        expect(result.reason).toBe('email-not-verified');
    });

    it('returns allow=false when device marker present', () => {
        webauthnApi.markDeviceRegistered();
        const result = computeLocalDecision(verifiedUser);
        expect(result.allow).toBe(false);
        expect(result.reason).toBe('device-registered');
    });

    it('returns allow=false when declined within cooldown window', () => {
        const now = new Date('2026-04-17T12:00:00Z').getTime();
        const recent = new Date(now - 5 * 24 * 60 * 60 * 1000).toISOString();
        window.localStorage.setItem('biometric_prompt_declined_at', recent);
        const result = computeLocalDecision(verifiedUser, { now });
        expect(result.allow).toBe(false);
        expect(result.reason).toBe('declined-cooldown');
    });

    it('returns allow=true when declined older than cooldown', () => {
        const now = new Date('2026-04-17T12:00:00Z').getTime();
        const old = new Date(now - PROMPT_COOLDOWN_MS - 1000).toISOString();
        window.localStorage.setItem('biometric_prompt_declined_at', old);
        const result = computeLocalDecision(verifiedUser, { now });
        expect(result.allow).toBe(true);
    });

    it('returns allow=true when no marker and never declined', () => {
        const result = computeLocalDecision(verifiedUser);
        expect(result.allow).toBe(true);
    });
});

describe('useBiometricPromptDecision', () => {
    beforeEach(() => {
        enableWebauthnSupport();
        window.localStorage.clear();
        webauthnApi.resetEnabledCache();
    });

    afterEach(() => {
        disableWebauthnSupport();
        vi.restoreAllMocks();
    });

    it('returns false when local decision denies (no probe)', async () => {
        const probeSpy = vi.spyOn(webauthnApi, 'probeEnabled');
        const { result } = renderHook(() => useBiometricPromptDecision(unverifiedUser));
        expect(result.current).toBe(false);
        expect(probeSpy).not.toHaveBeenCalled();
    });

    it('returns true after successful probe when local allows', async () => {
        vi.spyOn(webauthnApi, 'probeEnabled').mockResolvedValue(true);
        const { result } = renderHook(() => useBiometricPromptDecision(verifiedUser));
        await waitFor(() => expect(result.current).toBe(true));
    });

    it('returns false when probe reports disabled', async () => {
        vi.spyOn(webauthnApi, 'probeEnabled').mockResolvedValue(false);
        const { result } = renderHook(() => useBiometricPromptDecision(verifiedUser));
        await waitFor(() => expect(webauthnApi.probeEnabled).toHaveBeenCalled());
        expect(result.current).toBe(false);
    });

    it('returns false when probe rejects', async () => {
        vi.spyOn(webauthnApi, 'probeEnabled').mockRejectedValue(new Error('network'));
        const { result } = renderHook(() => useBiometricPromptDecision(verifiedUser));
        await waitFor(() => expect(webauthnApi.probeEnabled).toHaveBeenCalled());
        expect(result.current).toBe(false);
    });

    it('ignores async probe result after unmount', async () => {
        let resolveFn;
        vi.spyOn(webauthnApi, 'probeEnabled').mockImplementation(
            () => new Promise((resolve) => { resolveFn = resolve; }),
        );
        const { result, unmount } = renderHook(() => useBiometricPromptDecision(verifiedUser));
        unmount();
        resolveFn(true);
        await new Promise((r) => setTimeout(r, 0));
        expect(result.current).toBe(false);
    });
});
