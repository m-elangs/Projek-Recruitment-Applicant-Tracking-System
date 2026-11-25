import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCheck, Plus } from 'lucide-react';
import { adminApi, notificationsApi } from '../../api/services';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatDateTime, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  DataTable,
  Drawer,
  EmptyState,
  ErrorState,
  Input,
  LoadingState,
  Modal,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  StatusBadge,
} from '../../components/ui';

export const UsersPage: React.FC = () => {
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: 'Password123!',
    role_id: '',
    department_id: '',
    job_title: '',
  });

  const { data: roles } = useQuery({ queryKey: ['admin-roles'], queryFn: () => adminApi.listRoles() });
  const { data: departments } = useQuery({ queryKey: ['admin-depts'], queryFn: () => adminApi.listDepartments() });
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-users', search],
    queryFn: () => adminApi.listUsers({ search: search || undefined }),
  });

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => adminApi.createUser(payload),
    onSuccess: () => {
      setDrawerOpen(false);
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      showToast('success', 'User created');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const toggleActiveMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) => adminApi.updateUser(id, { is_active }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      showToast('success', 'User status updated');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  return (
    <div>
      <PageHeader
        title="User Management"
        description="Provision internal accounts, assign roles, and manage access status."
        actions={
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setDrawerOpen(true)}>
            Add Internal User
          </Button>
        }
      />

      <div className="mb-4 max-w-sm">
        <SearchInput value={search} onChange={setSearch} placeholder="Search by name or email..." />
      </div>

      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <DataTable
          data={data?.data || []}
          rowKey={(u) => u.id}
          columns={[
            {
              key: 'user',
              header: 'User',
              render: (u) => (
                <div>
                  <div className="font-medium text-ink">{u.name}</div>
                  <div className="text-xs text-muted">{u.email}</div>
                </div>
              ),
            },
            { key: 'role', header: 'Role', render: (u) => <span className="text-xs font-medium">{u.role?.name}</span> },
            { key: 'dept', header: 'Department', render: (u) => <span className="text-xs text-muted">{u.department?.name || '—'}</span> },
            {
              key: 'status',
              header: 'Status',
              render: (u) => <StatusBadge status={u.is_active ? 'active' : 'closed'} label={u.is_active ? 'Active' : 'Disabled'} />,
            },
            {
              key: 'actions',
              header: 'Actions',
              className: 'text-right',
              render: (u) => (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => toggleActiveMutation.mutate({ id: u.id, is_active: !u.is_active })}
                >
                  {u.is_active ? 'Deactivate' : 'Reactivate'}
                </Button>
              ),
            },
          ]}
        />
      )}

      <Drawer isOpen={drawerOpen} onClose={() => setDrawerOpen(false)} title="Create Internal User">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            createMutation.mutate({
              ...form,
              role_id: Number(form.role_id),
              department_id: form.department_id ? Number(form.department_id) : null,
            });
          }}
          className="space-y-4"
        >
          <Input label="Full Name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input label="Email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input label="Temporary Password" type="password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          <Select
            label="Role"
            required
            placeholder="Select role"
            value={form.role_id}
            onChange={(e) => setForm({ ...form, role_id: e.target.value })}
            options={(roles || []).map((r) => ({ value: r.id, label: r.name }))}
          />
          <Select
            label="Department (Optional)"
            placeholder="None"
            value={form.department_id}
            onChange={(e) => setForm({ ...form, department_id: e.target.value })}
            options={(departments || []).map((d) => ({ value: d.id, label: d.name }))}
          />
          <Input label="Job Title" value={form.job_title} onChange={(e) => setForm({ ...form, job_title: e.target.value })} />
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setDrawerOpen(false)}>Cancel</Button>
            <Button type="submit" isLoading={createMutation.isPending}>Create User</Button>
          </div>
        </form>
      </Drawer>
    </div>
  );
};

export const RolesPermissionsPage: React.FC = () => {
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [checkedIds, setCheckedIds] = useState<number[]>([]);

  const { data: roles, isLoading } = useQuery({ queryKey: ['admin-roles'], queryFn: () => adminApi.listRoles() });
  const { data: permissionsByModule } = useQuery({ queryKey: ['admin-perms'], queryFn: () => adminApi.listPermissions() });

  const activeRole = (roles || []).find((r) => r.id === selectedRoleId) || (roles || [])[0];

  useEffect(() => {
    if (activeRole) {
      setSelectedRoleId(activeRole.id);
      setCheckedIds((activeRole.permissions || []).map((p) => p.id));
    }
  }, [activeRole?.id]);

  const syncMutation = useMutation({
    mutationFn: () => adminApi.syncRolePermissions(activeRole!.id, checkedIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-roles'] });
      showToast('success', 'Permissions saved', `Updated RBAC matrix for ${activeRole?.name}.`);
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState />;

  return (
    <div>
      <PageHeader
        title="Roles & Permissions"
        description="Configure backend-enforced RBAC permission mappings for each role."
        actions={
          activeRole && (
            <Button isLoading={syncMutation.isPending} onClick={() => syncMutation.mutate()}>
              Save Permissions for {activeRole.name}
            </Button>
          )
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <div className="space-y-2">
          {(roles || []).map((r) => (
            <button
              key={r.id}
              type="button"
              onClick={() => {
                setSelectedRoleId(r.id);
                setCheckedIds((r.permissions || []).map((p) => p.id));
              }}
              className={`w-full rounded-lg border p-3 text-left transition-colors ${
                activeRole?.id === r.id
                  ? 'border-brand bg-brand-subtle/60 text-brand'
                  : 'border-line bg-surface text-ink hover:bg-canvas'
              }`}
            >
              <div className="text-sm font-semibold">{r.name}</div>
              <div className="text-xs text-muted">{r.users_count ?? 0} users · {(r.permissions || []).length} permissions</div>
            </button>
          ))}
        </div>

        <div className="space-y-4 lg:col-span-3">
          {permissionsByModule &&
            Object.entries(permissionsByModule).map(([moduleName, perms]) => (
              <div key={moduleName} className="rounded-lg border border-line bg-surface p-4">
                <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">
                  {humanizeSlug(moduleName)}
                </h3>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  {perms.map((p) => (
                    <label key={p.id} className="flex items-start gap-2 rounded border border-line/70 p-2.5 text-xs cursor-pointer hover:bg-canvas">
                      <input
                        type="checkbox"
                        checked={checkedIds.includes(p.id)}
                        onChange={() =>
                          setCheckedIds((prev) =>
                            prev.includes(p.id) ? prev.filter((x) => x !== p.id) : [...prev, p.id]
                          )
                        }
                        className="mt-0.5"
                      />
                      <div>
                        <div className="font-medium text-ink">{p.name}</div>
                        <div className="font-mono text-[11px] text-muted">{p.slug}</div>
                      </div>
                    </label>
                  ))}
                </div>
              </div>
            ))}
        </div>
      </div>
    </div>
  );
};

export const DepartmentsPage: React.FC = () => {
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ code: '', name: '', description: '' });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-depts'],
    queryFn: () => adminApi.listDepartments(),
  });

  const createMutation = useMutation({
    mutationFn: () => adminApi.createDepartment(form),
    onSuccess: () => {
      setModalOpen(false);
      setForm({ code: '', name: '', description: '' });
      queryClient.invalidateQueries({ queryKey: ['admin-depts'] });
      showToast('success', 'Department created');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  return (
    <div>
      <PageHeader
        title="Departments"
        description="Manage organizational departments used across requisitions, vacancies, and reports."
        actions={
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setModalOpen(true)}>
            Add Department
          </Button>
        }
      />

      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <DataTable
          data={data || []}
          rowKey={(d) => d.id}
          columns={[
            { key: 'code', header: 'Code', render: (d) => <span className="font-mono text-xs font-semibold">{d.code}</span> },
            { key: 'name', header: 'Department Name', render: (d) => <span className="font-medium">{d.name}</span> },
            { key: 'head', header: 'Department Head', render: (d) => <span className="text-xs text-muted">{d.head?.name || '—'}</span> },
            { key: 'jobs', header: 'Jobs', render: (d) => d.jobs_count ?? 0 },
            { key: 'status', header: 'Status', render: (d) => <StatusBadge status={d.is_active ? 'active' : 'closed'} label={d.is_active ? 'Active' : 'Inactive'} /> },
          ]}
        />
      )}

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title="Add Department"
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button isLoading={createMutation.isPending} onClick={() => createMutation.mutate()}>Create</Button>
          </>
        }
      >
        <div className="space-y-3">
          <Input label="Code" required placeholder="e.g. DATA" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          <Input label="Name" required placeholder="Data & AI" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        </div>
      </Modal>
    </div>
  );
};

export const CompanySettingsPage: React.FC = () => {
  const { showToast } = useToast();
  const [settings, setSettings] = useState<Record<string, unknown>>({});

  const { data, isLoading } = useQuery({ queryKey: ['admin-settings'], queryFn: () => adminApi.getSettings() });

  useEffect(() => {
    if (data) setSettings(data);
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: () => adminApi.updateSettings(settings),
    onSuccess: () => showToast('success', 'Settings saved', 'Company preferences updated.'),
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState />;

  return (
    <div>
      <PageHeader title="Company Settings" description="Global organization configuration and hiring workflow policies." />
      <form
        onSubmit={(e) => {
          e.preventDefault();
          saveMutation.mutate();
        }}
        className="max-w-xl space-y-4 rounded-lg border border-line bg-surface p-6"
      >
        <Input
          label="Company Name"
          value={String(settings.company_name ?? '')}
          onChange={(e) => setSettings({ ...settings, company_name: e.target.value })}
        />
        <div className="grid grid-cols-2 gap-4">
          <Input
            label="Default Currency"
            maxLength={3}
            value={String(settings.default_currency ?? 'USD')}
            onChange={(e) => setSettings({ ...settings, default_currency: e.target.value.toUpperCase() })}
          />
          <Input
            label="Default Timezone"
            value={String(settings.default_timezone ?? 'Asia/Jakarta')}
            onChange={(e) => setSettings({ ...settings, default_timezone: e.target.value })}
          />
        </div>
        <Input
          label="Careers Portal Headline"
          value={String(settings.careers_headline ?? '')}
          onChange={(e) => setSettings({ ...settings, careers_headline: e.target.value })}
        />
        <label className="flex items-center gap-2 text-xs text-ink pt-1">
          <input
            type="checkbox"
            checked={Boolean(settings.offer_approval_required)}
            onChange={(e) => setSettings({ ...settings, offer_approval_required: e.target.checked })}
          />
          Require Hiring Manager / Admin approval before Recruiters can send Job Offers
        </label>
        <div className="flex justify-end pt-2">
          <Button type="submit" isLoading={saveMutation.isPending}>
            Save Settings
          </Button>
        </div>
      </form>
    </div>
  );
};

export const ActivityLogsPage: React.FC = () => {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['audit-logs', { page, search }],
    queryFn: () => adminApi.listActivityLogs({ page, search: search || undefined }),
  });

  return (
    <div>
      <PageHeader
        title="Audit & Activity Logs"
        description="Immutable security and operational audit trail across all recruitment entities."
      />
      <div className="mb-4 max-w-sm">
        <SearchInput
          value={search}
          onChange={(v) => {
            setSearch(v);
            setPage(1);
          }}
          placeholder="Filter by event type or description..."
        />
      </div>

      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <>
          <DataTable
            data={data?.data || []}
            rowKey={(l) => l.id}
            columns={[
              { key: 'time', header: 'Timestamp', render: (l) => <span className="text-xs text-muted whitespace-nowrap">{formatDateTime(l.created_at)}</span> },
              {
                key: 'actor',
                header: 'Actor',
                render: (l) => (
                  <div className="text-xs">
                    <div className="font-medium text-ink">{l.actor?.name || 'System'}</div>
                    <div className="text-muted">{humanizeSlug(l.actor_role)}</div>
                  </div>
                ),
              },
              { key: 'event', header: 'Event', render: (l) => <span className="font-mono text-xs text-brand">{l.event_type}</span> },
              { key: 'desc', header: 'Description', render: (l) => <span className="text-xs text-ink">{l.description}</span> },
              { key: 'ip', header: 'IP', render: (l) => <span className="font-mono text-[11px] text-muted">{l.ip_address || '—'}</span> },
            ]}
          />
          <Pagination meta={data?.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
};

export const NotificationsPage: React.FC = () => {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: () => notificationsApi.list() });

  const markReadMutation = useMutation({
    mutationFn: (id: string) => notificationsApi.markRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  });

  const markAllMutation = useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  });

  if (isLoading) return <LoadingState />;

  return (
    <div>
      <PageHeader
        title="Notifications"
        description="Real-time updates for applications, interviews, and job offers."
        actions={
          (data?.unread_count ?? 0) > 0 && (
            <Button
              variant="outline"
              size="sm"
              leftIcon={<CheckCheck className="h-4 w-4" />}
              onClick={() => markAllMutation.mutate()}
            >
              Mark All Read
            </Button>
          )
        }
      />

      {!data || data.items.length === 0 ? (
        <EmptyState title="You're all caught up" description="No notifications to display right now." />
      ) : (
        <div className="divide-y divide-line rounded-lg border border-line bg-surface">
          {data.items.map((n) => (
            <div key={n.id} className={`flex items-start justify-between gap-4 p-4 ${!n.read_at ? 'bg-brand-subtle/30' : ''}`}>
              <div>
                <p className="text-sm font-medium text-ink">{n.data.title}</p>
                <p className="mt-0.5 text-xs text-muted">{n.data.message}</p>
                <p className="mt-1 text-[11px] text-muted">{formatDateTime(n.created_at)}</p>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                {n.data.action_url && (
                  <Link to={n.data.action_url}>
                    <Button size="sm" variant="outline">
                      Open
                    </Button>
                  </Link>
                )}
                {!n.read_at && (
                  <Button size="sm" variant="ghost" onClick={() => markReadMutation.mutate(n.id)}>
                    Mark Read
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
