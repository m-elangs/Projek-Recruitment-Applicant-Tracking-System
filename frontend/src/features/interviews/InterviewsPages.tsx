import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarPlus, ExternalLink, MapPin, Video } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatDateTime, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  DataTable,
  Drawer,
  ErrorState,
  FilterDropdown,
  Input,
  LoadingState,
  Modal,
  PageHeader,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';

export const InterviewCalendarPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(Boolean(searchParams.get('schedule_for')));

  const [form, setForm] = useState({
    application_id: searchParams.get('schedule_for') || '',
    title: 'Technical & System Design Interview',
    interview_type: 'technical',
    mode: 'online',
    scheduled_at: '',
    duration_minutes: '60',
    meeting_url: '',
    location: '',
    candidate_instructions: '',
    interviewer_ids: [] as number[],
  });

  useEffect(() => {
    const prefill = searchParams.get('schedule_for');
    if (prefill) {
      setForm((prev) => ({ ...prev, application_id: prefill }));
      setDrawerOpen(true);
    }
  }, [searchParams]);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['interviews', statusFilter],
    queryFn: () => recruitmentApi.listInterviews({ status: statusFilter || undefined }),
  });

  const { data: activeApps } = useQuery({
    queryKey: ['applications', 'active-for-interview'],
    queryFn: () => recruitmentApi.listApplications({ is_active: true, per_page: 50 }),
    enabled: drawerOpen,
  });

  const { data: staff } = useQuery({
    queryKey: ['assignable-staff'],
    queryFn: () => recruitmentApi.listAssignableStaff(),
    enabled: drawerOpen,
  });

  const scheduleMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => recruitmentApi.scheduleInterview(payload),
    onSuccess: (created) => {
      setDrawerOpen(false);
      queryClient.invalidateQueries({ queryKey: ['interviews'] });
      showToast('success', 'Interview scheduled', 'Candidate and interviewers have been notified.');
      navigate(`/interviews/${created.id}`);
    },
    onError: (err) => showToast('error', 'Scheduling failed', extractApiError(err).message),
  });

  const toggleInterviewer = (uid: number) => {
    setForm((prev) => ({
      ...prev,
      interviewer_ids: prev.interviewer_ids.includes(uid)
        ? prev.interviewer_ids.filter((x) => x !== uid)
        : [...prev.interviewer_ids, uid],
    }));
  };

  const handleSchedule = (e: React.FormEvent) => {
    e.preventDefault();
    scheduleMutation.mutate({
      ...form,
      application_id: Number(form.application_id),
      duration_minutes: Number(form.duration_minutes),
    });
  };

  return (
    <div>
      <PageHeader
        title="Interview Schedule"
        description="Coordinate online and onsite interviews, panel assignments, and evaluation rubrics."
        actions={
          hasPermission('interviews.manage') && (
            <Button leftIcon={<CalendarPlus className="h-4 w-4" />} onClick={() => setDrawerOpen(true)}>
              Schedule Interview
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
            { value: 'scheduled', label: 'Scheduled' },
            { value: 'rescheduled', label: 'Rescheduled' },
            { value: 'completed', label: 'Completed' },
            { value: 'cancelled', label: 'Cancelled' },
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
          rowKey={(i) => i.id}
          onRowClick={(i) => navigate(`/interviews/${i.id}`)}
          columns={[
            {
              key: 'title',
              header: 'Interview Session',
              render: (i) => (
                <div>
                  <div className="font-medium text-ink">
                    R{i.round_number} · {i.title}
                  </div>
                  <div className="text-xs text-muted">
                    {i.application?.candidate?.first_name} {i.application?.candidate?.last_name} · {i.application?.job?.title}
                  </div>
                </div>
              ),
            },
            {
              key: 'when',
              header: 'Date & Time',
              render: (i) => (
                <div className="text-xs">
                  <div className="font-medium text-ink">{formatDateTime(i.scheduled_at)}</div>
                  <div className="text-muted">{i.duration_minutes} mins ({i.timezone})</div>
                </div>
              ),
            },
            {
              key: 'mode',
              header: 'Mode',
              render: (i) => (
                <span className="inline-flex items-center gap-1 text-xs text-muted">
                  {i.mode === 'online' ? <Video className="h-3.5 w-3.5 text-brand" /> : <MapPin className="h-3.5 w-3.5 text-brand" />}
                  {humanizeSlug(i.mode)}
                </span>
              ),
            },
            {
              key: 'panel',
              header: 'Panel',
              render: (i) => (
                <span className="text-xs text-muted">
                  {(i.panel_users || []).map((u) => u.name).join(', ') || '—'}
                </span>
              ),
            },
            { key: 'status', header: 'Status', render: (i) => <StatusBadge status={i.status} /> },
          ]}
        />
      )}

      <Drawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Schedule New Interview"
        description="Assign interviewers and choose online meeting URL or onsite room."
      >
        <form onSubmit={handleSchedule} className="space-y-4">
          <Select
            label="Active Application"
            required
            placeholder="Select candidate application"
            value={form.application_id}
            onChange={(e) => setForm({ ...form, application_id: e.target.value })}
            options={(activeApps?.data || []).map((a) => ({
              value: a.id,
              label: `${a.candidate?.full_name} — ${a.job?.title} (${a.current_stage?.name})`,
            }))}
          />
          <Input label="Session Title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          <div className="grid grid-cols-2 gap-3">
            <Select
              label="Interview Type"
              value={form.interview_type}
              onChange={(e) => setForm({ ...form, interview_type: e.target.value })}
              options={[
                { value: 'screening', label: 'Screening' },
                { value: 'technical', label: 'Technical' },
                { value: 'behavioral', label: 'Behavioral' },
                { value: 'panel', label: 'Panel' },
                { value: 'final', label: 'Final' },
              ]}
            />
            <Select
              label="Mode"
              value={form.mode}
              onChange={(e) => setForm({ ...form, mode: e.target.value })}
              options={[
                { value: 'online', label: 'Online' },
                { value: 'onsite', label: 'Onsite' },
              ]}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Date & Time"
              type="datetime-local"
              required
              value={form.scheduled_at}
              onChange={(e) => setForm({ ...form, scheduled_at: e.target.value })}
            />
            <Input
              label="Duration (minutes)"
              type="number"
              min={15}
              max={480}
              required
              value={form.duration_minutes}
              onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })}
            />
          </div>
          {form.mode === 'online' ? (
            <Input
              label="Meeting URL"
              type="url"
              required
              placeholder="https://meet.google.com/..."
              value={form.meeting_url}
              onChange={(e) => setForm({ ...form, meeting_url: e.target.value })}
            />
          ) : (
            <Input
              label="Physical Location"
              required
              placeholder="HQ Tower Floor 12, Room A"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
            />
          )}

          <div>
            <label className="mb-1.5 block text-xs font-medium text-ink">
              Assign Interviewers <span className="text-danger">*</span>
            </label>
            <div className="max-h-40 space-y-1.5 overflow-y-auto rounded-md border border-line p-2.5">
              {(staff || []).map((u) => (
                <label key={u.id} className="flex items-center gap-2 text-xs text-ink cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.interviewer_ids.includes(u.id)}
                    onChange={() => toggleInterviewer(u.id)}
                  />
                  <span>
                    {u.name} <span className="text-muted">({u.role?.name})</span>
                  </span>
                </label>
              ))}
            </div>
          </div>

          <Textarea
            label="Candidate Instructions (Optional)"
            rows={2}
            value={form.candidate_instructions}
            onChange={(e) => setForm({ ...form, candidate_instructions: e.target.value })}
          />

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={form.interviewer_ids.length === 0} isLoading={scheduleMutation.isPending}>
              Schedule Interview
            </Button>
          </div>
        </form>
      </Drawer>
    </div>
  );
};

export const InterviewDetailPage: React.FC = () => {
  const { id } = useParams();
  const { user, hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelReason, setCancelReason] = useState('');

  const [evalForm, setEvalForm] = useState({
    technical_skills_rating: 4,
    communication_rating: 4,
    problem_solving_rating: 4,
    relevant_experience_rating: 4,
    cultural_fit_rating: 4,
    strengths: '',
    weaknesses: '',
    overall_feedback: '',
    interview_notes: '',
    recommendation: 'yes',
  });

  const { data: iv, isLoading, error, refetch } = useQuery({
    queryKey: ['interview', id],
    queryFn: () => recruitmentApi.getInterview(id!),
  });

  const completeMutation = useMutation({
    mutationFn: () => recruitmentApi.completeInterview(Number(id)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['interview', id] });
      showToast('success', 'Interview completed');
    },
  });

  const cancelMutation = useMutation({
    mutationFn: () => recruitmentApi.cancelInterview(Number(id), cancelReason),
    onSuccess: () => {
      setCancelOpen(false);
      queryClient.invalidateQueries({ queryKey: ['interview', id] });
      showToast('success', 'Interview cancelled', 'Participants notified.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const evalMutation = useMutation({
    mutationFn: () => recruitmentApi.submitEvaluation(Number(id), evalForm),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['interview', id] });
      showToast('success', 'Evaluation submitted', 'Your structured scorecard has been recorded.');
    },
    onError: (err) => showToast('error', 'Submission failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState message="Loading interview workspace..." />;
  if (error || !iv) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  const isAssignedInterviewer =
    user?.role?.slug === 'system_admin' || (iv.panel_users || []).some((p) => p.id === user?.id);
  const alreadyEvaluated = (iv.evaluations || []).some((e) => e.interviewer_user_id === user?.id);

  const criteria: Array<{ key: keyof typeof evalForm; label: string }> = [
    { key: 'technical_skills_rating', label: 'Technical Skills' },
    { key: 'communication_rating', label: 'Communication' },
    { key: 'problem_solving_rating', label: 'Problem Solving' },
    { key: 'relevant_experience_rating', label: 'Relevant Experience' },
    { key: 'cultural_fit_rating', label: 'Cultural Fit' },
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Round ${iv.round_number}: ${iv.title}`}
        description={`${iv.application?.candidate?.first_name} ${iv.application?.candidate?.last_name} · ${iv.application?.job?.title}`}
        breadcrumbs={[{ label: 'Interviews', to: '/interviews' }, { label: `Session #${iv.id}` }]}
        actions={
          <>
            <StatusBadge status={iv.status} />
            {iv.status !== 'cancelled' && iv.status !== 'completed' && hasPermission('interviews.manage') && (
              <>
                <Button size="sm" variant="secondary" onClick={() => completeMutation.mutate()}>
                  Mark Completed
                </Button>
                <Button size="sm" variant="outline" onClick={() => setCancelOpen(true)}>
                  Cancel
                </Button>
              </>
            )}
          </>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {/* Submitted Evaluations */}
          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="mb-3 text-sm font-semibold text-ink">Panel Evaluations ({(iv.evaluations || []).length})</h2>
            {(iv.evaluations || []).length === 0 ? (
              <p className="text-xs text-muted">No evaluations submitted yet.</p>
            ) : (
              <div className="space-y-4">
                {(iv.evaluations || []).map((ev) => (
                  <div key={ev.id} className="rounded-md border border-line p-4 space-y-2.5">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm font-semibold text-ink">{ev.interviewer?.name}</p>
                        <p className="text-xs text-muted">Overall Average: {ev.average_rating} / 5.0</p>
                      </div>
                      <StatusBadge status={ev.recommendation} />
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                      <div className="rounded bg-canvas p-2 text-center"><div className="text-muted">Technical</div><div className="font-semibold">{ev.technical_skills_rating}/5</div></div>
                      <div className="rounded bg-canvas p-2 text-center"><div className="text-muted">Comms</div><div className="font-semibold">{ev.communication_rating}/5</div></div>
                      <div className="rounded bg-canvas p-2 text-center"><div className="text-muted">Problem</div><div className="font-semibold">{ev.problem_solving_rating}/5</div></div>
                      <div className="rounded bg-canvas p-2 text-center"><div className="text-muted">Exp.</div><div className="font-semibold">{ev.relevant_experience_rating}/5</div></div>
                      <div className="rounded bg-canvas p-2 text-center"><div className="text-muted">Culture</div><div className="font-semibold">{ev.cultural_fit_rating}/5</div></div>
                    </div>
                    <div className="text-xs space-y-1">
                      <p><strong className="text-ink">Strengths:</strong> <span className="text-muted">{ev.strengths}</span></p>
                      <p><strong className="text-ink">Weaknesses:</strong> <span className="text-muted">{ev.weaknesses}</span></p>
                      <p><strong className="text-ink">Overall Feedback:</strong> <span className="text-muted">{ev.overall_feedback}</span></p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Structured 1-5 Evaluation Form */}
          {isAssignedInterviewer && !alreadyEvaluated && iv.status !== 'cancelled' && hasPermission('evaluations.submit') && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                evalMutation.mutate();
              }}
              className="rounded-lg border border-line bg-surface p-5 space-y-4"
            >
              <div>
                <h2 className="text-sm font-semibold text-ink">Submit Interview Evaluation</h2>
                <p className="text-xs text-muted">Score each competency from 1 (Poor) to 5 (Exceptional).</p>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {criteria.map((c) => (
                  <Select
                    key={c.key}
                    label={c.label}
                    value={String(evalForm[c.key])}
                    onChange={(e) => setEvalForm({ ...evalForm, [c.key]: Number(e.target.value) })}
                    options={[
                      { value: '1', label: '1 — Poor' },
                      { value: '2', label: '2 — Below Expectations' },
                      { value: '3', label: '3 — Meets Expectations' },
                      { value: '4', label: '4 — Exceeds Expectations' },
                      { value: '5', label: '5 — Exceptional' },
                    ]}
                  />
                ))}
                <Select
                  label="Overall Recommendation"
                  value={evalForm.recommendation}
                  onChange={(e) => setEvalForm({ ...evalForm, recommendation: e.target.value })}
                  options={[
                    { value: 'strong_yes', label: 'Strong Yes' },
                    { value: 'yes', label: 'Yes' },
                    { value: 'neutral', label: 'Neutral' },
                    { value: 'no', label: 'No' },
                    { value: 'strong_no', label: 'Strong No' },
                  ]}
                />
              </div>
              <Textarea
                label="Strengths"
                required
                rows={2}
                value={evalForm.strengths}
                onChange={(e) => setEvalForm({ ...evalForm, strengths: e.target.value })}
              />
              <Textarea
                label="Weaknesses / Risks"
                required
                rows={2}
                value={evalForm.weaknesses}
                onChange={(e) => setEvalForm({ ...evalForm, weaknesses: e.target.value })}
              />
              <Textarea
                label="Overall Feedback"
                required
                rows={3}
                value={evalForm.overall_feedback}
                onChange={(e) => setEvalForm({ ...evalForm, overall_feedback: e.target.value })}
              />
              <div className="flex justify-end">
                <Button type="submit" isLoading={evalMutation.isPending}>
                  Submit Evaluation
                </Button>
              </div>
            </form>
          )}
        </div>

        <div className="space-y-4">
          <div className="rounded-lg border border-line bg-surface p-5 text-xs space-y-2.5">
            <h3 className="font-semibold uppercase tracking-wider text-muted">Session Details</h3>
            <div className="flex justify-between"><span className="text-muted">Scheduled</span><span className="font-medium">{formatDateTime(iv.scheduled_at)}</span></div>
            <div className="flex justify-between"><span className="text-muted">Duration</span><span className="font-medium">{iv.duration_minutes} mins</span></div>
            <div className="flex justify-between"><span className="text-muted">Mode</span><span className="font-medium">{humanizeSlug(iv.mode)}</span></div>
            {iv.meeting_url && (
              <a
                href={iv.meeting_url}
                target="_blank"
                rel="noreferrer"
                className="mt-2 inline-flex items-center gap-1 font-medium text-brand hover:underline"
              >
                Join Meeting Link <ExternalLink className="h-3.5 w-3.5" />
              </a>
            )}
            {iv.location && <p className="text-ink"><strong>Location:</strong> {iv.location}</p>}
            {iv.application && (
              <div className="pt-2 border-t border-line">
                <Link to={`/applications/${iv.application.id}`} className="font-medium text-brand hover:underline">
                  View Full Application &rarr;
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>

      <Modal
        isOpen={cancelOpen}
        onClose={() => setCancelOpen(false)}
        title="Cancel Interview"
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setCancelOpen(false)}>Back</Button>
            <Button variant="danger" isLoading={cancelMutation.isPending} onClick={() => cancelMutation.mutate()}>
              Confirm Cancellation
            </Button>
          </>
        }
      >
        <Textarea
          label="Cancellation Reason"
          required
          rows={3}
          value={cancelReason}
          onChange={(e) => setCancelReason(e.target.value)}
        />
      </Modal>
    </div>
  );
};
