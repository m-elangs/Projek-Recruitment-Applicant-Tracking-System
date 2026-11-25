import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { authApi } from '../api/services';
import { TOKEN_STORAGE_KEY } from '../api/client';
import type { User } from '../types/models';

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (payload: { email: string; password: string; remember?: boolean }) => Promise<{ user: User; redirect_to: string }>;
  register: (payload: {
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    location?: string;
    password: string;
    password_confirmation: string;
  }) => Promise<{ user: User; redirect_to: string }>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  hasPermission: (permissionSlug: string) => boolean;
  hasRole: (roles: string | string[]) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const refreshUser = useCallback(async () => {
    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (!token) {
      setUser(null);
      setIsLoading(false);
      return;
    }

    try {
      const current = await authApi.me();
      setUser(current);
    } catch {
      localStorage.removeItem(TOKEN_STORAGE_KEY);
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void refreshUser();
  }, [refreshUser]);

  const login = useCallback(async (payload: { email: string; password: string; remember?: boolean }) => {
    const res = await authApi.login(payload);
    localStorage.setItem(TOKEN_STORAGE_KEY, res.token);
    setUser(res.user);
    return { user: res.user, redirect_to: res.redirect_to };
  }, []);

  const register = useCallback(
    async (payload: {
      first_name: string;
      last_name: string;
      email: string;
      phone?: string;
      location?: string;
      password: string;
      password_confirmation: string;
    }) => {
      const res = await authApi.register(payload);
      localStorage.setItem(TOKEN_STORAGE_KEY, res.token);
      setUser(res.user);
      return { user: res.user, redirect_to: res.redirect_to };
    },
    []
  );

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // Ignore network errors during logout cleanup
    } finally {
      localStorage.removeItem(TOKEN_STORAGE_KEY);
      setUser(null);
    }
  }, []);

  const hasPermission = useCallback(
    (permissionSlug: string) => {
      if (!user) return false;
      if (user.role?.slug === 'system_admin' && !['applications.withdraw_own', 'offers.respond'].includes(permissionSlug)) {
        return true;
      }
      return user.permissions?.includes(permissionSlug) ?? false;
    },
    [user]
  );

  const hasRole = useCallback(
    (roles: string | string[]) => {
      if (!user?.role?.slug) return false;
      const list = Array.isArray(roles) ? roles : [roles];
      return list.includes(user.role.slug);
    },
    [user]
  );

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isLoading,
      isAuthenticated: Boolean(user),
      login,
      register,
      logout,
      refreshUser,
      hasPermission,
      hasRole,
    }),
    [user, isLoading, login, register, logout, refreshUser, hasPermission, hasRole]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}
