import { apiClient, initializeCsrfCookie } from './client';
import type {
  ActivityLog,
  AppNotification,
  Application,
  ApplicationNote,
  Candidate,
  CandidateDocument,
  Department,
  Interview,
  InterviewEvaluation,
  Job,
  JobOffer,
  JobRequisition,
  KanbanColumn,
  PaginatedResult,
  Permission,
  RecruitmentStage,
  Role,
  User,
} from '../types/models';

export const authApi = {
  async login(payload: { email: string; password: string; remember?: boolean }) {
    await initializeCsrfCookie();
    const { data } = await apiClient.post('/auth/login', payload);
    return data.data as { user: User; token: string; redirect_to: string };
  },
  async register(payload: {
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    location?: string;
    password: string;
    password_confirmation: string;
  }) {
    await initializeCsrfCookie();
    const { data } = await apiClient.post('/auth/register', payload);
    return data.data as { user: User; token: string; redirect_to: string };
  },
  async logout() {
    await apiClient.post('/auth/logout');
  },
  async me() {
    const { data } = await apiClient.get('/auth/me');
    return data.data as User;
  },
  async updateProfile(payload: Record<string, unknown>) {
    const { data } = await apiClient.put('/auth/profile', payload);
    return data.data as User;
  },
  async forgotPassword(email: string) {
    const { data } = await apiClient.post('/auth/forgot-password', { email });
    return data.message as string;
  },
  async resetPassword(payload: { token: string; email: string; password: string; password_confirmation: string }) {
    const { data } = await apiClient.post('/auth/reset-password', payload);
    return data.message as string;
  },
};

export const publicCareersApi = {
  async listJobs(params?: Record<string, unknown>): Promise<PaginatedResult<Job>> {
    const { data } = await apiClient.get('/public/jobs', { params });
    return { data: data.data, meta: data.meta };
  },
  async getJob(slugOrId: string): Promise<Job> {
    const { data } = await apiClient.get(`/public/jobs/${slugOrId}`);
    return data.data;
  },
  async listDepartments(): Promise<Array<{ id: number; code: string; name: string }>> {
    const { data } = await apiClient.get('/public/departments');
    return data.data;
  },
};

export const recruitmentApi = {
  async getDashboard() {
    const { data } = await apiClient.get('/dashboard');
    return data.data as {
      kpis: {
        active_jobs: number;
        total_candidates: number;
        total_applications: number;
        interviews_scheduled: number;
        hired_candidates: number;
        pending_offers: number;
        pending_requisitions: number;
      };
      recruitment_funnel: Array<{ slug: string; name: string; is_terminal: boolean; count: number; percentage: number }>;
      hiring_performance: Array<{ month: string; applications: number; hired: number }>;
      open_vacancies: Array<{
        id: number;
        code: string;
        title: string;
        headcount: number;
        hired_count: number;
        status: string;
        applications_count: number;
        department?: { id: number; code: string; name: string };
      }>;
      upcoming_interviews: Interview[];
      recent_activities: ActivityLog[];
    };
  },
  async getReports(params?: Record<string, unknown>) {
    const { data } = await apiClient.get('/reports', { params });
    return data.data as {
      overview: {
        total_applications: number;
        active_applications: number;
        hired_candidates: number;
        rejected_candidates: number;
        withdrawn_candidates: number;
        hiring_conversion_rate: number;
        avg_time_to_hire_days: number;
        offer_acceptance_rate: number;
      };
      candidates_by_stage: Array<{ slug: string; name: string; count: number; share_percentage: number; is_terminal: boolean }>;
      applications_by_job: Array<{
        job_id: number;
        code: string;
        title: string;
        department?: string;
        status: string;
        headcount: number;
        total_applications: number;
        active: number;
        hired: number;
        rejected: number;
      }>;
      candidate_sources: Array<{ source: string; label: string; applications: number; hired: number; conversion_rate: number }>;
      department_performance: Array<{
        department_id: number;
        code: string;
        department: string;
        open_jobs: number;
        applications: number;
        hired: number;
        rejected: number;
        avg_time_to_hire_days: number;
      }>;
    };
  },
  async listStages(): Promise<RecruitmentStage[]> {
    const { data } = await apiClient.get('/stages');
    return data.data;
  },
  async listDepartments(): Promise<Department[]> {
    const { data } = await apiClient.get('/departments');
    return data.data;
  },
  async listAssignableStaff(): Promise<
    Array<{
      id: number;
      name: string;
      email: string;
      job_title?: string | null;
      role?: { id: number; slug: string; name: string };
      department?: { id: number; code: string; name: string };
    }>
  > {
    const { data } = await apiClient.get('/jobs/assignable-staff');
    return data.data;
  },

  // Requisitions
  async listRequisitions(params?: Record<string, unknown>): Promise<PaginatedResult<JobRequisition>> {
    const { data } = await apiClient.get('/requisitions', { params });
    return { data: data.data, meta: data.meta };
  },
  async createRequisition(payload: Record<string, unknown>): Promise<JobRequisition> {
    const { data } = await apiClient.post('/requisitions', payload);
    return data.data;
  },
  async decideRequisition(id: number, decision: 'approved' | 'rejected', rejection_reason?: string): Promise<JobRequisition> {
    const { data } = await apiClient.patch(`/requisitions/${id}/decision`, { decision, rejection_reason });
    return data.data;
  },

  // Jobs
  async listJobs(params?: Record<string, unknown>): Promise<PaginatedResult<Job>> {
    const { data } = await apiClient.get('/jobs', { params });
    return { data: data.data, meta: data.meta };
  },
  async getJob(id: number | string): Promise<Job> {
    const { data } = await apiClient.get(`/jobs/${id}`);
    return data.data;
  },
  async createJob(payload: Record<string, unknown>): Promise<Job> {
    const { data } = await apiClient.post('/jobs', payload);
    return data.data;
  },
  async updateJob(id: number, payload: Record<string, unknown>): Promise<Job> {
    const { data } = await apiClient.put(`/jobs/${id}`, payload);
    return data.data;
  },
  async deleteDraftJob(id: number): Promise<void> {
    await apiClient.delete(`/jobs/${id}`);
  },
  async changeJobStatus(id: number, action: 'publish' | 'pause' | 'close'): Promise<Job> {
    const { data } = await apiClient.patch(`/jobs/${id}/${action}`);
    return data.data;
  },

  // Candidates & Documents
  async listCandidates(params?: Record<string, unknown>): Promise<PaginatedResult<Candidate>> {
    const { data } = await apiClient.get('/candidates', { params });
    return { data: data.data, meta: data.meta };
  },
  async checkCandidateDuplicate(params: Record<string, unknown>) {
    const { data } = await apiClient.get('/candidates/check-duplicate', { params });
    return data.data as {
      has_exact_email_duplicate: boolean;
      exact_match: Candidate | null;
      potential_duplicates: Candidate[];
    };
  },
  async createCandidate(payload: Record<string, unknown>): Promise<Candidate> {
    const { data } = await apiClient.post('/candidates', payload);
    return data.data;
  },
  async getCandidate(id: number | string): Promise<Candidate> {
    const { data } = await apiClient.get(`/candidates/${id}`);
    return data.data;
  },
  async updateCandidate(id: number, payload: Record<string, unknown>): Promise<Candidate> {
    const { data } = await apiClient.put(`/candidates/${id}`, payload);
    return data.data;
  },
  async uploadCandidateDocument(candidateId: number, file: File, documentType = 'cv', isPrimaryCv = true): Promise<CandidateDocument> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', documentType);
    formData.append('is_primary_cv', isPrimaryCv ? '1' : '0');
    const { data } = await apiClient.post(`/candidates/${candidateId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  },
  async deleteDocument(docId: number): Promise<void> {
    await apiClient.delete(`/documents/${docId}`);
  },

  // Applications & Pipeline
  async listApplications(params?: Record<string, unknown>): Promise<PaginatedResult<Application>> {
    const { data } = await apiClient.get('/applications', { params });
    return { data: data.data, meta: data.meta };
  },
  async getPipelineBoard(jobId?: number | string): Promise<KanbanColumn[]> {
    const { data } = await apiClient.get('/applications/pipeline', { params: jobId ? { job_id: jobId } : undefined });
    return data.data;
  },
  async createApplication(payload: Record<string, unknown>): Promise<Application> {
    const { data } = await apiClient.post('/applications', payload);
    return data.data;
  },
  async getApplication(id: number | string): Promise<Application> {
    const { data } = await apiClient.get(`/applications/${id}`);
    return data.data;
  },
  async transitionStage(id: number, payload: { stage_slug: string; reason?: string; notes?: string }): Promise<Application> {
    const { data } = await apiClient.patch(`/applications/${id}/stage`, payload);
    return data.data;
  },
  async submitRecommendation(id: number, payload: { recommendation: string; notes?: string }): Promise<Application> {
    const { data } = await apiClient.patch(`/applications/${id}/recommendation`, payload);
    return data.data;
  },
  async addApplicationNote(applicationId: number, body: string): Promise<ApplicationNote> {
    const { data } = await apiClient.post(`/applications/${applicationId}/notes`, { body });
    return data.data;
  },
  async deleteApplicationNote(applicationId: number, noteId: number): Promise<void> {
    await apiClient.delete(`/applications/${applicationId}/notes/${noteId}`);
  },

  // Interviews & Evaluations
  async listInterviews(params?: Record<string, unknown>): Promise<PaginatedResult<Interview>> {
    const { data } = await apiClient.get('/interviews', { params });
    return { data: data.data, meta: data.meta };
  },
  async getInterview(id: number | string): Promise<Interview> {
    const { data } = await apiClient.get(`/interviews/${id}`);
    return data.data;
  },
  async scheduleInterview(payload: Record<string, unknown>): Promise<Interview> {
    const { data } = await apiClient.post('/interviews', payload);
    return data.data;
  },
  async updateInterview(id: number, payload: Record<string, unknown>): Promise<Interview> {
    const { data } = await apiClient.put(`/interviews/${id}`, payload);
    return data.data;
  },
  async cancelInterview(id: number, cancellation_reason: string): Promise<Interview> {
    const { data } = await apiClient.patch(`/interviews/${id}/cancel`, { cancellation_reason });
    return data.data;
  },
  async completeInterview(id: number): Promise<Interview> {
    const { data } = await apiClient.patch(`/interviews/${id}/complete`);
    return data.data;
  },
  async submitEvaluation(interviewId: number, payload: Record<string, unknown>): Promise<InterviewEvaluation> {
    const { data } = await apiClient.post(`/interviews/${interviewId}/evaluations`, payload);
    return data.data;
  },

  // Job Offers
  async listOffers(params?: Record<string, unknown>): Promise<PaginatedResult<JobOffer>> {
    const { data } = await apiClient.get('/job-offers', { params });
    return { data: data.data, meta: data.meta };
  },
  async createOffer(payload: Record<string, unknown>): Promise<JobOffer> {
    const { data } = await apiClient.post('/job-offers', payload);
    return data.data;
  },
  async approveOffer(id: number): Promise<JobOffer> {
    const { data } = await apiClient.patch(`/job-offers/${id}/approve`);
    return data.data;
  },
  async sendOffer(id: number): Promise<JobOffer> {
    const { data } = await apiClient.patch(`/job-offers/${id}/send`);
    return data.data;
  },
  async revokeOffer(id: number): Promise<JobOffer> {
    const { data } = await apiClient.patch(`/job-offers/${id}/revoke`);
    return data.data;
  },
};

export const portalApi = {
  async getDashboard() {
    const { data } = await apiClient.get('/portal/dashboard');
    return data.data as {
      summary: {
        total_applications: number;
        active_applications: number;
        upcoming_interviews: number;
        pending_offers: number;
        has_primary_cv: boolean;
      };
      upcoming_interviews: Interview[];
      pending_offers: JobOffer[];
      recent_applications: Application[];
    };
  },
  async getProfile(): Promise<Candidate> {
    const { data } = await apiClient.get('/portal/profile');
    return data.data;
  },
  async updateProfile(payload: Record<string, unknown>): Promise<Candidate> {
    const { data } = await apiClient.put('/portal/profile', payload);
    return data.data;
  },
  async uploadDocument(file: File, documentType = 'cv', isPrimaryCv = true): Promise<CandidateDocument> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', documentType);
    formData.append('is_primary_cv', isPrimaryCv ? '1' : '0');
    const { data } = await apiClient.post('/portal/documents', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  },
  async listApplications(params?: Record<string, unknown>): Promise<PaginatedResult<Application>> {
    const { data } = await apiClient.get('/portal/applications', { params });
    return { data: data.data, meta: data.meta };
  },
  async submitApplication(payload: Record<string, unknown>): Promise<Application> {
    const { data } = await apiClient.post('/portal/applications', payload);
    return data.data;
  },
  async getApplication(id: number | string): Promise<Application> {
    const { data } = await apiClient.get(`/portal/applications/${id}`);
    return data.data;
  },
  async withdrawApplication(id: number, reason?: string): Promise<Application> {
    const { data } = await apiClient.patch(`/portal/applications/${id}/withdraw`, { reason });
    return data.data;
  },
  async listInterviews(): Promise<Interview[]> {
    const { data } = await apiClient.get('/portal/interviews');
    return data.data;
  },
  async respondToOffer(offerId: number, payload: { decision: 'accepted' | 'declined'; decline_reason?: string; candidate_notes?: string }): Promise<JobOffer> {
    const { data } = await apiClient.patch(`/portal/offers/${offerId}/respond`, payload);
    return data.data;
  },
};

export const notificationsApi = {
  async list() {
    const { data } = await apiClient.get('/notifications');
    return data.data as { unread_count: number; items: AppNotification[] };
  },
  async markRead(id: string) {
    await apiClient.patch(`/notifications/${id}/read`);
  },
  async markAllRead() {
    await apiClient.patch('/notifications/read-all');
  },
};

export const adminApi = {
  async listUsers(params?: Record<string, unknown>): Promise<PaginatedResult<User>> {
    const { data } = await apiClient.get('/admin/users', { params });
    return { data: data.data, meta: data.meta };
  },
  async createUser(payload: Record<string, unknown>): Promise<User> {
    const { data } = await apiClient.post('/admin/users', payload);
    return data.data;
  },
  async updateUser(id: number, payload: Record<string, unknown>): Promise<User> {
    const { data } = await apiClient.put(`/admin/users/${id}`, payload);
    return data.data;
  },
  async listRoles(): Promise<Role[]> {
    const { data } = await apiClient.get('/admin/roles');
    return data.data;
  },
  async createRole(payload: { name: string; description?: string; permission_ids?: number[] }): Promise<Role> {
    const { data } = await apiClient.post('/admin/roles', payload);
    return data.data;
  },
  async syncRolePermissions(roleId: number, permission_ids: number[]): Promise<Role> {
    const { data } = await apiClient.put(`/admin/roles/${roleId}/permissions`, { permission_ids });
    return data.data;
  },
  async listPermissions(): Promise<Record<string, Permission[]>> {
    const { data } = await apiClient.get('/admin/permissions');
    return data.data;
  },
  async listDepartments(): Promise<Department[]> {
    const { data } = await apiClient.get('/admin/departments');
    return data.data;
  },
  async createDepartment(payload: Record<string, unknown>): Promise<Department> {
    const { data } = await apiClient.post('/admin/departments', payload);
    return data.data;
  },
  async updateDepartment(id: number, payload: Record<string, unknown>): Promise<Department> {
    const { data } = await apiClient.put(`/admin/departments/${id}`, payload);
    return data.data;
  },
  async getSettings(): Promise<Record<string, unknown>> {
    const { data } = await apiClient.get('/admin/settings');
    return data.data;
  },
  async updateSettings(settings: Record<string, unknown>): Promise<Record<string, unknown>> {
    const { data } = await apiClient.put('/admin/settings', { settings });
    return data.data;
  },
  async listActivityLogs(params?: Record<string, unknown>): Promise<PaginatedResult<ActivityLog>> {
    const { data } = await apiClient.get('/admin/activity-logs', { params });
    return { data: data.data, meta: data.meta };
  },
};
