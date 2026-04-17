import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import BiometricOptInModal from './BiometricOptInModal';
import * as webauthnApi from '../../lib/webauthnApi';

describe('BiometricOptInModal', () => {
    let onClose;

    beforeEach(() => {
        onClose = vi.fn();
        window.localStorage.clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders title, body and both buttons', () => {
        render(<BiometricOptInModal onClose={onClose} />);
        expect(screen.getByText(/Activar biometría en este dispositivo/i)).toBeInTheDocument();
        expect(screen.getByText(/Face ID, Touch ID o huella/i)).toBeInTheDocument();
        expect(screen.getByTestId('biometric-optin-activate')).toBeInTheDocument();
        expect(screen.getByTestId('biometric-optin-dismiss')).toBeInTheDocument();
    });

    it('autofocuses the primary activate button on mount', () => {
        render(<BiometricOptInModal onClose={onClose} />);
        expect(screen.getByTestId('biometric-optin-activate')).toHaveFocus();
    });

    it('calls registerCredential and closes on success', async () => {
        vi.spyOn(webauthnApi, 'registerCredential').mockResolvedValue({ id: 'cred-1' });
        const markSpy = vi.spyOn(webauthnApi, 'markDeviceRegistered');

        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-activate'));

        await waitFor(() => expect(onClose).toHaveBeenCalled());
        expect(markSpy).toHaveBeenCalled();
    });

    it('shows error message on registration failure and does not mark device', async () => {
        vi.spyOn(webauthnApi, 'registerCredential').mockRejectedValue(new Error('Registro cancelado.'));
        const markSpy = vi.spyOn(webauthnApi, 'markDeviceRegistered');

        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-activate'));

        const alert = await screen.findByTestId('biometric-optin-error');
        expect(alert).toHaveTextContent(/Registro cancelado/i);
        expect(markSpy).not.toHaveBeenCalled();
        expect(onClose).not.toHaveBeenCalled();
    });

    it('uses fallback error message when err.message is empty', async () => {
        vi.spyOn(webauthnApi, 'registerCredential').mockRejectedValue({});

        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-activate'));

        const alert = await screen.findByTestId('biometric-optin-error');
        expect(alert).toHaveTextContent(/No se pudo activar/i);
    });

    it('disables activate button and shows "Activando…" while loading', async () => {
        let resolveFn;
        vi.spyOn(webauthnApi, 'registerCredential').mockImplementation(
            () => new Promise((resolve) => { resolveFn = resolve; }),
        );

        render(<BiometricOptInModal onClose={onClose} />);
        const btn = screen.getByTestId('biometric-optin-activate');
        fireEvent.click(btn);

        await waitFor(() => expect(btn).toBeDisabled());
        expect(btn).toHaveTextContent(/Activando/);
        resolveFn({ id: 'c1' });
    });

    it('dismiss button marks declined and calls onClose', () => {
        const markSpy = vi.spyOn(webauthnApi, 'markPromptDeclined');
        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-dismiss'));
        expect(markSpy).toHaveBeenCalled();
        expect(onClose).toHaveBeenCalled();
    });

    it('close X button marks declined and calls onClose', () => {
        const markSpy = vi.spyOn(webauthnApi, 'markPromptDeclined');
        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-close'));
        expect(markSpy).toHaveBeenCalled();
        expect(onClose).toHaveBeenCalled();
    });

    it('clicking backdrop marks declined and calls onClose', () => {
        const markSpy = vi.spyOn(webauthnApi, 'markPromptDeclined');
        render(<BiometricOptInModal onClose={onClose} />);
        const backdrop = screen.getByTestId('biometric-optin-modal');
        fireEvent.click(backdrop);
        expect(markSpy).toHaveBeenCalled();
        expect(onClose).toHaveBeenCalled();
    });

    it('clicking inner dialog does NOT dismiss', () => {
        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByText(/Activar biometría en este dispositivo/i));
        expect(onClose).not.toHaveBeenCalled();
    });

    it('pressing Escape dismisses', () => {
        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onClose).toHaveBeenCalled();
    });

    it('does not dismiss on Escape while loading', async () => {
        let resolveFn;
        vi.spyOn(webauthnApi, 'registerCredential').mockImplementation(
            () => new Promise((resolve) => { resolveFn = resolve; }),
        );
        const markSpy = vi.spyOn(webauthnApi, 'markPromptDeclined');

        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.click(screen.getByTestId('biometric-optin-activate'));
        await waitFor(() => expect(screen.getByTestId('biometric-optin-activate')).toBeDisabled());

        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onClose).not.toHaveBeenCalled();
        expect(markSpy).not.toHaveBeenCalled();
        resolveFn({ id: 'c1' });
    });

    it('ignores non-Escape keys', () => {
        render(<BiometricOptInModal onClose={onClose} />);
        fireEvent.keyDown(document, { key: 'Enter' });
        expect(onClose).not.toHaveBeenCalled();
    });
});
