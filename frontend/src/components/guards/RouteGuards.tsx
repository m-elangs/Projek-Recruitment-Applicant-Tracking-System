import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { LoadingState } from '../ui';

export const ProtectedRoute: React.FC = () => {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-canvas p-6">
        <div className="w-full max-w-sm">
          <LoadingState message="Verifying your session..." />
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  }

  return <Outlet />;
};

export const GuestOnlyRoute: React.FC = () => {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-canvas p-6">
        <div className="w-full max-w-sm">
          <LoadingState message="Loading..." />
        </div>
      </div>
    );
  }

  if (isAuthenticated && user) {
    return <Navigate to={user.redirect_to || '/dashboard'} replace />;
  }

  return <Outlet />;
};

export const InternalOnlyRoute: React.FC = () => {
  const { user } = useAuth();

  if (user?.role?.slug === 'candidate') {
    return <Navigate to="/portal/dashboard" replace />;
  }

  return <Outlet />;
};

export const CandidateOnlyRoute: React.FC = () => {
  const { user } = useAuth();

  if (user && user.role?.slug !== 'candidate') {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
};

export const PermissionRoute: React.FC<{ permission: string }> = ({ permission }) => {
  const { hasPermission } = useAuth();

  if (!hasPermission(permission)) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
};
