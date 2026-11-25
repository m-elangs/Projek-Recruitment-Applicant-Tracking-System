import React, { useEffect } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Bell, LogOut } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { notificationsApi } from '../../api/services';
import { Button } from '../ui';

export const PublicAndCandidateShell: React.FC = () => {
  const { user, isAuthenticated, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isCandidate = user?.role?.slug === 'candidate';

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [location.pathname]);

  const { data: notifData } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.list(),
    enabled: isAuthenticated,
  });

  const unreadCount = notifData?.unread_count ?? 0;

  const handleLogout = async () => {
    await logout();
    navigate('/careers');
  };

  return (
    <div className="flex min-h-screen w-full flex-col bg-canvas">
      <header className="sticky top-0 z-20 w-full border-b border-line bg-surface">
        <div className="mx-auto flex h-15 w-full max-w-screen-2xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <div className="flex items-center gap-6">
            <Link to="/careers" className="flex items-center gap-2">
              <div className="flex h-7 w-7 items-center justify-center rounded-md bg-brand text-xs font-bold text-white">
                TF
              </div>
              <span className="text-sm font-semibold tracking-tight text-ink">TalentFlow Careers</span>
            </Link>

            <nav className="hidden items-center gap-1 md:flex">
              <NavLink
                to="/careers"
                end
                className={({ isActive }) =>
                  `rounded-md px-3 py-1.5 text-sm font-medium ${
                    isActive ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                  }`
                }
              >
                Open Positions
              </NavLink>

              {isCandidate && (
                <>
                  <NavLink
                    to="/portal/dashboard"
                    className={({ isActive }) =>
                      `rounded-md px-3 py-1.5 text-sm font-medium ${
                        isActive ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                      }`
                    }
                  >
                    Dashboard
                  </NavLink>
                  <NavLink
                    to="/portal/applications"
                    className={({ isActive }) =>
                      `rounded-md px-3 py-1.5 text-sm font-medium ${
                        isActive ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                      }`
                    }
                  >
                    My Applications
                  </NavLink>
                  <NavLink
                    to="/portal/interviews"
                    className={({ isActive }) =>
                      `rounded-md px-3 py-1.5 text-sm font-medium ${
                        isActive ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                      }`
                    }
                  >
                    Interviews
                  </NavLink>
                  <NavLink
                    to="/portal/profile"
                    className={({ isActive }) =>
                      `rounded-md px-3 py-1.5 text-sm font-medium ${
                        isActive ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                      }`
                    }
                  >
                    My Profile & CV
                  </NavLink>
                </>
              )}
            </nav>
          </div>

          <div className="flex items-center gap-2.5">
            {!isAuthenticated ? (
              <>
                <Link to="/login">
                  <Button variant="ghost" size="sm">
                    Sign In
                  </Button>
                </Link>
                <Link to="/register">
                  <Button variant="primary" size="sm">
                    Candidate Sign Up
                  </Button>
                </Link>
              </>
            ) : isCandidate ? (
              <>
                <Link
                  to="/portal/notifications"
                  className="relative rounded-md p-2 text-muted hover:bg-canvas hover:text-ink"
                  aria-label="Notifications"
                >
                  <Bell className="h-4 w-4" />
                  {unreadCount > 0 && (
                    <span className="absolute right-1 top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                      {unreadCount}
                    </span>
                  )}
                </Link>
                <span className="hidden text-xs font-medium text-ink sm:inline">{user.name}</span>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="rounded-md p-1.5 text-muted hover:bg-danger-subtle hover:text-danger"
                  title="Sign out"
                >
                  <LogOut className="h-4 w-4" />
                </button>
              </>
            ) : (
              <Link to="/dashboard">
                <Button variant="secondary" size="sm">
                  Back to Internal ATS
                </Button>
              </Link>
            )}
          </div>
        </div>
      </header>

      <main className="mx-auto w-full min-w-0 max-w-screen-2xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <Outlet />
      </main>

      <footer className="border-t border-line bg-surface py-4 text-center text-xs text-muted">
        TalentFlow Recruitment & Applicant Tracking Platform
      </footer>
    </div>
  );
};
