import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarPlus, Download, FileBadge } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { downloadPrivateDocument } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatCurrency, formatDateTime, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  DataTable,
  ErrorState,
  FilterDropdown,
  LoadingState,
  Modal,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';

export const ApplicationsListPage: React.FC = () => {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [jobId, setJobId] = useState('');
  const [stageSlug, setStageSlug] = useState('');

  const { data: jobs } = useQuery({ queryKey: ['jobs', 'filter'], queryFn: () => recruitmentApi.listJobs({ per_page: 50 }) });
  const { data: stages } = useQuery({ queryKey: ['stages'], queryFn: () => recruitmentApi.listStages() });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['applications', { page, search, jobId, stageSlug }],
    queryFn: () =>
      recruitmentApi.listApplications({
        page,
        search: search || undefined,
        job_id: jobId || undefined,
        stage_slug: stageSlug || undefined,
      }),
  });

  return (
    <div>
      <PageHeader
        title="Applications"
        description="Track every candidate application, stage transition, and evaluation outcome."
        actions={
          <Link to="/pipeline">
            <Button variant="outline" size="sm">
              Open Kanban Pipeline
            </Button>
          </Link>
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onChange={(v) => {
            setSearch(v);
            setPage(1);
          }}
          placeholder="Search candidate, job, or app number..."
          className="w-full sm:w-72"
        />
        <FilterDropdown
          label="Job"
          value={jobId}
          onChange={(v) => {
            setJobId(v);
            setPage(1);
          }}
          options={(jobs?.data || []).map((j) => ({ value: j.id, label: `${j.code} — ${j.title}` }))}
        />
        <FilterDropdown
          label="Stage"
          value={stageSlug}
          onChange={(v) => {
            setStageSlug(v);
            setPage(1);
          }}
          options={(stages || []).map((s) => ({ value: s.slug, label: s.name }))}
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
            rowKey={(a) => a.id}
            onRowClick={(a) => navigate(`/applications/${a.id}`)}
            columns={[
              {
                key: 'candidate',
                header: 'Candidate',
                render: (a) => (
                  <div>
                    <div className="font-medium text-ink">{a.candidate?.full_name}</div>
                    <div className="text-xs text-muted">{a.application_number} · {a.candidate?.email}</div>
                  </div>
                ),
              },
              {
                key: 'job',
                header: 'Vacancy',
                render: (a) => (
                  <div className="text-xs">
                    <div className="font-medium text-ink">{a.job?.title}</div>
                    <div className="text-muted">{a.job?.department?.name}</div>
                  </div>
                ),
              },
              {
                key: 'stage',
                header: 'Pipeline Stage',
                render: (a) => <StatusBadge status={a.current_stage?.slug} label={a.current_stage?.name} />,
              },
              {
                key: 'hm',
                header: 'HM Recommendation',
                render: (a) =>
                  a.hiring_manager_recommendation ? <StatusBadge status={a.hiring_manager_recommendation} /> : '—',
              },
              {
                key: 'applied_at',
                header: 'Applied',
                render: (a) => <span className="text-xs text-muted">{formatDateTime(a.applied_at)}</span>,
              },
            ]}
          />
          <Pagination meta={data?.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
};

export const ApplicationDetailPage: React.FC = () => {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [stageModalOpen, setStageModalOpen] = useState(false);
  const [targetStage, setTargetStage] = useState('');
  const [transitionReason, setTransitionReason] = useState('');
  const [transitionNotes, setTransitionNotes] = useState('');
  const [noteBody, setNoteBody] = useState('');
  const [hmRec, setHmRec] = useState('yes');
  const [hmNotes, setHmNotes] = useState('');

  const { data: app, isLoading, error, refetch } = useQuery({
    queryKey: ['application', id],
    queryFn: () => recruitmentApi.getApplication(id!),
  });
  const { data: stages } = useQuery({ queryKey: ['stages'], queryFn: () => recruitmentApi.listStages() });

  const transitionMutation = useMutation({
    mutationFn: () =>
      recruitmentApi.transitionStage(Number(id), {
        stage_slug: targetStage,
        reason: transitionReason || undefined,
        notes: transitionNotes || undefined,
      }),
    onSuccess: () => {
      setStageModalOpen(false);
      setTransitionReason('');
      setTransitionNotes('');
      queryClient.invalidateQueries({ queryKey: ['application', id] });
      queryClient.invalidateQueries({ queryKey: ['pipeline'] });
      showToast('success', 'Stage updated', 'Application moved and transition recorded in history.');
    },
    onError: (err) => showToast('error', 'Transition blocked', extractApiError(err).message),
  });

  const noteMutation = useMutation({
    mutationFn: () => recruitmentApi.addApplicationNote(Number(id), noteBody),
    onSuccess: () => {
      setNoteBody('');
      queryClient.invalidateQueries({ queryKey: ['application', id] });
      showToast('success', 'Note added', 'Internal note saved.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const recMutation = useMutation({
    mutationFn: () => recruitmentApi.submitRecommendation(Number(id), { recommendation: hmRec, notes: hmNotes }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['application', id] });
      showToast('success', 'Recommendation saved', 'Hiring Manager feedback updated.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState message="Loading application workspace..." />;
  if (error || !app) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${app.candidate?.full_name} — ${app.job?.title}`}
        description={`${app.application_number} · Applied ${formatDateTime(app.applied_at)} · Source: ${humanizeSlug(app.source)}`}
        breadcrumbs={[{ label: 'Applications', to: '/applications' }, { label: app.application_number }]}
        actions={
          <>
            <StatusBadge status={app.current_stage?.slug} label={app.current_stage?.name} />
            {app.cv_document && (
              <Button
                variant="outline"
                size="sm"
                leftIcon={<Download className="h-3.5 w-3.5" />}
                onClick={() => downloadPrivateDocument(app.cv_document!.id, app.cv_document!.original_name)}
              >
                Download CV
              </Button>
            )}
            {app.is_active && hasPermission('applications.transition') && (
              <Button
                size="sm"
                onClick={() => {
                  setTargetStage('');
                  setStageModalOpen(true);
                }}
              >
                Move Stage
              </Button>
            )}
            {app.is_active && hasPermission('interviews.manage') && (
              <Link to={`/interviews?schedule_for=${app.id}`}>
                <Button variant="secondary" size="sm" leftIcon={<CalendarPlus className="h-3.5 w-3.5" />}>
                  Schedule Interview
                </Button>
              </Link>
            )}
            {app.is_active && hasPermission('offers.manage') && (
              <Link to={`/job-offers?create_for=${app.id}`}>
                <Button variant="outline" size="sm" leftIcon={<FileBadge className="h-3.5 w-3.5" />}>
                  Create Offer
                </Button>
              </Link>
            )}
          </>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {app.cover_letter && (
            <div className="rounded-lg border border-line bg-surface p-5">
              <h2 className="text-sm font-semibold text-ink">Cover Letter</h2>
              <p className="mt-2 whitespace-pre-line text-sm text-muted">{app.cover_letter}</p>
            </div>
          )}

          {/* Interviews & Evaluations */}
          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="mb-3 text-sm font-semibold text-ink">Interviews & Evaluations</h2>
            {(app.interviews || []).length === 0 ? (
              <p className="text-xs text-muted">No interviews scheduled for this application yet.</p>
            ) : (
              <div className="space-y-3">
                {(app.interviews || []).map((iv) => (
                  <div key={iv.id} className="rounded-md border border-line p-3.5">
                    <div className="flex items-center justify-between">
                      <Link to={`/interviews/${iv.id}`} className="text-sm font-medium text-brand hover:underline">
                        Round {iv.round_number}: {iv.title}
                      </Link>
                      <StatusBadge status={iv.status} />
                    </div>
                    <p className="mt-1 text-xs text-muted">
                      {formatDateTime(iv.scheduled_at)} ({iv.duration_minutes} mins) · {humanizeSlug(iv.mode)}
                    </p>
                    {(iv.evaluations || []).length > 0 && (
                      <div className="mt-2.5 space-y-1.5 border-t border-line pt-2.5">
                        {(iv.evaluations || []).map((ev) => (
                          <div key={ev.id} className="flex items-center justify-between text-xs">
                            <span className="text-ink font-medium">{ev.interviewer?.name || 'Interviewer'}</span>
                            <div className="flex items-center gap-2">
                              <span className="text-muted">Avg: {ev.average_rating}/5</span>
                              <StatusBadge status={ev.recommendation} />
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Internal Notes */}
          {hasPermission('applications.note') && (
            <div className="rounded-lg border border-line bg-surface p-5">
              <h2 className="mb-3 text-sm font-semibold text-ink">Internal Recruiting Notes</h2>
              <div className="space-y-3 mb-4">
                {(app.notes || []).length === 0 ? (
                  <p className="text-xs text-muted">No internal notes yet.</p>
                ) : (
                  (app.notes || []).map((n) => (
                    <div key={n.id} className="rounded-md border border-line bg-canvas/50 p-3">
                      <p className="text-sm text-ink">{n.body}</p>
                      <p className="mt-1 text-[11px] text-muted">
                        {n.author?.name} · {formatDateTime(n.created_at)}
                      </p>
                    </div>
                  ))
                )}
              </div>
              <div className="space-y-2">
                <Textarea
                  rows={2}
                  placeholder="Write an internal note visible only to the hiring team..."
                  value={noteBody}
                  onChange={(e) => setNoteBody(e.target.value)}
                />
                <div className="flex justify-end">
                  <Button size="sm" disabled={noteBody.trim().length < 2} isLoading={noteMutation.isPending} onClick={() => noteMutation.mutate()}>
                    Add Internal Note
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="space-y-6">
          {/* Candidate Quick Card */}
          <div className="rounded-lg border border-line bg-surface p-5 text-xs space-y-2">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold uppercase tracking-wider text-muted">Candidate Summary</h3>
              {app.candidate && (
                <Link to={`/candidates/${app.candidate.id}`} className="font-medium text-brand hover:underline">
                  Full Profile
                </Link>
              )}
            </div>
            <div className="flex justify-between"><span className="text-muted">Email</span><span className="font-medium">{app.candidate?.email}</span></div>
            <div className="flex justify-between"><span className="text-muted">Phone</span><span className="font-medium">{app.candidate?.phone || '—'}</span></div>
            <div className="flex justify-between"><span className="text-muted">Expected Salary</span><span className="font-medium">{formatCurrency(app.expected_salary)}</span></div>
            <div className="flex justify-between"><span className="text-muted">HM Decision</span><StatusBadge status={app.hiring_manager_recommendation} /></div>
          </div>

          {/* Hiring Manager Recommendation */}
          {hasPermission('applications.recommend') && (
            <div className="rounded-lg border border-line bg-surface p-5 space-y-3">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted">Hiring Manager Recommendation</h3>
              <Select
                value={hmRec}
                onChange={(e) => setHmRec(e.target.value)}
                options={[
                  { value: 'strong_yes', label: 'Strong Yes' },
                  { value: 'yes', label: 'Yes' },
                  { value: 'neutral', label: 'Neutral' },
                  { value: 'no', label: 'No' },
                  { value: 'strong_no', label: 'Strong No' },
                ]}
              />
              <Textarea
                rows={2}
                placeholder="Summary reasoning..."
                value={hmNotes}
                onChange={(e) => setHmNotes(e.target.value)}
              />
              <Button size="sm" className="w-full" isLoading={recMutation.isPending} onClick={() => recMutation.mutate()}>
                Save Recommendation
              </Button>
            </div>
          )}

          {/* Stage Transition History */}
          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">Stage History Audit Trail</h3>
            <div className="space-y-3">
              {(app.histories || []).map((h) => (
                <div key={h.id} className="border-l-2 border-brand/30 pl-3 text-xs">
                  <p className="font-medium text-ink">
                    {h.from_stage ? `${h.from_stage.name} → ` : ''}
                    {h.to_stage?.name}
                  </p>
                  {h.reason && <p className="text-muted">{h.reason}</p>}
                  <p className="mt-0.5 text-[11px] text-muted">
                    {h.actor?.name || 'System'} · {formatDateTime(h.created_at)}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      <Modal
        isOpen={stageModalOpen}
        onClose={() => setStageModalOpen(false)}
        title="Transition Recruitment Stage"
        description={`Current stage: ${app.current_stage?.name}`}
        footer={
          <>
            <Button variant="outline" onClick={() => setStageModalOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!targetStage} isLoading={transitionMutation.isPending} onClick={() => transitionMutation.mutate()}>
              Confirm Transition
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <Select
            label="Next Stage"
            required
            placeholder="Select next stage"
            value={targetStage}
            onChange={(e) => setTargetStage(e.target.value)}
            options={(stages || [])
              .filter((s) => s.slug !== app.current_stage?.slug)
              .map((s) => ({ value: s.slug, label: s.name }))}
          />
          <Textarea
            label={targetStage === 'rejected' ? 'Rejection Reason (Required)' : 'Transition Reason (Optional)'}
            required={targetStage === 'rejected'}
            rows={2}
            value={transitionReason}
            onChange={(e) => setTransitionReason(e.target.value)}
          />
          <Textarea
            label="Internal Notes (Optional)"
            rows={2}
            value={transitionNotes}
            onChange={(e) => setTransitionNotes(e.target.value)}
          />
        </div>
      </Modal>
    </div>
  );
};
