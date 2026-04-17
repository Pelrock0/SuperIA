import { useEffect, useState } from 'react';
import {
    isSupported as isWebauthnSupported,
    probeEnabled,
    hasDeviceMarker,
    getDeclinedAt,
} from '../lib/webauthnApi';

export const PROMPT_COOLDOWN_MS = 30 * 24 * 60 * 60 * 1000;

export function computeLocalDecision(user, { now = Date.now() } = {}) {
    if (!isWebauthnSupported()) {
        return { allow: false, reason: 'unsupported' };
    }
    if (!user || !user.email_verified_at) {
        return { allow: false, reason: 'email-not-verified' };
    }
    if (hasDeviceMarker()) {
        return { allow: false, reason: 'device-registered' };
    }
    const declinedAt = getDeclinedAt();
    if (declinedAt && now - declinedAt.getTime() < PROMPT_COOLDOWN_MS) {
        return { allow: false, reason: 'declined-cooldown' };
    }
    return { allow: true, reason: 'pending-probe' };
}

export function useBiometricPromptDecision(user) {
    const [shouldShow, setShouldShow] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const local = computeLocalDecision(user);
        if (!local.allow) {
            setShouldShow(false);
            return () => { cancelled = true; };
        }

        probeEnabled()
            .then((enabled) => {
                if (!cancelled) {
                    setShouldShow(Boolean(enabled));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setShouldShow(false);
                }
            });

        return () => { cancelled = true; };
    }, [user]);

    return shouldShow;
}
