import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, Kanban, List, Star } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatDate } from '../../lib/formatters';
import {
  Button,
  DataTable,
  ErrorState,
  FilterDropdown,
  LoadingState,
  Modal,
  PageHeader,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';

export const PipelinePage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [viewMode, setViewMode] = useState<'kanban' | 'list'>('kanban');
  const jobId = searchParams.get('job_id') || '';

  const [moveModal, setMoveModal] = useState<{
    applicationId: number;
    candidateName: string;
    currentStageName: string;
    allowedNext: string[];
  } | null>(null);
  const [nextStage, setNextStage] = useState('');
  const [reason, setReason] = useState('');

  const { data: jobs } = useQuery({ queryKey: ['jobs', 'pipeline-filter'], queryFn: () => recruitmentApi.listJobs({ per_page: 50 }) });
  const { data: columns, isLoading, error, refetch } = useQuery({
    queryKey: ['pipeline', jobId],
    queryFn: () => recruitmentApi.getPipelineBoard(jobId || undefined),
  });

  const transitionMutation = useMutation({
    mutationFn: () =>
      recruitmentApi.transitionStage(moveModal!.applicationId, {
        stage_slug: nextStage,
        reason: reason || undefined,
      }),
    onSuccess: () => {
      setMoveModal(null);
      setNextStage('');
      setReason('');
      queryClient.invalidateQueries({ queryKey: ['pipeline'] });
      showToast('success', 'Stage updated', 'Candidate moved to the new stage.');
    },
    onError: (err) => showToast('error', 'Transition blocked', extractApiError(err).message),
  });

  const flatRows = (columns || []).flatMap((col) =>
    col.applications.map((app) => ({ ...app, stage: col.stage }))
  );

  return (
    <div>
      <PageHeader
        title="Recruitment Pipeline"
        description="Track candidates across all 9 hiring stages with backend-validated stage transitions."
        actions={
          <div className="flex items-center gap-2">
            <FilterDropdown
              label="Job"
              allLabel="All Jobs"
              value={jobId}
              onChange={(v) => {
                if (v) setSearchParams({ job_id: v });
                else setSearchParams({});
              }}
              options={(jobs?.data || []).map((j) => ({ value: j.id, label: `${j.code} — ${j.title}` }))}
            />
            <div className="flex rounded-md border border-line bg-surface p-0.5">
              <button
                type="button"
                onClick={() => setViewMode('kanban')}
                className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium ${
                  viewMode === 'kanban' ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                }`}
              >
                <Kanban className="h-3.5 w-3.5" /> Board
              </button>
              <button
                type="button"
                onClick={() => setViewMode('list')}
                className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium ${
                  viewMode === 'list' ? 'bg-brand-subtle text-brand' : 'text-muted hover:text-ink'
                }`}
              >
                <List className="h-3.5 w-3.5" /> List
              </button>
            </div>
          </div>
        }
      />

      {isLoading ? (
        <LoadingState message="Loading pipeline board..." />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : viewMode === 'kanban' ? (
        <div className="flex gap-4 overflow-x-auto pb-4">
          {(columns || []).map((col) => (
            <div
              key={col.stage.id}
              className="flex w-72 shrink-0 snap-start flex-col rounded-lg border border-line bg-surface"
            >
              <div className="flex items-center justify-between border-b border-line bg-canvas/70 px-3.5 py-2.5">
                <span className="text-xs font-semibold text-ink">{col.stage.name}</span>
                <span className="rounded-full bg-surface px-2 py-0.5 text-[11px] font-semibold text-muted border border-line">
                  {col.count}
                </span>
              </div>
              <div className="flex-1 space-y-2.5 p-2.5 min-h-[260px]">
                {col.applications.length === 0 ? (
                  <p className="py-8 text-center text-xs text-muted">No candidates</p>
                ) : (
                  col.applications.map((card) => (
                    <div key={card.id} className="rounded-md border border-line bg-surface p-3 shadow-2xs hover:border-brand/40 transition-colors">
                      <div className="flex items-start justify-between gap-2">
                        <Link to={`/applications/${card.id}`} className="text-sm font-medium text-ink hover:text-brand">
                          {card.candidate.full_name}
                        </Link>
                        {card.average_evaluation_rating !== null && card.average_evaluation_rating !== undefined && (
                          <span className="inline-flex items-center gap-0.5 text-[11px] font-semibold text-warning">
                            <Star className="h-3 w-3 fill-warning" /> {card.average_evaluation_rating}
                          </span>
                        )}
                      </div>
                      <p className="mt-0.5 truncate text-xs text-muted">{card.job.title}</p>
                      {card.candidate.skills.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-1">
                          {card.candidate.skills.slice(0, 3).map((sk) => (
                            <span key={sk} className="rounded bg-canvas px-1.5 py-0.5 text-[10px] text-muted border border-line">
                              {sk}
                            </span>
                          ))}
                        </div>
                      )}
                      <div className="mt-2.5 flex items-center justify-between border-t border-line pt-2 text-[11px] text-muted">
                        <span>{formatDate(card.applied_at)}</span>
                        {!col.stage.is_terminal && hasPermission('applications.transition') && (
                          <button
                            type="button"
                            onClick={() => {
                              setMoveModal({
                                applicationId: card.id,
                                candidateName: card.candidate.full_name,
                                currentStageName: col.stage.name,
                                allowedNext: col.stage.allowed_next_stages || [],
                              });
                              setNextStage((col.stage.allowed_next_stages || [])[0] || '');
                            }}
                            className="inline-flex items-center gap-0.5 font-medium text-brand hover:underline"
                          >
                            Move <ArrowRight className="h-3 w-3" />
                          </button>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <DataTable
          data={flatRows}
          rowKey={(r) => r.id}
          columns={[
            {
              key: 'cand',
              header: 'Candidate',
              render: (r) => (
                <Link to={`/applications/${r.id}`} className="font-medium text-brand hover:underline">
                  {r.candidate.full_name}
                </Link>
              ),
            },
            { key: 'job', header: 'Position', render: (r) => <span className="text-xs">{r.job.title}</span> },
            { key: 'stage', header: 'Stage', render: (r) => <StatusBadge status={r.stage.slug} label={r.stage.name} /> },
            {
              key: 'rating',
              header: 'Eval Avg',
              render: (r) => <span className="text-xs">{r.average_evaluation_rating ?? '—'}</span>,
            },
            { key: 'applied', header: 'Applied', render: (r) => <span className="text-xs text-muted">{formatDate(r.applied_at)}</span> },
          ]}
        />
      )}

      <Modal
        isOpen={Boolean(moveModal)}
        onClose={() => setMoveModal(null)}
        title={`Move ${moveModal?.candidateName}`}
        description={`Current stage: ${moveModal?.currentStageName}`}
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setMoveModal(null)}>
              Cancel
            </Button>
            <Button disabled={!nextStage} isLoading={transitionMutation.isPending} onClick={() => transitionMutation.mutate()}>
              Move Candidate
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <Select
            label="Target Stage"
            required
            value={nextStage}
            onChange={(e) => setNextStage(e.target.value)}
            options={(moveModal?.allowedNext || []).map((slug) => ({
              value: slug,
              label: slug.charAt(0).toUpperCase() + slug.slice(1),
            }))}
          />
          <Textarea
            label={nextStage === 'rejected' ? 'Rejection Reason (Required)' : 'Reason / Notes (Optional)'}
            required={nextStage === 'rejected'}
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
        </div>
      </Modal>
    </div>
  );
};
