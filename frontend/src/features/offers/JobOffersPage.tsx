import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatCurrency, formatDate } from '../../lib/formatters';
import {
  Button,
  DataTable,
  Drawer,
  ErrorState,
  FilterDropdown,
  Input,
  LoadingState,
  PageHeader,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';

export const JobOffersPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(Boolean(searchParams.get('create_for')));
  const [form, setForm] = useState({
    application_id: searchParams.get('create_for') || '',
    base_salary: '',
    bonus_amount: '',
    salary_currency: 'USD',
    pay_frequency: 'annual',
    proposed_start_date: '',
    expires_at: '',
    benefits_summary: '',
    send_immediately: false,
  });

  useEffect(() => {
    const prefill = searchParams.get('create_for');
    if (prefill) {
      setForm((prev) => ({ ...prev, application_id: prefill }));
      setDrawerOpen(true);
    }
  }, [searchParams]);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['job-offers', statusFilter],
    queryFn: () => recruitmentApi.listOffers({ status: statusFilter || undefined }),
  });

  const { data: activeApps } = useQuery({
    queryKey: ['applications', 'active-for-offer'],
    queryFn: () => recruitmentApi.listApplications({ is_active: true, per_page: 50 }),
    enabled: drawerOpen,
  });

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => recruitmentApi.createOffer(payload),
    onSuccess: () => {
      setDrawerOpen(false);
      queryClient.invalidateQueries({ queryKey: ['job-offers'] });
      showToast('success', 'Job offer created');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const actionMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'approve' | 'send' | 'revoke' }) => {
      if (action === 'approve') return recruitmentApi.approveOffer(id);
      if (action === 'send') return recruitmentApi.sendOffer(id);
      return recruitmentApi.revokeOffer(id);
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['job-offers'] });
      showToast('success', `Offer ${vars.action}d`, 'Job offer status updated.');
    },
    onError: (err) => showToast('error', 'Action failed', extractApiError(err).message),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createMutation.mutate({
      ...form,
      application_id: Number(form.application_id),
      base_salary: Number(form.base_salary),
      bonus_amount: form.bonus_amount ? Number(form.bonus_amount) : null,
    });
  };

  return (
    <div>
      <PageHeader
        title="Job Offers"
        description="Compose, approve, issue, and track candidate offer responses."
        actions={
          hasPermission('offers.manage') && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setDrawerOpen(true)}>
              Create Offer
            </Button>
          )
        }
      />

      <div className="mb-4">
        <FilterDropdown
          label="Status"
          value={statusFilter}
          onChange={setStatusFilter}
          options={[
            { value: 'draft', label: 'Draft' },
            { value: 'pending_approval', label: 'Pending Approval' },
            { value: 'sent', label: 'Sent' },
            { value: 'accepted', label: 'Accepted' },
            { value: 'declined', label: 'Declined' },
            { value: 'expired', label: 'Expired' },
          ]}
        />
      </div>

      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <DataTable
          data={data?.data || []}
          rowKey={(o) => o.id}
          columns={[
            {
              key: 'offer',
              header: 'Offer & Candidate',
              render: (o) => (
                <div>
                  <div className="font-medium text-ink">
                    {o.application?.candidate?.first_name} {o.application?.candidate?.last_name}
                  </div>
                  <div className="text-xs text-muted">
                    {o.offer_number} · {o.position_title}
                  </div>
                </div>
              ),
            },
            {
              key: 'comp',
              header: 'Compensation',
              render: (o) => (
                <div className="text-xs">
                  <div className="font-medium text-ink">
                    {formatCurrency(o.base_salary, o.salary_currency)} / {o.pay_frequency}
                  </div>
                  {o.bonus_amount && <div className="text-muted">+ {formatCurrency(o.bonus_amount, o.salary_currency)} bonus</div>}
                </div>
              ),
            },
            {
              key: 'dates',
              header: 'Start / Expires',
              render: (o) => (
                <div className="text-xs text-muted">
                  <div>Start: {formatDate(o.proposed_start_date)}</div>
                  <div>Exp: {formatDate(o.expires_at)}</div>
                </div>
              ),
            },
            { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
            {
              key: 'actions',
              header: 'Actions',
              className: 'text-right',
              render: (o) => (
                <div className="flex justify-end gap-1.5">
                  {o.status === 'pending_approval' && hasPermission('offers.approve') && (
                    <Button size="sm" variant="secondary" onClick={() => actionMutation.mutate({ id: o.id, action: 'approve' })}>
                      Approve
                    </Button>
                  )}
                  {o.status === 'draft' && hasPermission('offers.manage') && (
                    <Button size="sm" onClick={() => actionMutation.mutate({ id: o.id, action: 'send' })}>
                      Send Offer
                    </Button>
                  )}
                  {['draft', 'pending_approval', 'sent'].includes(o.status) && hasPermission('offers.manage') && (
                    <Button size="sm" variant="outline" onClick={() => actionMutation.mutate({ id: o.id, action: 'revoke' })}>
                      Revoke
                    </Button>
                  )}
                </div>
              ),
            },
          ]}
        />
      )}

      <Drawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Compose Job Offer"
        description="Sending an offer transitions the candidate to Offered and notifies them in the Candidate Portal."
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Select
            label="Candidate Application"
            required
            placeholder="Select active application"
            value={form.application_id}
            onChange={(e) => setForm({ ...form, application_id: e.target.value })}
            options={(activeApps?.data || []).map((a) => ({
              value: a.id,
              label: `${a.candidate?.full_name} — ${a.job?.title}`,
            }))}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Base Salary"
              type="number"
              required
              value={form.base_salary}
              onChange={(e) => setForm({ ...form, base_salary: e.target.value })}
            />
            <Input
              label="Bonus (Optional)"
              type="number"
              value={form.bonus_amount}
              onChange={(e) => setForm({ ...form, bonus_amount: e.target.value })}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Currency"
              maxLength={3}
              value={form.salary_currency}
              onChange={(e) => setForm({ ...form, salary_currency: e.target.value.toUpperCase() })}
            />
            <Select
              label="Pay Frequency"
              value={form.pay_frequency}
              onChange={(e) => setForm({ ...form, pay_frequency: e.target.value })}
              options={[
                { value: 'annual', label: 'Annual' },
                { value: 'monthly', label: 'Monthly' },
              ]}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Proposed Start Date"
              type="date"
              required
              value={form.proposed_start_date}
              onChange={(e) => setForm({ ...form, proposed_start_date: e.target.value })}
            />
            <Input
              label="Offer Expiration"
              type="datetime-local"
              required
              value={form.expires_at}
              onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
            />
          </div>
          <Textarea
            label="Benefits Summary"
            rows={3}
            value={form.benefits_summary}
            onChange={(e) => setForm({ ...form, benefits_summary: e.target.value })}
          />
          <label className="flex items-center gap-2 text-xs text-ink">
            <input
              type="checkbox"
              checked={form.send_immediately}
              onChange={(e) => setForm({ ...form, send_immediately: e.target.checked })}
            />
            Send immediately to candidate (when approval is not required)
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createMutation.isPending}>
              Save Offer
            </Button>
          </div>
        </form>
      </Drawer>
    </div>
  );
};
