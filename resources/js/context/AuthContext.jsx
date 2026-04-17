import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { getToken, setToken, removeToken } from '../lib/api';
import { authenticate as webauthnAuthenticate, markDeviceRegistered } from '../lib/webauthnApi';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    const fetchUser = useCallback(async () => {
        const token = getToken();
        if (!token) {
            setUser(null);
            setIsLoading(false);
            return;
        }

        try {
            const response = await api.get('/profile');
            setUser(response.data.data);
        } catch {
            removeToken();
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    const login = async (email, password, remember = false) => {
        const response = await api.post('/auth/login', { email, password, remember });
        const { token, user: userData } = response.data.data;
        setToken(token);
        setUser(userData);
        return userData;
    };

    const loginWithPasskey = async (email = null) => {
        const { user: userData } = await webauthnAuthenticate(email);
        markDeviceRegistered();
        setUser(userData);
        return userData;
    };

    const logout = async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // Ignore errors on logout
        } finally {
            removeToken();
            setUser(null);
        }
    };

    const value = {
        user,
        isAuthenticated: !!user,
        isLoading,
        login,
        loginWithPasskey,
        logout,
        refreshUser: fetchUser,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}

export default AuthContext;
