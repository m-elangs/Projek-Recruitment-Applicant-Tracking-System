import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatDate, formatSalaryRange, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  ConfirmDialog,
  DataTable,
  ErrorState,
  FilterDropdown,
  Input,
  LoadingState,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';
import type { Job } from '../../types/models';

export const JobsListPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [deleteTarget, setDeleteTarget] = useState<Job | null>(null);

  const { data: departments } = useQuery({
    queryKey: ['departments'],
    queryFn: () => recruitmentApi.listDepartments(),
  });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['jobs', { page, search, status, departmentId }],
    queryFn: () =>
      recruitmentApi.listJobs({
        page,
        search: search || undefined,
        status: status || undefined,
        department_id: departmentId || undefined,
      }),
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'publish' | 'pause' | 'close' }) =>
      recruitmentApi.changeJobStatus(id, action),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['jobs'] });
      showToast('success', 'Vacancy updated', `Job status action (${vars.action}) applied.`);
    },
    onError: (err) => showToast('error', 'Action failed', extractApiError(err).message),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => recruitmentApi.deleteDraftJob(id),
    onSuccess: () => {
      setDeleteTarget(null);
      queryClient.invalidateQueries({ queryKey: ['jobs'] });
      showToast('success', 'Draft deleted', 'Draft job vacancy was removed.');
    },
    onError: (err) => showToast('error', 'Delete failed', extractApiError(err).message),
  });

  return (
    <div>
      <PageHeader
        title="Job Vacancies"
        description="Manage open positions, publication lifecycle, and hiring teams."
        actions={
          hasPermission('jobs.manage') && (
            <Link to="/jobs/new">
              <Button leftIcon={<Plus className="h-4 w-4" />}>Create Vacancy</Button>
            </Link>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onChange={(v) => {
            setSearch(v);
            setPage(1);
          }}
          placeholder="Search by title, code, or location..."
          className="w-full sm:w-72"
        />
        <FilterDropdown
          label="Status"
          value={status}
          onChange={(v) => {
            setStatus(v);
            setPage(1);
          }}
          options={[
            { value: 'draft', label: 'Draft' },
            { value: 'published', label: 'Published' },
            { value: 'paused', label: 'Paused' },
            { value: 'closed', label: 'Closed' },
          ]}
        />
        <FilterDropdown
          label="Department"
          value={departmentId}
          onChange={(v) => {
            setDepartmentId(v);
            setPage(1);
          }}
          options={(departments || []).map((d) => ({ value: d.id, label: d.name }))}
        />
      </div>

      {isLoading ? (
        <LoadingState message="Loading job vacancies..." />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <>
          <DataTable
            data={data?.data || []}
            rowKey={(j) => j.id}
            onRowClick={(j) => navigate(`/jobs/${j.id}`)}
            columns={[
              {
                key: 'title',
                header: 'Position',
                render: (j) => (
                  <div>
                    <div className="font-medium text-ink">{j.title}</div>
                    <div className="text-xs text-muted">
                      {j.code} · {j.location} · {humanizeSlug(j.work_arrangement)}
                    </div>
                  </div>
                ),
              },
              {
                key: 'department',
                header: 'Department',
                render: (j) => <span className="text-xs text-ink">{j.department?.name || '—'}</span>,
              },
              {
                key: 'applications',
                header: 'Applicants',
                render: (j) => (
                  <div className="text-xs">
                    <span className="font-semibold text-ink">{j.applications_count ?? 0}</span>
                    <span className="text-muted"> total ({j.hired_count ?? 0}/{j.headcount} hired)</span>
                  </div>
                ),
              },
              {
                key: 'status',
                header: 'Status',
                render: (j) => <StatusBadge status={j.status} />,
              },
              {
                key: 'closes_at',
                header: 'Deadline',
                render: (j) => <span className="text-xs text-muted">{formatDate(j.closes_at)}</span>,
              },
              {
                key: 'actions',
                header: 'Actions',
                className: 'text-right',
                render: (j) =>
                  hasPermission('jobs.manage') ? (
                    <div className="flex justify-end gap-1.5" onClick={(e) => e.stopPropagation()}>
                      {j.status === 'draft' && (
                        <>
                          <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => statusMutation.mutate({ id: j.id, action: 'publish' })}
                          >
                            Publish
                          </Button>
                          <Button size="sm" variant="ghost" onClick={() => setDeleteTarget(j)} aria-label="Delete draft">
                            <Trash2 className="h-3.5 w-3.5 text-danger" />
                          </Button>
                        </>
                      )}
                      {j.status === 'published' && (
                        <>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => statusMutation.mutate({ id: j.id, action: 'pause' })}
                          >
                            Pause
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => statusMutation.mutate({ id: j.id, action: 'close' })}
                          >
                            Close
                          </Button>
                        </>
                      )}
                      {j.status === 'paused' && (
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => statusMutation.mutate({ id: j.id, action: 'publish' })}
                        >
                          Resume
                        </Button>
                      )}
                    </div>
                  ) : null,
              },
            ]}
          />
          <Pagination meta={data?.meta} onPageChange={setPage} />
        </>
      )}

      <ConfirmDialog
        isOpen={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        isLoading={deleteMutation.isPending}
        title="Delete Draft Vacancy"
        message={`Are you sure you want to delete draft vacancy "${deleteTarget?.title}"?`}
        confirmLabel="Delete Draft"
      />
    </div>
  );
};

export const JobFormPage: React.FC = () => {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();

  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: () => recruitmentApi.listDepartments() });
  const { data: staff } = useQuery({ queryKey: ['assignable-staff'], queryFn: () => recruitmentApi.listAssignableStaff() });
  const { data: existingJob, isLoading: loadingJob } = useQuery({
    queryKey: ['job', id],
    queryFn: () => recruitmentApi.getJob(id!),
    enabled: isEdit,
  });

  const [form, setForm] = useState({
    requisition_id: searchParams.get('requisition_id') || '',
    department_id: '',
    hiring_manager_id: '',
    recruiter_id: '',
    title: searchParams.get('title') || '',
    employment_type: 'full_time',
    work_arrangement: 'hybrid',
    experience_level: 'mid',
    location: searchParams.get('location') || '',
    salary_min: '',
    salary_max: '',
    salary_currency: 'USD',
    is_salary_visible: true,
    headcount: '1',
    closes_at: '',
    description: '',
    requirements: '',
    responsibilities: '',
    benefits: '',
    skills_csv: '',
    status: 'draft',
  });

  useEffect(() => {
    if (existingJob) {
      setForm({
        requisition_id: existingJob.requisition_id ? String(existingJob.requisition_id) : '',
        department_id: existingJob.department ? String(existingJob.department.id) : '',
        hiring_manager_id: existingJob.hiring_manager ? String(existingJob.hiring_manager.id) : '',
        recruiter_id: existingJob.recruiter ? String(existingJob.recruiter.id) : '',
        title: existingJob.title,
        employment_type: existingJob.employment_type,
        work_arrangement: existingJob.work_arrangement,
        experience_level: existingJob.experience_level,
        location: existingJob.location,
        salary_min: existingJob.salary_min ? String(existingJob.salary_min) : '',
        salary_max: existingJob.salary_max ? String(existingJob.salary_max) : '',
        salary_currency: existingJob.salary_currency,
        is_salary_visible: existingJob.is_salary_visible,
        headcount: String(existingJob.headcount),
        closes_at: existingJob.closes_at ? existingJob.closes_at.slice(0, 10) : '',
        description: existingJob.description,
        requirements: existingJob.requirements,
        responsibilities: existingJob.responsibilities || '',
        benefits: existingJob.benefits || '',
        skills_csv: existingJob.skills.map((s) => s.skill_name).join(', '),
        status: existingJob.status,
      });
    }
  }, [existingJob]);

  const mutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      isEdit ? recruitmentApi.updateJob(Number(id), payload) : recruitmentApi.createJob(payload),
    onSuccess: (saved) => {
      queryClient.invalidateQueries({ queryKey: ['jobs'] });
      showToast('success', isEdit ? 'Job updated' : 'Job created', `${saved.code} saved successfully.`);
      navigate(`/jobs/${saved.id}`);
    },
    onError: (err) => showToast('error', 'Save failed', extractApiError(err).message),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const skills = form.skills_csv
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .map((skill_name) => ({ skill_name, is_required: true }));

    mutation.mutate({
      requisition_id: form.requisition_id ? Number(form.requisition_id) : null,
      department_id: Number(form.department_id),
      hiring_manager_id: Number(form.hiring_manager_id),
      recruiter_id: form.recruiter_id ? Number(form.recruiter_id) : undefined,
      title: form.title,
      employment_type: form.employment_type,
      work_arrangement: form.work_arrangement,
      experience_level: form.experience_level,
      location: form.location,
      salary_min: form.salary_min ? Number(form.salary_min) : null,
      salary_max: form.salary_max ? Number(form.salary_max) : null,
      salary_currency: form.salary_currency,
      is_salary_visible: form.is_salary_visible,
      headcount: Number(form.headcount) || 1,
      closes_at: form.closes_at || null,
      description: form.description,
      requirements: form.requirements,
      responsibilities: form.responsibilities || null,
      benefits: form.benefits || null,
      skills,
      status: isEdit ? undefined : form.status,
    });
  };

  if (isEdit && loadingJob) return <LoadingState message="Loading job form..." />;

  return (
    <div>
      <PageHeader
        title={isEdit ? `Edit Job — ${existingJob?.code}` : 'Create Job Vacancy'}
        breadcrumbs={[{ label: 'Jobs', to: '/jobs' }, { label: isEdit ? 'Edit' : 'New Vacancy' }]}
      />

      <form onSubmit={handleSubmit} className="space-y-6 rounded-lg border border-line bg-surface p-6 max-w-4xl">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Job Title"
            required
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            placeholder="e.g. Senior Full Stack Engineer"
          />
          <Select
            label="Department"
            required
            placeholder="Select department"
            value={form.department_id}
            onChange={(e) => setForm({ ...form, department_id: e.target.value })}
            options={(departments || []).map((d) => ({ value: d.id, label: `${d.code} — ${d.name}` }))}
          />
          <Select
            label="Assigned Hiring Manager"
            required
            placeholder="Select Hiring Manager"
            value={form.hiring_manager_id}
            onChange={(e) => setForm({ ...form, hiring_manager_id: e.target.value })}
            options={(staff || []).map((u) => ({ value: u.id, label: `${u.name} (${u.role?.name})` }))}
          />
          <Input
            label="Location"
            required
            value={form.location}
            onChange={(e) => setForm({ ...form, location: e.target.value })}
            placeholder="Jakarta, Indonesia / Remote"
          />
          <Select
            label="Employment Type"
            value={form.employment_type}
            onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
            options={[
              { value: 'full_time', label: 'Full-Time' },
              { value: 'part_time', label: 'Part-Time' },
              { value: 'contract', label: 'Contract' },
              { value: 'internship', label: 'Internship' },
            ]}
          />
          <Select
            label="Work Arrangement"
            value={form.work_arrangement}
            onChange={(e) => setForm({ ...form, work_arrangement: e.target.value })}
            options={[
              { value: 'onsite', label: 'Onsite' },
              { value: 'hybrid', label: 'Hybrid' },
              { value: 'remote', label: 'Remote' },
            ]}
          />
          <Input
            label="Salary Min"
            type="number"
            value={form.salary_min}
            onChange={(e) => setForm({ ...form, salary_min: e.target.value })}
          />
          <Input
            label="Salary Max"
            type="number"
            value={form.salary_max}
            onChange={(e) => setForm({ ...form, salary_max: e.target.value })}
          />
          <Input
            label="Headcount"
            type="number"
            min={1}
            required
            value={form.headcount}
            onChange={(e) => setForm({ ...form, headcount: e.target.value })}
          />
          <Input
            label="Application Deadline"
            type="date"
            value={form.closes_at}
            onChange={(e) => setForm({ ...form, closes_at: e.target.value })}
          />
        </div>

        <Input
          label="Required Skills (comma-separated)"
          placeholder="React, TypeScript, Laravel, MySQL"
          value={form.skills_csv}
          onChange={(e) => setForm({ ...form, skills_csv: e.target.value })}
        />

        <Textarea
          label="Job Description"
          required
          rows={5}
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.target.value })}
        />
        <Textarea
          label="Requirements"
          required
          rows={4}
          value={form.requirements}
          onChange={(e) => setForm({ ...form, requirements: e.target.value })}
        />
        <Textarea
          label="Responsibilities (Optional)"
          rows={3}
          value={form.responsibilities}
          onChange={(e) => setForm({ ...form, responsibilities: e.target.value })}
        />

        <div className="flex flex-wrap items-center justify-between gap-4 border-t border-line pt-4">
          <label className="flex items-center gap-2 text-xs text-ink">
            <input
              type="checkbox"
              checked={form.is_salary_visible}
              onChange={(e) => setForm({ ...form, is_salary_visible: e.target.checked })}
            />
            Display salary range on Public Careers Board
          </label>

          <div className="flex items-center gap-2">
            {!isEdit && (
              <Select
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
                options={[
                  { value: 'draft', label: 'Save as Draft' },
                  { value: 'published', label: 'Publish Immediately' },
                ]}
                className="w-44"
              />
            )}
            <Button type="button" variant="outline" onClick={() => navigate('/jobs')}>
              Cancel
            </Button>
            <Button type="submit" isLoading={mutation.isPending}>
              {isEdit ? 'Save Changes' : 'Create Job'}
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
};

export const JobDetailPage: React.FC = () => {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const { data: job, isLoading, error, refetch } = useQuery({
    queryKey: ['job', id],
    queryFn: () => recruitmentApi.getJob(id!),
  });

  const { data: apps } = useQuery({
    queryKey: ['applications', { job_id: id }],
    queryFn: () => recruitmentApi.listApplications({ job_id: id, per_page: 25 }),
    enabled: Boolean(id),
  });

  const statusMutation = useMutation({
    mutationFn: (action: 'publish' | 'pause' | 'close') => recruitmentApi.changeJobStatus(Number(id), action),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['job', id] });
      showToast('success', 'Status updated', 'Vacancy status changed.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState message="Loading job details..." />;
  if (error || !job) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={job.title}
        description={`${job.code} · ${job.department?.name} · ${job.location}`}
        breadcrumbs={[{ label: 'Jobs', to: '/jobs' }, { label: job.code }]}
        actions={
          <>
            <StatusBadge status={job.status} />
            <Link to={`/pipeline?job_id=${job.id}`}>
              <Button variant="outline" size="sm">
                View Pipeline
              </Button>
            </Link>
            {hasPermission('jobs.manage') && (
              <>
                <Link to={`/jobs/${job.id}/edit`}>
                  <Button variant="outline" size="sm">
                    Edit
                  </Button>
                </Link>
                {job.status !== 'published' && (
                  <Button size="sm" onClick={() => statusMutation.mutate('publish')}>
                    Publish
                  </Button>
                )}
                {job.status === 'published' && (
                  <Button size="sm" variant="secondary" onClick={() => statusMutation.mutate('close')}>
                    Close Vacancy
                  </Button>
                )}
              </>
            )}
          </>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-5 rounded-lg border border-line bg-surface p-6 lg:col-span-2">
          <div>
            <h2 className="text-sm font-semibold text-ink">Description</h2>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.description}</p>
          </div>
          <div className="border-t border-line pt-4">
            <h2 className="text-sm font-semibold text-ink">Requirements</h2>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.requirements}</p>
          </div>
          {job.responsibilities && (
            <div className="border-t border-line pt-4">
              <h2 className="text-sm font-semibold text-ink">Responsibilities</h2>
              <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.responsibilities}</p>
            </div>
          )}
          <div className="border-t border-line pt-4">
            <h2 className="mb-2 text-sm font-semibold text-ink">Required Skills</h2>
            <div className="flex flex-wrap gap-1.5">
              {job.skills.map((s) => (
                <span key={s.skill_name} className="rounded border border-line bg-canvas px-2.5 py-1 text-xs font-medium text-ink">
                  {s.skill_name}
                </span>
              ))}
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div className="rounded-lg border border-line bg-surface p-5 text-sm space-y-2.5">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-muted">Vacancy Details</h3>
            <div className="flex justify-between"><span className="text-muted">Employment</span><span className="font-medium">{humanizeSlug(job.employment_type)}</span></div>
            <div className="flex justify-between"><span className="text-muted">Arrangement</span><span className="font-medium">{humanizeSlug(job.work_arrangement)}</span></div>
            <div className="flex justify-between"><span className="text-muted">Compensation</span><span className="font-medium">{formatSalaryRange(job.salary_min, job.salary_max, job.salary_currency)}</span></div>
            <div className="flex justify-between"><span className="text-muted">Headcount</span><span className="font-medium">{job.hired_count ?? 0} / {job.headcount} hired</span></div>
            <div className="flex justify-between"><span className="text-muted">Deadline</span><span className="font-medium">{formatDate(job.closes_at)}</span></div>
            <div className="flex justify-between"><span className="text-muted">Recruiter</span><span className="font-medium">{job.recruiter?.name || '—'}</span></div>
            <div className="flex justify-between"><span className="text-muted">Hiring Manager</span><span className="font-medium">{job.hiring_manager?.name || '—'}</span></div>
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">
              Recent Applicants ({apps?.meta?.total ?? 0})
            </h3>
            <div className="space-y-2">
              {(apps?.data || []).slice(0, 6).map((a) => (
                <Link
                  key={a.id}
                  to={`/applications/${a.id}`}
                  className="flex items-center justify-between rounded-md border border-line p-2.5 hover:bg-canvas"
                >
                  <div className="min-w-0">
                    <p className="truncate text-xs font-medium text-ink">{a.candidate?.full_name}</p>
                    <p className="text-[11px] text-muted">{a.application_number}</p>
                  </div>
                  <StatusBadge status={a.current_stage?.slug} label={a.current_stage?.name} />
                </Link>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
