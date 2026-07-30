import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from "react";
import * as authApi from "@/api/auth";
import type { LoginInput, RegisterInput } from "@/api/auth";
import type { User, UserRole } from "@/types";
import { clearAuthToken, getAuthToken, setAuthToken } from "@/utils/storage";
import { queryClient } from "@/lib/query-client";

interface AuthContextValue {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (input: LoginInput) => Promise<void>;
    register: (input: RegisterInput) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    hasRole: (role: UserRole) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

function applyAuthPayload(
    setUser: (user: User) => void,
    payload: { user: User; token: string },
) {
    setAuthToken(payload.token);
    setUser(payload.user);
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const refreshUser = useCallback(async () => {
        const token = getAuthToken();

        if (!token) {
            setUser(null);
            return;
        }

        const currentUser = await authApi.getCurrentUser();
        setUser(currentUser);
    }, []);

    useEffect(() => {
        let cancelled = false;

        async function bootstrap() {
            try {
                if (getAuthToken()) {
                    const currentUser = await authApi.getCurrentUser();
                    if (!cancelled) {
                        setUser(currentUser);
                    }
                }
            } catch {
                clearAuthToken();
                if (!cancelled) {
                    setUser(null);
                }
            } finally {
                if (!cancelled) {
                    setIsLoading(false);
                }
            }
        }

        void bootstrap();

        const handleUnauthorized = () => {
            setUser(null);
        };

        window.addEventListener("auth:unauthorized", handleUnauthorized);

        return () => {
            cancelled = true;
            window.removeEventListener("auth:unauthorized", handleUnauthorized);
        };
    }, []);

    const login = useCallback(async (input: LoginInput) => {
        const payload = await authApi.login(input);
        queryClient.clear();
        applyAuthPayload(setUser, payload);
    }, []);

    const register = useCallback(async (input: RegisterInput) => {
        const payload = await authApi.register(input);
        queryClient.clear();
        applyAuthPayload(setUser, payload);
    }, []);

    const logout = useCallback(async () => {
        try {
            if (getAuthToken()) {
                await authApi.logout();
            }
        } finally {
            clearAuthToken();
            setUser(null);
            queryClient.clear();
        }
    }, []);

    const hasRole = useCallback(
        (role: UserRole) => {
            return user?.roles?.includes(role) ?? false;
        },
        [user],
    );

    const value = useMemo<AuthContextValue>(
        () => ({
            user,
            isAuthenticated: Boolean(user),
            isLoading,
            login,
            register,
            logout,
            refreshUser,
            hasRole,
        }),
        [user, isLoading, login, register, logout, refreshUser, hasRole],
    );

    return (
        <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error("useAuth must be used within AuthProvider");
    }

    return context;
}
