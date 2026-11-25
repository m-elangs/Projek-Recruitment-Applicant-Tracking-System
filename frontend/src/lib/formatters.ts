import axios from 'axios';

export function formatCurrency(amount: number | string | null | undefined, currency = 'USD'): string {
  if (amount === null || amount === undefined || amount === '') return '—';
  const numeric = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (Number.isNaN(numeric)) return '—';

  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency.toUpperCase(),
      maximumFractionDigits: 0,
    }).format(numeric);
  } catch {
    return `${currency.toUpperCase()} ${numeric.toLocaleString()}`;
  }
}

export function formatSalaryRange(
  min?: number | string | null,
  max?: number | string | null,
  currency = 'USD'
): string {
  if (!min && !max) return 'Competitive compensation';
  if (min && max) return `${formatCurrency(min, currency)} – ${formatCurrency(max, currency)}`;
  if (min) return `From ${formatCurrency(min, currency)}`;
  return `Up to ${formatCurrency(max, currency)}`;
}

export function formatDate(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function formatDateTime(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatBytes(bytes?: number | null): string {
  if (!bytes || bytes <= 0) return '0 KB';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function humanizeSlug(value?: string | null): string {
  if (!value) return '—';
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export interface ExtractedApiError {
  code: string;
  message: string;
  fieldErrors: Record<string, string>;
}

export function extractApiError(err: unknown): ExtractedApiError {
  if (axios.isAxiosError(err)) {
    const payload = err.response?.data;
    const errorBlock = payload?.error;
    const fieldErrors: Record<string, string> = {};

    if (errorBlock?.details && typeof errorBlock.details === 'object') {
      for (const [key, messages] of Object.entries(errorBlock.details as Record<string, unknown>)) {
        if (Array.isArray(messages) && messages.length > 0) {
          fieldErrors[key] = String(messages[0]);
        } else if (typeof messages === 'string') {
          fieldErrors[key] = messages;
        }
      }
    }

    return {
      code: errorBlock?.code || `HTTP_${err.response?.status ?? 'ERROR'}`,
      message: errorBlock?.message || payload?.message || err.message || 'Request failed.',
      fieldErrors,
    };
  }

  if (err instanceof Error) {
    return { code: 'CLIENT_ERROR', message: err.message, fieldErrors: {} };
  }

  return { code: 'UNKNOWN_ERROR', message: 'An unexpected error occurred.', fieldErrors: {} };
}
