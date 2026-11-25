import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import { ToastProvider } from './context/ToastContext';
import {
  CandidateOnlyRoute,
  GuestOnlyRoute,
  InternalOnlyRoute,
  PermissionRoute,
  ProtectedRoute,
} from './components/guards/RouteGuards';
import { InternalAppShell } from './components/layouts/InternalAppShell';
import { PublicAndCandidateShell } from './components/layouts/PublicAndCandidateShell';
import {
  AccountSettingsPage,
  ForgotPasswordPage,
  LoginPage,
  RegisterPage,
  ResetPasswordPage,
} from './features/auth/AuthPages';
import { DashboardPage } from './features/dashboard/DashboardPage';
import { JobDetailPage, JobFormPage, JobsListPage } from './features/jobs/JobsPages';
import { RequisitionsPage } from './features/requisitions/RequisitionsPage';
import { CandidateDetailPage, CandidatesListPage } from './features/candidates/CandidatesPages';
import { ApplicationDetailPage, ApplicationsListPage } from './features/applications/ApplicationsPages';
import { PipelinePage } from './features/pipeline/PipelinePage';
import { InterviewCalendarPage, InterviewDetailPage } from './features/interviews/InterviewsPages';
import { JobOffersPage } from './features/offers/JobOffersPage';
import { ReportsPage } from './features/reports/ReportsPage';
import {
  ActivityLogsPage,
  CompanySettingsPage,
  DepartmentsPage,
  NotificationsPage,
  RolesPermissionsPage,
  UsersPage,
} from './features/admin/AdminPages';
import {
  CandidateApplicationDetailPage,
  CandidateDashboardPage,
  CandidateInterviewsPage,
  CareersPage,
  MyApplicationsPage,
  MyProfilePage,
  PublicJobDetailPage,
} from './features/portal/CandidatePortalPages';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

export const App: React.FC = () => (
  <QueryClientProvider client={queryClient}>
    <ToastProvider>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            {/* Public Careers & Candidate Portal Shell */}
            <Route element={<PublicAndCandidateShell />}>
              <Route path="/" element={<Navigate to="/careers" replace />} />
              <Route path="/careers" element={<CareersPage />} />
              <Route path="/careers/jobs/:slug" element={<PublicJobDetailPage />} />

              <Route element={<ProtectedRoute />}>
                <Route element={<CandidateOnlyRoute />}>
                  <Route path="/portal/dashboard" element={<CandidateDashboardPage />} />
                  <Route path="/portal/profile" element={<MyProfilePage />} />
                  <Route path="/portal/applications" element={<MyApplicationsPage />} />
                  <Route path="/portal/applications/:id" element={<CandidateApplicationDetailPage />} />
                  <Route path="/portal/interviews" element={<CandidateInterviewsPage />} />
                  <Route path="/portal/notifications" element={<NotificationsPage />} />
                </Route>
              </Route>
            </Route>

            {/* Authentication Routes */}
            <Route element={<GuestOnlyRoute />}>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />
            </Route>

            {/* Internal ATS Workspace (System Admin, Recruiter, Hiring Manager) */}
            <Route element={<ProtectedRoute />}>
              <Route element={<InternalOnlyRoute />}>
                <Route element={<InternalAppShell />}>
                  <Route path="/dashboard" element={<DashboardPage />} />
                  <Route path="/account" element={<AccountSettingsPage />} />
                  <Route path="/notifications" element={<NotificationsPage />} />

                  <Route path="/jobs" element={<JobsListPage />} />
                  <Route element={<PermissionRoute permission="jobs.manage" />}>
                    <Route path="/jobs/new" element={<JobFormPage />} />
                    <Route path="/jobs/:id/edit" element={<JobFormPage />} />
                  </Route>
                  <Route path="/jobs/:id" element={<JobDetailPage />} />

                  <Route path="/requisitions" element={<RequisitionsPage />} />
                  <Route path="/candidates" element={<CandidatesListPage />} />
                  <Route path="/candidates/:id" element={<CandidateDetailPage />} />
                  <Route path="/applications" element={<ApplicationsListPage />} />
                  <Route path="/applications/:id" element={<ApplicationDetailPage />} />
                  <Route path="/pipeline" element={<PipelinePage />} />
                  <Route path="/interviews" element={<InterviewCalendarPage />} />
                  <Route path="/interviews/:id" element={<InterviewDetailPage />} />
                  <Route path="/job-offers" element={<JobOffersPage />} />
                  <Route path="/reports" element={<ReportsPage />} />

                  <Route element={<PermissionRoute permission="users.view" />}>
                    <Route path="/admin/users" element={<UsersPage />} />
                  </Route>
                  <Route element={<PermissionRoute permission="roles.manage" />}>
                    <Route path="/admin/roles" element={<RolesPermissionsPage />} />
                  </Route>
                  <Route element={<PermissionRoute permission="departments.manage" />}>
                    <Route path="/admin/departments" element={<DepartmentsPage />} />
                  </Route>
                  <Route element={<PermissionRoute permission="settings.manage" />}>
                    <Route path="/admin/settings" element={<CompanySettingsPage />} />
                  </Route>
                  <Route element={<PermissionRoute permission="audit_logs.view" />}>
                    <Route path="/admin/activity-logs" element={<ActivityLogsPage />} />
                  </Route>
                </Route>
              </Route>
            </Route>

            <Route path="*" element={<Navigate to="/careers" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </ToastProvider>
  </QueryClientProvider>
);

export default App;
