import React from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, ChevronLeft, ChevronRight, Inbox, Loader2 } from 'lucide-react';
import { Button } from './Primitives';
import { humanizeSlug } from '../../lib/formatters';
import type { PaginationMeta } from '../../types/models';

const STATUS_STYLES: Record<string, string> = {
  published: 'bg-success-subtle text-success border-success/25',
  active: 'bg-success-subtle text-success border-success/25',
  approved: 'bg-success-subtle text-success border-success/25',
  accepted: 'bg-success-subtle text-success border-success/25',
  hired: 'bg-success-subtle text-success border-success/25',
  completed: 'bg-success-subtle text-success border-success/25',
  strong_yes: 'bg-success-subtle text-success border-success/25',
  yes: 'bg-success-subtle text-success border-success/25',

  applied: 'bg-brand-subtle text-brand border-brand/20',
  screening: 'bg-brand-subtle text-brand border-brand/20',
  shortlisted: 'bg-brand-subtle text-brand border-brand/20',
  interview: 'bg-brand-subtle text-brand border-brand/20',
  assessment: 'bg-brand-subtle text-brand border-brand/20',
  scheduled: 'bg-brand-subtle text-brand border-brand/20',
  sent: 'bg-brand-subtle text-brand border-brand/20',

  offered: 'bg-warning-subtle text-warning border-warning/25',
  pending: 'bg-warning-subtle text-warning border-warning/25',
  pending_approval: 'bg-warning-subtle text-warning border-warning/25',
  paused: 'bg-warning-subtle text-warning border-warning/25',
  rescheduled: 'bg-warning-subtle text-warning border-warning/25',
  neutral: 'bg-warning-subtle text-warning border-warning/25',
  high: 'bg-warning-subtle text-warning border-warning/25',
  urgent: 'bg-danger-subtle text-danger border-danger/25',

  rejected: 'bg-danger-subtle text-danger border-danger/25',
  declined: 'bg-danger-subtle text-danger border-danger/25',
  cancelled: 'bg-danger-subtle text-danger border-danger/25',
  revoked: 'bg-danger-subtle text-danger border-danger/25',
  expired: 'bg-danger-subtle text-danger border-danger/25',
  no: 'bg-danger-subtle text-danger border-danger/25',
  strong_no: 'bg-danger-subtle text-danger border-danger/25',

  draft: 'bg-canvas text-muted border-line',
  closed: 'bg-canvas text-muted border-line',
  withdrawn: 'bg-canvas text-muted border-line',
};

export const StatusBadge: React.FC<{ status?: string | null; label?: string }> = ({ status, label }) => {
  const key = (status || 'draft').toLowerCase();
  const classes = STATUS_STYLES[key] || 'bg-canvas text-muted border-line';

  return (
    <span className={`inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium ${classes}`}>
      {label || humanizeSlug(status)}
    </span>
  );
};

export const StatCard: React.FC<{
  label: string;
  value: string | number;
  subtext?: string;
  icon?: React.ReactNode;
}> = ({ label, value, subtext, icon }) => (
  <div className="rounded-lg border border-line bg-surface p-4">
    <div className="flex items-center justify-between">
      <span className="text-xs font-medium uppercase tracking-wider text-muted">{label}</span>
      {icon && <div className="text-brand">{icon}</div>}
    </div>
    <div className="mt-2 text-2xl font-semibold tracking-tight text-ink">{value}</div>
    {subtext && <p className="mt-1 text-xs text-muted">{subtext}</p>}
  </div>
);

export const Breadcrumb: React.FC<{ items: Array<{ label: string; to?: string }> }> = ({ items }) => (
  <nav aria-label="Breadcrumb" className="mb-2 flex items-center gap-1.5 text-xs text-muted">
    {items.map((item, idx) => (
      <React.Fragment key={`${item.label}-${idx}`}>
        {idx > 0 && <span className="text-line">/</span>}
        {item.to ? (
          <Link to={item.to} className="hover:text-ink transition-colors">
            {item.label}
          </Link>
        ) : (
          <span className="font-medium text-ink">{item.label}</span>
        )}
      </React.Fragment>
    ))}
  </nav>
);

export const PageHeader: React.FC<{
  title: string;
  description?: string;
  breadcrumbs?: Array<{ label: string; to?: string }>;
  actions?: React.ReactNode;
}> = ({ title, description, breadcrumbs, actions }) => (
  <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      {breadcrumbs && breadcrumbs.length > 0 && <Breadcrumb items={breadcrumbs} />}
      <h1 className="text-xl font-semibold tracking-tight text-ink">{title}</h1>
      {description && <p className="mt-1 text-sm text-muted">{description}</p>}
    </div>
    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
  </div>
);

export const LoadingState: React.FC<{ message?: string }> = ({ message = 'Loading data...' }) => (
  <div className="flex flex-col items-center justify-center rounded-lg border border-line bg-surface py-16 text-center">
    <Loader2 className="h-6 w-6 animate-spin text-brand" />
    <p className="mt-2.5 text-sm text-muted">{message}</p>
  </div>
);

export const EmptyState: React.FC<{
  title: string;
  description?: string;
  action?: React.ReactNode;
}> = ({ title, description, action }) => (
  <div className="flex flex-col items-center justify-center rounded-lg border border-line bg-surface px-6 py-14 text-center">
    <div className="flex h-10 w-10 items-center justify-center rounded-md border border-line bg-canvas text-muted">
      <Inbox className="h-5 w-5" />
    </div>
    <h3 className="mt-3 text-sm font-semibold text-ink">{title}</h3>
    {description && <p className="mt-1 max-w-md text-xs text-muted">{description}</p>}
    {action && <div className="mt-4">{action}</div>}
  </div>
);

export const ErrorState: React.FC<{
  title?: string;
  message: string;
  onRetry?: () => void;
}> = ({ title = 'Unable to load resource', message, onRetry }) => (
  <div className="flex flex-col items-center justify-center rounded-lg border border-danger/30 bg-danger-subtle/50 px-6 py-12 text-center">
    <AlertTriangle className="h-6 w-6 text-danger" />
    <h3 className="mt-2.5 text-sm font-semibold text-ink">{title}</h3>
    <p className="mt-1 max-w-md text-xs text-muted">{message}</p>
    {onRetry && (
      <div className="mt-4">
        <Button variant="outline" size="sm" onClick={onRetry}>
          Try Again
        </Button>
      </div>
    )}
  </div>
);

export interface ColumnDef<T> {
  key: string;
  header: string;
  className?: string;
  render: (row: T) => React.ReactNode;
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  emptyTitle = 'No records found',
  emptyDescription = 'Adjust your filters or add a new entry to get started.',
  onRowClick,
}: {
  columns: ColumnDef<T>[];
  data: T[];
  rowKey: (row: T) => string | number;
  emptyTitle?: string;
  emptyDescription?: string;
  onRowClick?: (row: T) => void;
}) {
  if (!data || data.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <div className="w-full rounded-lg border border-line bg-surface">
      <div className="w-full overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-left text-sm">
          <thead className="bg-canvas">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  scope="col"
                  className={`px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-muted ${col.className || ''}`}
                >
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-line bg-surface">
            {data.map((row) => (
              <tr
                key={rowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={onRowClick ? 'cursor-pointer hover:bg-canvas/70 transition-colors' : 'hover:bg-canvas/40'}
              >
                {columns.map((col) => (
                  <td key={col.key} className={`px-4 py-3 align-middle text-sm text-ink ${col.className || ''}`}>
                    {col.render(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export const Pagination: React.FC<{
  meta?: PaginationMeta;
  onPageChange: (page: number) => void;
}> = ({ meta, onPageChange }) => {
  if (!meta || meta.last_page <= 1) return null;

  return (
    <div className="mt-4 flex items-center justify-between rounded-lg border border-line bg-surface px-4 py-2.5 text-xs text-muted">
      <span>
        Page <strong className="text-ink">{meta.current_page}</strong> of{' '}
        <strong className="text-ink">{meta.last_page}</strong> ({meta.total} total records)
      </span>
      <div className="flex items-center gap-1.5">
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
          leftIcon={<ChevronLeft className="h-3.5 w-3.5" />}
        >
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
          <ChevronRight className="h-3.5 w-3.5" />
        </Button>
      </div>
    </div>
  );
};
