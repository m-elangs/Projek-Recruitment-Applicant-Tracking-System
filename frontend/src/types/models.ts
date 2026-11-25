export type RoleSlug = 'system_admin' | 'recruiter' | 'hiring_manager' | 'candidate';

export interface Role {
  id: number;
  slug: RoleSlug | string;
  name: string;
  description?: string | null;
  is_system?: boolean;
  users_count?: number;
  permissions?: Permission[];
}

export interface Permission {
  id: number;
  slug: string;
  module: string;
  name: string;
  description?: string | null;
}

export interface Department {
  id: number;
  code: string;
  name: string;
  head_user_id?: number | null;
  description?: string | null;
  is_active: boolean;
  users_count?: number;
  jobs_count?: number;
  head?: { id: number; name: string; email: string; job_title?: string | null } | null;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  job_title?: string | null;
  is_active: boolean;
  role: { id: number; slug: RoleSlug | string; name: string } | null;
  department: { id: number; code: string; name: string } | null;
  permissions: string[];
  redirect_to: string;
  candidate_id?: number | null;
  unread_notifications_count?: number;
  last_login_at?: string | null;
  created_at?: string;
}

export interface JobRequisition {
  id: number;
  requisition_code: string;
  department_id: number;
  requested_by: number;
  approved_by?: number | null;
  title: string;
  headcount: number;
  employment_type: 'full_time' | 'part_time' | 'contract' | 'internship';
  work_arrangement: 'onsite' | 'hybrid' | 'remote';
  location: string;
  budget_min?: number | string | null;
  budget_max?: number | string | null;
  currency: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  target_start_date?: string | null;
  justification: string;
  requirements?: string | null;
  status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'cancelled';
  rejection_reason?: string | null;
  approved_at?: string | null;
  created_at: string;
  department?: Department;
  requester?: { id: number; name: string; email: string };
  approver?: { id: number; name: string; email: string } | null;
  jobs_count?: number;
}

export interface JobSkill {
  id?: number;
  skill_name: string;
  is_required: boolean;
}

export interface Job {
  id: number;
  code: string;
  slug: string;
  requisition_id?: number | null;
  title: string;
  department: { id: number; code: string; name: string } | null;
  recruiter?: { id: number; name: string; email: string } | null;
  hiring_manager?: { id: number; name: string; email: string } | null;
  employment_type: 'full_time' | 'part_time' | 'contract' | 'internship';
  work_arrangement: 'onsite' | 'hybrid' | 'remote';
  location: string;
  experience_level: 'entry' | 'junior' | 'mid' | 'senior' | 'lead' | 'executive';
  description: string;
  requirements: string;
  responsibilities?: string | null;
  benefits?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  salary_currency: string;
  is_salary_visible: boolean;
  headcount: number;
  hired_count?: number | null;
  status: 'draft' | 'published' | 'paused' | 'closed';
  is_accepting_applications: boolean;
  skills: JobSkill[];
  applications_count?: number | null;
  active_applications_count?: number | null;
  published_at?: string | null;
  closes_at?: string | null;
  closed_at?: string | null;
  created_at?: string;
}

export interface CandidateSkill {
  id?: number;
  skill_name: string;
  proficiency: 'beginner' | 'intermediate' | 'advanced' | 'expert';
}

export interface CandidateExperience {
  id?: number;
  company_name: string;
  job_title: string;
  employment_type?: string | null;
  location?: string | null;
  start_date: string;
  end_date?: string | null;
  is_current: boolean;
  description?: string | null;
}

export interface CandidateEducation {
  id?: number;
  institution: string;
  degree: string;
  field_of_study: string;
  start_date: string;
  end_date?: string | null;
  grade?: string | null;
  description?: string | null;
}

export interface CandidateDocument {
  id: number;
  candidate_id: number;
  document_type: 'cv' | 'cover_letter' | 'portfolio' | 'certificate' | 'other';
  original_name: string;
  mime_type: string;
  file_size_bytes: number;
  is_primary_cv: boolean;
  created_at?: string;
}

export interface Candidate {
  id: number;
  user_id?: number | null;
  first_name: string;
  last_name: string;
  full_name?: string;
  email: string;
  phone?: string | null;
  location?: string | null;
  headline?: string | null;
  summary?: string | null;
  current_company?: string | null;
  current_title?: string | null;
  years_of_experience?: number | string | null;
  linkedin_url?: string | null;
  portfolio_url?: string | null;
  expected_salary?: number | string | null;
  salary_currency?: string;
  notice_period_days?: number | null;
  source: 'careers_portal' | 'linkedin' | 'referral' | 'agency' | 'direct_sourced' | 'other';
  applications_count?: number;
  skills?: CandidateSkill[];
  experiences?: CandidateExperience[];
  educations?: CandidateEducation[];
  documents?: CandidateDocument[];
  primary_cv?: CandidateDocument | null;
  applications?: Application[];
  created_at?: string;
}

export interface RecruitmentStage {
  id: number;
  slug: string;
  name: string;
  display_order: number;
  is_terminal: boolean;
  allowed_next_stages?: string[];
}

export interface ApplicationHistory {
  id: number;
  from_stage?: { slug: string; name: string } | null;
  to_stage?: { slug: string; name: string } | null;
  actor?: { id: number; name: string } | null;
  reason?: string | null;
  notes?: string | null;
  time_in_previous_stage_seconds?: number | null;
  created_at: string;
}

export interface ApplicationNote {
  id: number;
  body: string;
  author?: { id: number; name: string; job_title?: string | null } | null;
  created_at: string;
}

export interface InterviewEvaluation {
  id: number;
  interview_id: number;
  interviewer_user_id: number;
  technical_skills_rating: number;
  communication_rating: number;
  problem_solving_rating: number;
  relevant_experience_rating: number;
  cultural_fit_rating: number;
  average_rating: number | string;
  interview_notes?: string | null;
  strengths: string;
  weaknesses: string;
  overall_feedback: string;
  recommendation: 'strong_yes' | 'yes' | 'neutral' | 'no' | 'strong_no';
  submitted_at: string;
  interviewer?: { id: number; name: string; email?: string; job_title?: string | null };
}

export interface Interview {
  id: number;
  application_id: number;
  title: string;
  round_number: number;
  interview_type: 'screening' | 'technical' | 'behavioral' | 'panel' | 'assessment_review' | 'final';
  mode: 'online' | 'onsite';
  scheduled_at: string;
  duration_minutes: number;
  timezone: string;
  meeting_url?: string | null;
  location?: string | null;
  candidate_instructions?: string | null;
  internal_agenda?: string | null;
  status: 'scheduled' | 'rescheduled' | 'completed' | 'cancelled' | 'no_show';
  cancellation_reason?: string | null;
  application?: {
    id: number;
    application_number?: string;
    job?: { id: number; code: string; title: string; location?: string; department?: { id: number; name: string } };
    candidate?: { id: number; first_name: string; last_name: string; email: string; phone?: string | null; current_title?: string | null };
    current_stage?: RecruitmentStage;
  };
  panel_users?: Array<{ id: number; name: string; email?: string; job_title?: string | null; is_lead?: boolean; pivot?: { is_lead?: boolean } }>;
  evaluations?: InterviewEvaluation[];
}

export interface JobOffer {
  id: number;
  offer_number: string;
  application_id: number;
  department_id: number;
  position_title: string;
  employment_type: 'full_time' | 'part_time' | 'contract' | 'internship';
  base_salary: number | string;
  bonus_amount?: number | string | null;
  salary_currency: string;
  pay_frequency: 'monthly' | 'annual';
  benefits_summary?: string | null;
  proposed_start_date: string;
  expires_at: string;
  status: 'draft' | 'pending_approval' | 'sent' | 'accepted' | 'declined' | 'expired' | 'revoked';
  sent_at?: string | null;
  approved_at?: string | null;
  responded_at?: string | null;
  decline_reason?: string | null;
  candidate_notes?: string | null;
  created_at?: string;
  department?: { id: number; code: string; name: string };
  creator?: { id: number; name: string };
  approver?: { id: number; name: string } | null;
  application?: {
    id: number;
    application_number?: string;
    candidate?: { id: number; first_name: string; last_name: string; email: string };
    job?: { id: number; code: string; title: string };
    current_stage?: RecruitmentStage;
  };
}

export interface Application {
  id: number;
  application_number: string;
  job_id: number;
  candidate_id: number;
  source: string;
  cover_letter?: string | null;
  expected_salary?: number | null;
  available_from?: string | null;
  is_active: boolean;
  rejection_reason?: string | null;
  withdrawal_reason?: string | null;
  hiring_manager_recommendation?: string | null;
  hiring_manager_notes?: string | null;
  applied_at: string;
  stage_updated_at: string;
  hired_at?: string | null;
  rejected_at?: string | null;
  withdrawn_at?: string | null;
  current_stage: RecruitmentStage | null;
  job?: {
    id: number;
    code: string;
    slug?: string | null;
    title: string;
    location: string;
    employment_type: string;
    work_arrangement?: string | null;
    department?: { id: number; name: string } | null;
  } | null;
  candidate?: {
    id: number;
    full_name: string;
    first_name: string;
    last_name: string;
    email: string;
    phone?: string | null;
    location?: string | null;
    current_title?: string | null;
    current_company?: string | null;
    years_of_experience?: number | string | null;
    skills?: CandidateSkill[];
    experiences?: CandidateExperience[];
    educations?: CandidateEducation[];
  } | null;
  cv_document?: CandidateDocument | null;
  histories?: ApplicationHistory[];
  interviews?: Interview[];
  offers?: JobOffer[];
  notes?: ApplicationNote[];
}

export interface KanbanColumn {
  stage: RecruitmentStage;
  count: number;
  applications: Array<{
    id: number;
    application_number: string;
    job: { id: number; code: string; title: string };
    candidate: {
      id: number;
      full_name: string;
      email: string;
      current_title?: string | null;
      current_company?: string | null;
      years_of_experience?: number | string | null;
      skills: string[];
    };
    hiring_manager_recommendation?: string;
    average_evaluation_rating?: number | null;
    applied_at: string;
    stage_updated_at: string;
  }>;
}

export interface ActivityLog {
  id: number;
  actor_user_id?: number | null;
  actor_role?: string | null;
  event_type: string;
  auditable_type?: string;
  auditable_id?: number;
  description: string;
  old_values?: Record<string, unknown> | null;
  new_values?: Record<string, unknown> | null;
  ip_address?: string | null;
  created_at: string;
  actor?: { id: number; name: string; email: string } | null;
}

export interface AppNotification {
  id: string;
  type: string;
  data: {
    category: string;
    title: string;
    message: string;
    action_url?: string;
    application_id?: number;
    interview_id?: number;
    offer_id?: number;
  };
  read_at?: string | null;
  created_at: string;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedResult<T> {
  data: T[];
  meta: PaginationMeta;
}
