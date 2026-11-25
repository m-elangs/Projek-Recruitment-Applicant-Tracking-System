import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { EmptyState, ErrorState, LoadingState, StatusBadge } from '../src/components/ui';
import { AuthProvider } from '../src/context/AuthContext';
import { ToastProvider } from '../src/context/ToastContext';
import { LoginPage } from '../src/features/auth/AuthPages';

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <AuthProvider>
          <MemoryRouter>{ui}</MemoryRouter>
        </AuthProvider>
      </ToastProvider>
    </QueryClientProvider>
  );
}

describe('TalentFlow Design System & Auth Validation', () => {
  it('renders LoadingState, EmptyState, ErrorState, and StatusBadge cleanly', async () => {
    const retry = vi.fn();
    render(
      <div>
        <LoadingState message="Fetching pipeline..." />
        <EmptyState title="No applicants yet" description="Publish the role to begin." />
        <ErrorState message="Network timeout" onRetry={retry} />
        <StatusBadge status="shortlisted" />
      </div>
    );

    expect(screen.getByText('Fetching pipeline...')).toBeInTheDocument();
    expect(screen.getByText('No applicants yet')).toBeInTheDocument();
    expect(screen.getByText('Network timeout')).toBeInTheDocument();
    expect(screen.getByText('Shortlisted')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /try again/i }));
    expect(retry).toHaveBeenCalledTimes(1);
  });

  it('validates login form fields and supports demo account quick-fill', async () => {
    renderWithProviders(<LoginPage />);

    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));
    expect(await screen.findByText(/enter a valid email address/i)).toBeInTheDocument();

    await userEvent.click(screen.getByText('Recruiter'));
    const emailInput = screen.getByLabelText(/work or personal email/i) as HTMLInputElement;
    expect(emailInput.value).toBe('recruiter@talentflow.local');
  });
});
