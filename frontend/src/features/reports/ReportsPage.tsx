import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { recruitmentApi } from '../../api/services';
import { extractApiError } from '../../lib/formatters';
import { DataTable, ErrorState, FilterDropdown, Input, LoadingState, PageHeader, StatCard, StatusBadge } from '../../components/ui';

export const ReportsPage: React.FC = () => {
  const [departmentId, setDepartmentId] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const { data: departments } = useQuery({
    queryKey: ['departments'],
    queryFn: () => recruitmentApi.listDepartments(),
  });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['reports', { departmentId, from, to }],
    queryFn: () =>
      recruitmentApi.getReports({
        department_id: departmentId || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
  });

  return (
    <div className="space-y-6">
      <PageHeader
        title="Recruitment Analytics & Reports"
        description="Live hiring conversion rates, time-to-hire, source attribution, and departmental performance."
      />

      <div className="flex flex-wrap items-end gap-3 rounded-lg border border-line bg-surface p-4">
        <FilterDropdown
          label="Department"
          allLabel="All Departments"
          value={departmentId}
          onChange={setDepartmentId}
          options={(departments || []).map((d) => ({ value: d.id, label: d.name }))}
        />
        <div className="w-40">
          <Input label="From Date" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div className="w-40">
          <Input label="To Date" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>

      {isLoading ? (
        <LoadingState message="Computing live recruitment analytics..." />
      ) : error || !data ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
              label="Hiring Conversion Rate"
              value={`${data.overview.hiring_conversion_rate}%`}
              subtext={`${data.overview.hired_candidates} hired of ${data.overview.total_applications} applications`}
            />
            <StatCard
              label="Avg Time to Hire"
              value={`${data.overview.avg_time_to_hire_days} days`}
              subtext="Measured from Applied to Hired"
            />
            <StatCard
              label="Offer Acceptance Rate"
              value={`${data.overview.offer_acceptance_rate}%`}
              subtext="Accepted vs responded offers"
            />
            <StatCard
              label="Hired vs Rejected"
              value={`${data.overview.hired_candidates} / ${data.overview.rejected_candidates}`}
              subtext={`${data.overview.withdrawn_candidates} withdrawn`}
            />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="min-w-0 rounded-lg border border-line bg-surface p-5">
              <h2 className="text-sm font-semibold text-ink">Candidates by Recruitment Stage</h2>
              <div className="mt-4 h-64 w-full min-w-0">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.candidates_by_stage} margin={{ top: 8, right: 8, left: -16, bottom: 24 }}>
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
              <h2 className="text-sm font-semibold text-ink">Candidate Sources & Hires</h2>
              <div className="mt-4 h-64 w-full min-w-0">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.candidate_sources} margin={{ top: 8, right: 8, left: -16, bottom: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#E6E8EC" vertical={false} />
                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#667085' }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#667085' }} />
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="applications" name="Applications" fill="#334E85" radius={[3, 3, 0, 0]} />
                    <Bar dataKey="hired" name="Hired" fill="#16845B" radius={[3, 3, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          </div>

          <div className="space-y-3">
            <h2 className="text-sm font-semibold text-ink">Applications by Job Vacancy</h2>
            <DataTable
              data={data.applications_by_job}
              rowKey={(r) => r.job_id}
              columns={[
                { key: 'title', header: 'Job', render: (r) => <span className="font-medium">{r.code} — {r.title}</span> },
                { key: 'dept', header: 'Department', render: (r) => <span className="text-xs text-muted">{r.department || '—'}</span> },
                { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                { key: 'total', header: 'Total Apps', render: (r) => <span className="font-semibold">{r.total_applications}</span> },
                { key: 'active', header: 'Active', render: (r) => r.active },
                { key: 'hired', header: 'Hired', render: (r) => <span className="font-semibold text-success">{r.hired}</span> },
                { key: 'rejected', header: 'Rejected', render: (r) => <span className="text-danger">{r.rejected}</span> },
              ]}
            />
          </div>

          <div className="space-y-3">
            <h2 className="text-sm font-semibold text-ink">Recruitment Performance by Department</h2>
            <DataTable
              data={data.department_performance}
              rowKey={(r) => r.department_id}
              columns={[
                { key: 'dept', header: 'Department', render: (r) => <span className="font-medium">{r.code} — {r.department}</span> },
                { key: 'open', header: 'Open Jobs', render: (r) => r.open_jobs },
                { key: 'apps', header: 'Applications', render: (r) => r.applications },
                { key: 'hired', header: 'Hired', render: (r) => <span className="font-semibold text-success">{r.hired}</span> },
                { key: 'rejected', header: 'Rejected', render: (r) => r.rejected },
                { key: 'tth', header: 'Avg Time to Hire', render: (r) => `${r.avg_time_to_hire_days} days` },
              ]}
            />
          </div>
        </>
      )}
    </div>
  );
};
