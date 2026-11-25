import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { Briefcase, CalendarClock, CheckCircle2, FileSpreadsheet, Users } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { extractApiError, formatDateTime } from '../../lib/formatters';
import { Button, EmptyState, ErrorState, LoadingState, PageHeader, StatCard, StatusBadge } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';

export const DashboardPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => recruitmentApi.getDashboard(),
  });

  if (isLoading) return <LoadingState message="Loading live recruitment metrics..." />;
  if (error || !data) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Recruitment Overview"
        description="Real-time hiring metrics, pipeline funnel, and upcoming activity."
        actions={
          <>
            {hasPermission('jobs.manage') && (
              <Link to="/jobs/new">
                <Button size="sm">Create Job Vacancy</Button>
              </Link>
            )}
            <Link to="/pipeline">
              <Button variant="outline" size="sm">
                Open Pipeline Board
              </Button>
            </Link>
          </>
        }
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
        <StatCard
          label="Active Jobs"
          value={data.kpis.active_jobs}
          subtext={`${data.kpis.pending_requisitions} pending requisitions`}
          icon={<Briefcase className="h-4 w-4" />}
        />
        <StatCard
          label="Total Candidates"
          value={data.kpis.total_candidates}
          subtext="Talent pool directory"
          icon={<Users className="h-4 w-4" />}
        />
        <StatCard
          label="Total Applications"
          value={data.kpis.total_applications}
          subtext="All-time submissions"
          icon={<FileSpreadsheet className="h-4 w-4" />}
        />
        <StatCard
          label="Interviews Scheduled"
          value={data.kpis.interviews_scheduled}
          subtext="Upcoming active sessions"
          icon={<CalendarClock className="h-4 w-4" />}
        />
        <StatCard
          label="Hired Candidates"
          value={data.kpis.hired_candidates}
          subtext={`${data.kpis.pending_offers} pending offers`}
          icon={<CheckCircle2 className="h-4 w-4 text-success" />}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="min-w-0 rounded-lg border border-line bg-surface p-5">
          <h2 className="text-sm font-semibold text-ink">Recruitment Funnel</h2>
          <p className="mt-0.5 text-xs text-muted">Current distribution of applications across pipeline stages.</p>
          <div className="mt-4 h-64 w-full min-w-0">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.recruitment_funnel} margin={{ top: 8, right: 8, left: -16, bottom: 24 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E6E8EC" vertical={false} />
                <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#667085' }} angle={-25} textAnchor="end" interval={0} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#667085' }} />
                <Tooltip />
                <Bar dataKey="count" name="Candidates" fill="#334E85" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="min-w-0 rounded-lg border border-line bg-surface p-5">
          <h2 className="text-sm font-semibold text-ink">Hiring Performance (Last 6 Months)</h2>
          <p className="mt-0.5 text-xs text-muted">Monthly incoming applications compared against completed hires.</p>
          <div className="mt-4 h-64 w-full min-w-0">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.hiring_performance} margin={{ top: 8, right: 8, left: -16, bottom: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E6E8EC" vertical={false} />
                <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#667085' }} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#667085' }} />
                <Tooltip />
                <Bar dataKey="applications" name="Applications" fill="#334E85" radius={[3, 3, 0, 0]} />
                <Bar dataKey="hired" name="Hired" fill="#16845B" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="min-w-0 rounded-lg border border-line bg-surface p-5 lg:col-span-2">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-ink">Active Vacancies & Headcount Progress</h2>
            <Link to="/jobs" className="text-xs font-medium text-brand hover:underline">
              View all jobs
            </Link>
          </div>
          {data.open_vacancies.length === 0 ? (
            <EmptyState title="No active vacancies" description="Publish a job vacancy to start receiving applications." />
          ) : (
            <div className="divide-y divide-line">
              {data.open_vacancies.map((job) => {
                const ratio = Math.min(100, Math.round((job.hired_count / Math.max(1, job.headcount)) * 100));
                return (
                  <div key={job.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <Link to={`/jobs/${job.id}`} className="text-sm font-medium text-ink hover:text-brand">
                        {job.title}
                      </Link>
                      <p className="text-xs text-muted">
                        {job.code} · {job.department?.name} · {job.applications_count} applications
                      </p>
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="w-28">
                        <div className="mb-1 flex justify-between text-[11px] text-muted">
                          <span>Filled</span>
                          <span className="font-medium text-ink">
                            {job.hired_count}/{job.headcount}
                          </span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-canvas">
                          <div className="h-full bg-success" style={{ width: `${ratio}%` }} />
                        </div>
                      </div>
                      <StatusBadge status={job.status} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        <div className="space-y-6">
          <div className="rounded-lg border border-line bg-surface p-5">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-ink">Upcoming Interviews</h2>
              <Link to="/interviews" className="text-xs font-medium text-brand hover:underline">
                Calendar
              </Link>
            </div>
            {data.upcoming_interviews.length === 0 ? (
              <p className="text-xs text-muted">No upcoming interviews scheduled.</p>
            ) : (
              <div className="space-y-3">
                {data.upcoming_interviews.map((iv) => (
                  <Link
                    key={iv.id}
                    to={`/interviews/${iv.id}`}
                    className="block rounded-md border border-line bg-canvas/50 p-3 hover:border-brand/40"
                  >
                    <p className="text-xs font-semibold text-ink">{iv.title}</p>
                    <p className="mt-0.5 text-xs text-muted">
                      {iv.application?.candidate?.first_name} {iv.application?.candidate?.last_name} · {iv.application?.job?.title}
                    </p>
                    <p className="mt-1 text-[11px] font-medium text-brand">{formatDateTime(iv.scheduled_at)}</p>
                  </Link>
                ))}
              </div>
            )}
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="mb-3 text-sm font-semibold text-ink">Recent Activities</h2>
            {data.recent_activities.length === 0 ? (
              <p className="text-xs text-muted">No recent audit activities recorded.</p>
            ) : (
              <div className="space-y-3">
                {data.recent_activities.slice(0, 6).map((log) => (
                  <div key={log.id} className="border-l-2 border-brand/30 pl-3">
                    <p className="text-xs text-ink">{log.description}</p>
                    <p className="mt-0.5 text-[11px] text-muted">
                      {log.actor?.name || 'System'} · {formatDateTime(log.created_at)}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
