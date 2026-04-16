import api, { setToken } from './api';

// --- Capability ---

export function isSupported() {
    return typeof window !== 'undefined'
        && typeof window.PublicKeyCredential !== 'undefined'
        && typeof navigator !== 'undefined'
        && typeof navigator.credentials?.create === 'function'
        && typeof navigator.credentials?.get === 'function';
}

let _enabledCache = null;

export async function probeEnabled() {
    if (_enabledCache !== null) {
        return _enabledCache;
    }
    try {
        await api.post('/auth/webauthn/authenticate/begin', {});
        _enabledCache = true;
    } catch (err) {
        if (err.response?.status === 404) {
            _enabledCache = false;
        } else {
            _enabledCache = true;
        }
    }
    return _enabledCache;
}

export function resetEnabledCache() {
    _enabledCache = null;
}

// --- base64url helpers ---

export function base64UrlToArrayBuffer(base64Url) {
    const padded = base64Url.padEnd(base64Url.length + ((4 - (base64Url.length % 4)) % 4), '=');
    const base64 = padded.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

export function arrayBufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function decodeCreationOptions(options) {
    return {
        ...options,
        challenge: base64UrlToArrayBuffer(options.challenge),
        user: {
            ...options.user,
            id: base64UrlToArrayBuffer(options.user.id),
        },
        excludeCredentials: (options.excludeCredentials || []).map((c) => ({
            ...c,
            id: base64UrlToArrayBuffer(c.id),
        })),
    };
}

function decodeRequestOptions(options) {
    return {
        ...options,
        challenge: base64UrlToArrayBuffer(options.challenge),
        allowCredentials: (options.allowCredentials || []).map((c) => ({
            ...c,
            id: base64UrlToArrayBuffer(c.id),
        })),
    };
}

function encodeAttestationCredential(credential) {
    return {
        id: credential.id,
        rawId: arrayBufferToBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: arrayBufferToBase64Url(credential.response.clientDataJSON),
            attestationObject: arrayBufferToBase64Url(credential.response.attestationObject),
            transports: typeof credential.response.getTransports === 'function'
                ? credential.response.getTransports()
                : [],
        },
        clientExtensionResults: credential.getClientExtensionResults?.() || {},
    };
}

function encodeAssertionCredential(credential) {
    return {
        id: credential.id,
        rawId: arrayBufferToBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: arrayBufferToBase64Url(credential.response.clientDataJSON),
            authenticatorData: arrayBufferToBase64Url(credential.response.authenticatorData),
            signature: arrayBufferToBase64Url(credential.response.signature),
            userHandle: credential.response.userHandle
                ? arrayBufferToBase64Url(credential.response.userHandle)
                : null,
        },
        clientExtensionResults: credential.getClientExtensionResults?.() || {},
    };
}

// --- Registration ---

export async function registerCredential(name) {
    if (!isSupported()) {
        throw new Error('Tu dispositivo no soporta biometria.');
    }

    const beginResp = await api.post('/auth/webauthn/register/begin');
    const { handle, options } = beginResp.data.data;

    const publicKey = decodeCreationOptions(options);

    let credential;
    try {
        credential = await navigator.credentials.create({ publicKey });
    } catch (err) {
        if (err?.name === 'NotAllowedError') {
            throw new Error('Registro cancelado.');
        }
        throw new Error('No se pudo registrar el dispositivo. Intentalo de nuevo.');
    }

    if (!credential) {
        throw new Error('No se recibio credencial del navegador.');
    }

    const completeResp = await api.post('/auth/webauthn/register/complete', {
        handle,
        name,
        credential: encodeAttestationCredential(credential),
    });

    return completeResp.data.data;
}

// --- Authentication ---

export async function authenticate(email = null) {
    if (!isSupported()) {
        throw new Error('Tu dispositivo no soporta biometria.');
    }

    const body = email ? { email } : {};
    const beginResp = await api.post('/auth/webauthn/authenticate/begin', body);
    const { handle, options } = beginResp.data.data;

    const publicKey = decodeRequestOptions(options);

    let assertion;
    try {
        assertion = await navigator.credentials.get({ publicKey });
    } catch (err) {
        if (err?.name === 'NotAllowedError') {
            throw new Error('Autenticacion cancelada.');
        }
        throw new Error('No se pudo autenticar con biometria.');
    }

    if (!assertion) {
        throw new Error('No se recibio respuesta del navegador.');
    }

    const completeResp = await api.post('/auth/webauthn/authenticate/complete', {
        handle,
        credential: encodeAssertionCredential(assertion),
    });

    const { token, user } = completeResp.data.data;
    setToken(token);

    return { token, user };
}

// --- Credential management ---

export async function listCredentials() {
    const resp = await api.get('/profile/webauthn-credentials');
    return resp.data.data;
}

export async function renameCredential(id, name) {
    const resp = await api.patch(`/profile/webauthn-credentials/${id}`, { name });
    return resp.data.data;
}

export async function deleteCredential(id) {
    await api.delete(`/profile/webauthn-credentials/${id}`);
}
