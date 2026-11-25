import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatSalaryRange } from '../../lib/formatters';
import {
  Button,
  DataTable,
  Drawer,
  ErrorState,
  Input,
  LoadingState,
  Modal,
  PageHeader,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';
import type { JobRequisition } from '../../types/models';

export const RequisitionsPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<JobRequisition | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const [form, setForm] = useState({
    department_id: '',
    title: '',
    headcount: '1',
    employment_type: 'full_time',
    work_arrangement: 'hybrid',
    location: '',
    budget_min: '',
    budget_max: '',
    priority: 'medium',
    target_start_date: '',
    justification: '',
    requirements: '',
  });

  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: () => recruitmentApi.listDepartments() });
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['requisitions'],
    queryFn: () => recruitmentApi.listRequisitions(),
  });

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => recruitmentApi.createRequisition(payload),
    onSuccess: () => {
      setDrawerOpen(false);
      queryClient.invalidateQueries({ queryKey: ['requisitions'] });
      showToast('success', 'Requisition submitted', 'Hiring request has been routed for approval.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const decisionMutation = useMutation({
    mutationFn: ({ id, decision, reason }: { id: number; decision: 'approved' | 'rejected'; reason?: string }) =>
      recruitmentApi.decideRequisition(id, decision, reason),
    onSuccess: (_, vars) => {
      setRejectTarget(null);
      setRejectReason('');
      queryClient.invalidateQueries({ queryKey: ['requisitions'] });
      showToast('success', `Requisition ${vars.decision}`, 'Status updated.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createMutation.mutate({
      ...form,
      department_id: Number(form.department_id),
      headcount: Number(form.headcount),
      budget_min: form.budget_min ? Number(form.budget_min) : null,
      budget_max: form.budget_max ? Number(form.budget_max) : null,
      target_start_date: form.target_start_date || null,
    });
  };

  return (
    <div>
      <PageHeader
        title="Job Requisitions"
        description="Submit, review, and approve hiring requests before opening vacancies."
        actions={
          hasPermission('requisitions.create') && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setDrawerOpen(true)}>
              New Hiring Request
            </Button>
          )
        }
      />

      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <DataTable
          data={data?.data || []}
          rowKey={(r) => r.id}
          columns={[
            {
              key: 'title',
              header: 'Requisition',
              render: (r) => (
                <div>
                  <div className="font-medium text-ink">{r.title}</div>
                  <div className="text-xs text-muted">
                    {r.requisition_code} · {r.department?.name} · Requested by {r.requester?.name}
                  </div>
                </div>
              ),
            },
            { key: 'hc', header: 'Headcount', render: (r) => <span className="text-xs font-medium">{r.headcount}</span> },
            {
              key: 'budget',
              header: 'Budget',
              render: (r) => <span className="text-xs text-muted">{formatSalaryRange(r.budget_min, r.budget_max, r.currency)}</span>,
            },
            { key: 'priority', header: 'Priority', render: (r) => <StatusBadge status={r.priority} /> },
            { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
            {
              key: 'actions',
              header: 'Actions',
              className: 'text-right',
              render: (r) => (
                <div className="flex justify-end gap-1.5">
                  {r.status === 'pending_approval' && hasPermission('requisitions.approve') && (
                    <>
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => decisionMutation.mutate({ id: r.id, decision: 'approved' })}
                      >
                        Approve
                      </Button>
                      <Button size="sm" variant="outline" onClick={() => setRejectTarget(r)}>
                        Reject
                      </Button>
                    </>
                  )}
                  {r.status === 'approved' && hasPermission('jobs.manage') && (
                    <Link
                      to={`/jobs/new?requisition_id=${r.id}&title=${encodeURIComponent(r.title)}&location=${encodeURIComponent(r.location)}`}
                    >
                      <Button size="sm" variant="outline">
                        Convert to Job
                      </Button>
                    </Link>
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
        title="Submit Job Requisition"
        description="Provide headcount, budget, and business justification."
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input label="Position Title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          <Select
            label="Department"
            required
            placeholder="Select department"
            value={form.department_id}
            onChange={(e) => setForm({ ...form, department_id: e.target.value })}
            options={(departments || []).map((d) => ({ value: d.id, label: d.name }))}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Headcount"
              type="number"
              min={1}
              required
              value={form.headcount}
              onChange={(e) => setForm({ ...form, headcount: e.target.value })}
            />
            <Select
              label="Priority"
              value={form.priority}
              onChange={(e) => setForm({ ...form, priority: e.target.value })}
              options={[
                { value: 'low', label: 'Low' },
                { value: 'medium', label: 'Medium' },
                { value: 'high', label: 'High' },
                { value: 'urgent', label: 'Urgent' },
              ]}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input label="Location" required value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
            <Input
              label="Target Start Date"
              type="date"
              value={form.target_start_date}
              onChange={(e) => setForm({ ...form, target_start_date: e.target.value })}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input label="Budget Min (USD)" type="number" value={form.budget_min} onChange={(e) => setForm({ ...form, budget_min: e.target.value })} />
            <Input label="Budget Max (USD)" type="number" value={form.budget_max} onChange={(e) => setForm({ ...form, budget_max: e.target.value })} />
          </div>
          <Textarea
            label="Business Justification"
            required
            rows={4}
            value={form.justification}
            onChange={(e) => setForm({ ...form, justification: e.target.value })}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createMutation.isPending}>
              Submit Request
            </Button>
          </div>
        </form>
      </Drawer>

      <Modal
        isOpen={Boolean(rejectTarget)}
        onClose={() => setRejectTarget(null)}
        title="Reject Job Requisition"
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setRejectTarget(null)}>
              Cancel
            </Button>
            <Button
              variant="danger"
              isLoading={decisionMutation.isPending}
              onClick={() =>
                rejectTarget && decisionMutation.mutate({ id: rejectTarget.id, decision: 'rejected', reason: rejectReason })
              }
            >
              Reject Request
            </Button>
          </>
        }
      >
        <Textarea
          label="Rejection Reason"
          required
          rows={3}
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
        />
      </Modal>
    </div>
  );
};
