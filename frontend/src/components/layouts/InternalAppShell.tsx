import React, { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  BarChart3,
  Bell,
  Briefcase,
  Building2,
  CalendarClock,
  ClipboardCheck,
  FileBadge,
  FileSpreadsheet,
  Kanban,
  LayoutDashboard,
  LogOut,
  Menu,
  Settings,
  ShieldCheck,
  UserCheck,
  Users,
  X,
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { notificationsApi } from '../../api/services';

interface NavItem {
  label: string;
  to: string;
  icon: React.ReactNode;
  permission?: string;
  section: 'Recruitment' | 'Analytics' | 'Administration';
}

export const InternalAppShell: React.FC = () => {
  const { user, logout, hasPermission } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [location.pathname]);

  const { data: notifData } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.list(),
    refetchInterval: 30000,
  });

  const navItems: NavItem[] = [
    { label: 'Dashboard', to: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, permission: 'dashboard.view_internal', section: 'Recruitment' },
    { label: 'Job Vacancies', to: '/jobs', icon: <Briefcase className="h-4 w-4" />, permission: 'jobs.view_internal', section: 'Recruitment' },
    { label: 'Requisitions', to: '/requisitions', icon: <ClipboardCheck className="h-4 w-4" />, permission: 'requisitions.view', section: 'Recruitment' },
    { label: 'Candidates', to: '/candidates', icon: <Users className="h-4 w-4" />, permission: 'candidates.view', section: 'Recruitment' },
    { label: 'Applications', to: '/applications', icon: <FileSpreadsheet className="h-4 w-4" />, permission: 'applications.view', section: 'Recruitment' },
    { label: 'Pipeline', to: '/pipeline', icon: <Kanban className="h-4 w-4" />, permission: 'applications.view', section: 'Recruitment' },
    { label: 'Interviews', to: '/interviews', icon: <CalendarClock className="h-4 w-4" />, permission: 'interviews.view', section: 'Recruitment' },
    { label: 'Job Offers', to: '/job-offers', icon: <FileBadge className="h-4 w-4" />, permission: 'offers.view', section: 'Recruitment' },
    { label: 'Reports & Analytics', to: '/reports', icon: <BarChart3 className="h-4 w-4" />, permission: 'reports.view', section: 'Analytics' },
    { label: 'Users', to: '/admin/users', icon: <UserCheck className="h-4 w-4" />, permission: 'users.view', section: 'Administration' },
    { label: 'Roles & Permissions', to: '/admin/roles', icon: <ShieldCheck className="h-4 w-4" />, permission: 'roles.manage', section: 'Administration' },
    { label: 'Departments', to: '/admin/departments', icon: <Building2 className="h-4 w-4" />, permission: 'departments.manage', section: 'Administration' },
    { label: 'Company Settings', to: '/admin/settings', icon: <Settings className="h-4 w-4" />, permission: 'settings.manage', section: 'Administration' },
    { label: 'Activity Logs', to: '/admin/activity-logs', icon: <ClipboardCheck className="h-4 w-4" />, permission: 'audit_logs.view', section: 'Administration' },
  ];

  const visibleItems = navItems.filter((item) => !item.permission || hasPermission(item.permission));
  const sections: Array<'Recruitment' | 'Analytics' | 'Administration'> = ['Recruitment', 'Analytics', 'Administration'];

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const unreadCount = notifData?.unread_count ?? user?.unread_notifications_count ?? 0;

  const renderNav = (onNavigate?: () => void) => (
    <div className="flex h-full flex-col justify-between">
      <div>
        <div className="flex h-14 items-center justify-between border-b border-line px-5">
          <Link to="/dashboard" className="flex items-center gap-2.5" onClick={onNavigate}>
            <div className="flex h-7 w-7 items-center justify-center rounded-md bg-brand text-xs font-bold text-white">
              TF
            </div>
            <div>
              <span className="text-sm font-semibold tracking-tight text-ink">TalentFlow</span>
              <span className="ml-1.5 rounded bg-canvas px-1.5 py-0.5 text-[10px] font-medium text-muted border border-line">
                ATS
              </span>
            </div>
          </Link>
          {onNavigate && (
            <button type="button" onClick={onNavigate} className="text-muted lg:hidden">
              <X className="h-5 w-5" />
            </button>
          )}
        </div>

        <nav className="space-y-5 px-3 py-4">
          {sections.map((section) => {
            const group = visibleItems.filter((i) => i.section === section);
            if (group.length === 0) return null;
            return (
              <div key={section}>
                <div className="mb-1.5 px-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted">
                  {section}
                </div>
                <div className="space-y-0.5">
                  {group.map((item) => (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      onClick={onNavigate}
                      className={({ isActive }) =>
                        `flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium transition-colors ${
                          isActive
                            ? 'bg-brand-subtle text-brand'
                            : 'text-muted hover:bg-canvas hover:text-ink'
                        }`
                      }
                    >
                      {item.icon}
                      <span>{item.label}</span>
                    </NavLink>
                  ))}
                </div>
              </div>
            );
          })}
        </nav>
      </div>

      <div className="border-t border-line p-3">
        <Link
          to="/careers"
          className="mb-2 block rounded-md border border-line bg-canvas px-3 py-2 text-center text-xs font-medium text-ink hover:bg-brand-subtle hover:text-brand transition-colors"
        >
          View Public Careers Board
        </Link>
        <div className="flex items-center justify-between px-2 py-1.5">
          <div className="min-w-0">
            <p className="truncate text-xs font-semibold text-ink">{user?.name}</p>
            <p className="truncate text-[11px] text-muted">{user?.role?.name}</p>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            title="Sign out"
            className="rounded p-1.5 text-muted hover:bg-danger-subtle hover:text-danger transition-colors"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );

  return (
    <div className="flex min-h-screen w-full bg-canvas">
      {/* Desktop Sidebar */}
      <aside className="sticky top-0 hidden h-screen w-60 shrink-0 overflow-y-auto border-r border-line bg-surface lg:block">
        {renderNav()}
      </aside>

      {/* Mobile Drawer */}
      {mobileOpen && (
        <div className="fixed inset-0 z-50 flex bg-ink/40 lg:hidden">
          <div className="h-full w-64 max-w-[80vw] overflow-y-auto bg-surface">
            {renderNav(() => setMobileOpen(false))}
          </div>
          <div className="flex-1" onClick={() => setMobileOpen(false)} />
        </div>
      )}

      {/* Main Content */}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex h-14 w-full items-center justify-between border-b border-line bg-surface px-4 sm:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileOpen(true)}
              className="rounded-md p-1.5 text-muted hover:bg-canvas hover:text-ink lg:hidden"
              aria-label="Open menu"
            >
              <Menu className="h-5 w-5" />
            </button>
            <span className="hidden truncate text-xs font-medium text-muted sm:inline">
              {user?.department ? `${user.department.name} · ` : ''}
              <span className="font-semibold text-ink">{user?.role?.name}</span>
            </span>
          </div>

          <div className="flex shrink-0 items-center gap-3">
            <Link
              to="/notifications"
              className="relative rounded-md p-2 text-muted transition-colors hover:bg-canvas hover:text-ink"
              aria-label="Notifications"
            >
              <Bell className="h-4 w-4" />
              {unreadCount > 0 && (
                <span className="absolute right-1 top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                  {unreadCount > 9 ? '9+' : unreadCount}
                </span>
              )}
            </Link>
            <Link to="/account" className="text-xs font-medium text-muted hover:text-ink">
              Account
            </Link>
          </div>
        </header>

        <main className="w-full min-w-0 flex-1 p-4 sm:p-6">
          <div className="w-full min-w-0">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
};
