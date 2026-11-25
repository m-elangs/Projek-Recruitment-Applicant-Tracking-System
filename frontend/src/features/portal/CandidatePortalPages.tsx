import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Briefcase, CalendarClock, CheckCircle2, Download, ExternalLink, MapPin, Upload } from 'lucide-react';
import { portalApi, publicCareersApi } from '../../api/services';
import { downloadPrivateDocument } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatBytes, formatCurrency, formatDate, formatDateTime, formatSalaryRange, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterDropdown,
  Input,
  LoadingState,
  Modal,
  PageHeader,
  Pagination,
  SearchInput,
  StatCard,
  StatusBadge,
  Textarea,
} from '../../components/ui';
import type { JobOffer } from '../../types/models';

export const CareersPage: React.FC = () => {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [employmentType, setEmploymentType] = useState('');

  const { data: departments } = useQuery({
    queryKey: ['public-depts'],
    queryFn: () => publicCareersApi.listDepartments(),
  });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['public-jobs', { page, search, departmentId, employmentType }],
    queryFn: () =>
      publicCareersApi.listJobs({
        page,
        search: search || undefined,
        department_id: departmentId || undefined,
        employment_type: employmentType || undefined,
      }),
  });

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-line bg-surface p-6">
        <span className="text-xs font-semibold uppercase tracking-wider text-brand">Open Roles</span>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">Join TalentFlow Technologies</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted">
          Explore our current openings across engineering, product, and operations. Create your candidate profile once to apply and track progress transparently.
        </p>

        <div className="mt-5 flex flex-wrap items-center gap-3">
          <SearchInput
            value={search}
            onChange={(v) => {
              setSearch(v);
              setPage(1);
            }}
            placeholder="Search role title or location..."
            className="w-full sm:w-72"
          />
          <FilterDropdown
            label="Department"
            value={departmentId}
            onChange={(v) => {
              setDepartmentId(v);
              setPage(1);
            }}
            options={(departments || []).map((d) => ({ value: d.id, label: d.name }))}
          />
          <FilterDropdown
            label="Type"
            value={employmentType}
            onChange={(v) => {
              setEmploymentType(v);
              setPage(1);
            }}
            options={[
              { value: 'full_time', label: 'Full-Time' },
              { value: 'part_time', label: 'Part-Time' },
              { value: 'contract', label: 'Contract' },
              { value: 'internship', label: 'Internship' },
            ]}
          />
        </div>
      </div>

      {isLoading ? (
        <LoadingState message="Loading open positions..." />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (data?.data || []).length === 0 ? (
        <EmptyState title="No open roles match your filter" description="Try broadening your search or clearing filters." />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {(data?.data || []).map((job) => (
              <Link
                key={job.id}
                to={`/careers/jobs/${job.slug}`}
                className="flex flex-col justify-between rounded-lg border border-line bg-surface p-5 hover:border-brand/50 transition-colors"
              >
                <div>
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-medium text-brand">{job.department?.name}</span>
                    <StatusBadge status={job.work_arrangement} />
                  </div>
                  <h2 className="mt-1.5 text-base font-semibold text-ink">{job.title}</h2>
                  <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted">
                    <span className="inline-flex items-center gap-1">
                      <MapPin className="h-3.5 w-3.5" /> {job.location}
                    </span>
                    <span className="inline-flex items-center gap-1">
                      <Briefcase className="h-3.5 w-3.5" /> {humanizeSlug(job.employment_type)}
                    </span>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {job.skills.slice(0, 4).map((s) => (
                      <span key={s.skill_name} className="rounded bg-canvas px-2 py-0.5 text-[11px] text-muted border border-line">
                        {s.skill_name}
                      </span>
                    ))}
                  </div>
                </div>
                <div className="mt-4 flex items-center justify-between border-t border-line pt-3 text-xs">
                  <span className="font-medium text-ink">
                    {job.is_salary_visible ? formatSalaryRange(job.salary_min, job.salary_max, job.salary_currency) : 'Competitive salary'}
                  </span>
                  <span className="font-medium text-brand">View Role &rarr;</span>
                </div>
              </Link>
            ))}
          </div>
          <Pagination meta={data?.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
};

export const PublicJobDetailPage: React.FC = () => {
  const { slug } = useParams();
  const { user, isAuthenticated } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();

  const [applyOpen, setApplyOpen] = useState(false);
  const [coverLetter, setCoverLetter] = useState('');
  const [expectedSalary, setExpectedSalary] = useState('');

  const { data: job, isLoading, error, refetch } = useQuery({
    queryKey: ['public-job', slug],
    queryFn: () => publicCareersApi.getJob(slug!),
  });

  const applyMutation = useMutation({
    mutationFn: () =>
      portalApi.submitApplication({
        job_id: job!.id,
        cover_letter: coverLetter || undefined,
        expected_salary: expectedSalary ? Number(expectedSalary) : undefined,
      }),
    onSuccess: (created) => {
      setApplyOpen(false);
      showToast('success', 'Application submitted', `Application ${created.application_number} received.`);
      navigate(`/portal/applications/${created.id}`);
    },
    onError: (err) => showToast('error', 'Unable to apply', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState message="Loading job details..." />;
  if (error || !job) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  const isCandidate = user?.role?.slug === 'candidate';

  return (
    <div className="space-y-6">
      <PageHeader
        title={job.title}
        description={`${job.department?.name} · ${job.location} · ${humanizeSlug(job.employment_type)} · ${humanizeSlug(job.work_arrangement)}`}
        breadcrumbs={[{ label: 'Careers', to: '/careers' }, { label: job.title }]}
        actions={
          !isAuthenticated ? (
            <Link to="/register">
              <Button>Sign Up to Apply</Button>
            </Link>
          ) : isCandidate ? (
            <Button disabled={!job.is_accepting_applications} onClick={() => setApplyOpen(true)}>
              {job.is_accepting_applications ? 'Apply for this Position' : 'Applications Closed'}
            </Button>
          ) : null
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-5 rounded-lg border border-line bg-surface p-6 lg:col-span-2">
          <div>
            <h2 className="text-sm font-semibold text-ink">About the Role</h2>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.description}</p>
          </div>
          <div className="border-t border-line pt-4">
            <h2 className="text-sm font-semibold text-ink">Requirements</h2>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.requirements}</p>
          </div>
          {job.responsibilities && (
            <div className="border-t border-line pt-4">
              <h2 className="text-sm font-semibold text-ink">Responsibilities</h2>
              <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.responsibilities}</p>
            </div>
          )}
          {job.benefits && (
            <div className="border-t border-line pt-4">
              <h2 className="text-sm font-semibold text-ink">Benefits</h2>
              <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{job.benefits}</p>
            </div>
          )}
        </div>

        <div className="space-y-4">
          <div className="rounded-lg border border-line bg-surface p-5 text-xs space-y-2.5">
            <h3 className="font-semibold uppercase tracking-wider text-muted">Role Summary</h3>
            <div className="flex justify-between"><span className="text-muted">Department</span><span className="font-medium">{job.department?.name}</span></div>
            <div className="flex justify-between"><span className="text-muted">Location</span><span className="font-medium">{job.location}</span></div>
            <div className="flex justify-between"><span className="text-muted">Level</span><span className="font-medium">{humanizeSlug(job.experience_level)}</span></div>
            <div className="flex justify-between">
              <span className="text-muted">Compensation</span>
              <span className="font-medium">
                {job.is_salary_visible ? formatSalaryRange(job.salary_min, job.salary_max, job.salary_currency) : 'Competitive'}
              </span>
            </div>
            <div className="flex justify-between"><span className="text-muted">Deadline</span><span className="font-medium">{formatDate(job.closes_at)}</span></div>
          </div>
        </div>
      </div>

      <Modal
        isOpen={applyOpen}
        onClose={() => setApplyOpen(false)}
        title={`Apply — ${job.title}`}
        description="Your primary CV from My Profile will be attached automatically."
        footer={
          <>
            <Button variant="outline" onClick={() => setApplyOpen(false)}>Cancel</Button>
            <Button isLoading={applyMutation.isPending} onClick={() => applyMutation.mutate()}>
              Submit Application
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <Input
            label="Expected Annual Salary (Optional)"
            type="number"
            value={expectedSalary}
            onChange={(e) => setExpectedSalary(e.target.value)}
          />
          <Textarea
            label="Cover Letter / Introduction (Optional)"
            rows={4}
            placeholder="Briefly highlight your relevant experience..."
            value={coverLetter}
            onChange={(e) => setCoverLetter(e.target.value)}
          />
          <p className="text-xs text-muted">
            Need to upload or update your CV first?{' '}
            <Link to="/portal/profile" className="font-medium text-brand hover:underline">
              Go to My Profile & CV
            </Link>
          </p>
        </div>
      </Modal>
    </div>
  );
};

export const CandidateDashboardPage: React.FC = () => {
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [declineOffer, setDeclineOffer] = useState<JobOffer | null>(null);
  const [declineReason, setDeclineReason] = useState('');

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-dashboard'],
    queryFn: () => portalApi.getDashboard(),
  });

  const respondMutation = useMutation({
    mutationFn: ({ offerId, decision, reason }: { offerId: number; decision: 'accepted' | 'declined'; reason?: string }) =>
      portalApi.respondToOffer(offerId, { decision, decline_reason: reason }),
    onSuccess: (_, vars) => {
      setDeclineOffer(null);
      setDeclineReason('');
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['portal-applications'] });
      showToast(
        'success',
        vars.decision === 'accepted' ? 'Offer Accepted!' : 'Offer Declined',
        vars.decision === 'accepted' ? 'Congratulations! Your status has been updated to Hired.' : 'Your response has been sent.'
      );
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState message="Loading your candidate portal..." />;
  if (error || !data) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Candidate Dashboard"
        description="Track your active applications, scheduled interviews, and job offers."
        actions={
          <Link to="/careers">
            <Button size="sm">Browse Open Positions</Button>
          </Link>
        }
      />

      {!data.summary.has_primary_cv && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warning/40 bg-warning-subtle px-4 py-3 text-xs text-warning">
          <span>You have not uploaded a primary CV yet. Upload your CV to apply for open roles.</span>
          <Link to="/portal/profile">
            <Button size="sm" variant="outline">Upload CV Now</Button>
          </Link>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Active Applications" value={data.summary.active_applications} icon={<Briefcase className="h-4 w-4" />} />
        <StatCard label="Total Submitted" value={data.summary.total_applications} />
        <StatCard label="Upcoming Interviews" value={data.summary.upcoming_interviews} icon={<CalendarClock className="h-4 w-4" />} />
        <StatCard label="Pending Offers" value={data.summary.pending_offers} icon={<CheckCircle2 className="h-4 w-4 text-success" />} />
      </div>

      {/* Pending Job Offers Requiring Candidate Response */}
      {data.pending_offers.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-semibold text-ink">Official Job Offers Awaiting Your Response</h2>
          {data.pending_offers.map((offer) => (
            <div key={offer.id} className="rounded-lg border border-brand/30 bg-brand-subtle/30 p-5">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <span className="text-xs font-semibold text-brand">{offer.offer_number}</span>
                  <h3 className="text-base font-semibold text-ink">{offer.position_title}</h3>
                  <p className="mt-1 text-xs text-muted">
                    Compensation: <strong className="text-ink">{formatCurrency(offer.base_salary, offer.salary_currency)} / {offer.pay_frequency}</strong>
                    {offer.bonus_amount ? ` + ${formatCurrency(offer.bonus_amount, offer.salary_currency)} bonus` : ''} · Start: {formatDate(offer.proposed_start_date)} · Expires: {formatDateTime(offer.expires_at)}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    isLoading={respondMutation.isPending}
                    onClick={() => respondMutation.mutate({ offerId: offer.id, decision: 'accepted' })}
                  >
                    Accept Offer
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => setDeclineOffer(offer)}>
                    Decline
                  </Button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-line bg-surface p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-ink">Recent Applications</h2>
            <Link to="/portal/applications" className="text-xs font-medium text-brand hover:underline">
              View all
            </Link>
          </div>
          {data.recent_applications.length === 0 ? (
            <EmptyState title="No applications yet" description="Explore open roles and submit your first application." />
          ) : (
            <div className="divide-y divide-line">
              {data.recent_applications.map((a) => (
                <Link
                  key={a.id}
                  to={`/portal/applications/${a.id}`}
                  className="flex items-center justify-between py-3 hover:bg-canvas/50"
                >
                  <div>
                    <p className="text-sm font-medium text-ink">{a.job?.title}</p>
                    <p className="text-xs text-muted">{a.application_number} · Applied {formatDate(a.applied_at)}</p>
                  </div>
                  <StatusBadge status={a.current_stage?.slug} label={a.current_stage?.name} />
                </Link>
              ))}
            </div>
          )}
        </div>

        <div className="rounded-lg border border-line bg-surface p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-ink">Upcoming Interviews</h2>
            <Link to="/portal/interviews" className="text-xs font-medium text-brand hover:underline">
              Full schedule
            </Link>
          </div>
          {data.upcoming_interviews.length === 0 ? (
            <p className="text-xs text-muted">No upcoming interviews scheduled right now.</p>
          ) : (
            <div className="space-y-3">
              {data.upcoming_interviews.map((iv) => (
                <div key={iv.id} className="rounded-md border border-line p-3.5">
                  <p className="text-sm font-medium text-ink">{iv.title}</p>
                  <p className="text-xs text-muted">{iv.application?.job?.title} · {formatDateTime(iv.scheduled_at)}</p>
                  {iv.meeting_url && (
                    <a
                      href={iv.meeting_url}
                      target="_blank"
                      rel="noreferrer"
                      className="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                    >
                      Join Meeting <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <Modal
        isOpen={Boolean(declineOffer)}
        onClose={() => setDeclineOffer(null)}
        title="Decline Job Offer"
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setDeclineOffer(null)}>Cancel</Button>
            <Button
              variant="danger"
              isLoading={respondMutation.isPending}
              onClick={() =>
                declineOffer && respondMutation.mutate({ offerId: declineOffer.id, decision: 'declined', reason: declineReason })
              }
            >
              Confirm Decline
            </Button>
          </>
        }
      >
        <Textarea
          label="Reason for Declining"
          required
          rows={3}
          value={declineReason}
          onChange={(e) => setDeclineReason(e.target.value)}
        />
      </Modal>
    </div>
  );
};

export const MyProfilePage: React.FC = () => {
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [uploading, setUploading] = useState(false);

  const { data: profile, isLoading } = useQuery({
    queryKey: ['portal-profile'],
    queryFn: () => portalApi.getProfile(),
  });

  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    location: '',
    headline: '',
    current_company: '',
    current_title: '',
    years_of_experience: '',
    linkedin_url: '',
    portfolio_url: '',
    summary: '',
    skills_csv: '',
  });

  useEffect(() => {
    if (profile) {
      setForm({
        first_name: profile.first_name || '',
        last_name: profile.last_name || '',
        phone: profile.phone || '',
        location: profile.location || '',
        headline: profile.headline || '',
        current_company: profile.current_company || '',
        current_title: profile.current_title || '',
        years_of_experience: profile.years_of_experience ? String(profile.years_of_experience) : '',
        linkedin_url: profile.linkedin_url || '',
        portfolio_url: profile.portfolio_url || '',
        summary: profile.summary || '',
        skills_csv: (profile.skills || []).map((s) => s.skill_name).join(', '),
      });
    }
  }, [profile]);

  const updateMutation = useMutation({
    mutationFn: () =>
      portalApi.updateProfile({
        first_name: form.first_name,
        last_name: form.last_name,
        phone: form.phone || null,
        location: form.location || null,
        headline: form.headline || null,
        current_company: form.current_company || null,
        current_title: form.current_title || null,
        years_of_experience: form.years_of_experience ? Number(form.years_of_experience) : null,
        linkedin_url: form.linkedin_url || null,
        portfolio_url: form.portfolio_url || null,
        summary: form.summary || null,
        skills: form.skills_csv
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-profile'] });
      showToast('success', 'Profile saved', 'Your candidate profile has been updated.');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  const handleCvUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      await portalApi.uploadDocument(file, 'cv', true);
      queryClient.invalidateQueries({ queryKey: ['portal-profile'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
      showToast('success', 'CV uploaded', `${file.name} is now set as your primary CV.`);
    } catch (err) {
      showToast('error', 'Upload failed', extractApiError(err).message);
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  };

  if (isLoading) return <LoadingState />;

  return (
    <div className="space-y-6">
      <PageHeader title="My Profile & Private CV" description="Keep your professional details and resume up to date." />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            updateMutation.mutate();
          }}
          className="space-y-4 rounded-lg border border-line bg-surface p-6 lg:col-span-2"
        >
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input label="First Name" required value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
            <Input label="Last Name" required value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
            <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
            <Input label="Location" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
            <Input label="Current Title" value={form.current_title} onChange={(e) => setForm({ ...form, current_title: e.target.value })} />
            <Input label="Current Company" value={form.current_company} onChange={(e) => setForm({ ...form, current_company: e.target.value })} />
            <Input label="Years of Experience" type="number" step="0.5" value={form.years_of_experience} onChange={(e) => setForm({ ...form, years_of_experience: e.target.value })} />
            <Input label="Headline" value={form.headline} onChange={(e) => setForm({ ...form, headline: e.target.value })} />
            <Input label="LinkedIn URL" type="url" value={form.linkedin_url} onChange={(e) => setForm({ ...form, linkedin_url: e.target.value })} />
            <Input label="Portfolio URL" type="url" value={form.portfolio_url} onChange={(e) => setForm({ ...form, portfolio_url: e.target.value })} />
          </div>
          <Input
            label="Skills (comma-separated)"
            placeholder="React, TypeScript, Laravel, MySQL"
            value={form.skills_csv}
            onChange={(e) => setForm({ ...form, skills_csv: e.target.value })}
          />
          <Textarea label="Professional Summary" rows={4} value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} />
          <div className="flex justify-end">
            <Button type="submit" isLoading={updateMutation.isPending}>
              Save Profile
            </Button>
          </div>
        </form>

        <div className="rounded-lg border border-line bg-surface p-5 h-fit space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-ink">CV & Supporting Documents</h2>
            <label className="inline-flex cursor-pointer items-center gap-1 rounded border border-line bg-canvas px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-brand-subtle hover:text-brand">
              <Upload className="h-3.5 w-3.5" />
              {uploading ? 'Uploading...' : 'Upload CV'}
              <input type="file" accept=".pdf,.doc,.docx" className="hidden" onChange={handleCvUpload} disabled={uploading} />
            </label>
          </div>
          <p className="text-xs text-muted">Accepted formats: PDF, DOC, DOCX up to 5 MB. Stored privately.</p>
          <div className="space-y-2">
            {(profile?.documents || []).map((doc) => (
              <div key={doc.id} className="flex items-center justify-between rounded-md border border-line p-2.5">
                <div className="min-w-0">
                  <p className="truncate text-xs font-medium text-ink">{doc.original_name}</p>
                  <p className="text-[11px] text-muted">
                    {doc.is_primary_cv ? 'Primary CV · ' : ''}
                    {formatBytes(doc.file_size_bytes)}
                  </p>
                </div>
                <Button size="sm" variant="ghost" onClick={() => downloadPrivateDocument(doc.id, doc.original_name)}>
                  <Download className="h-4 w-4 text-brand" />
                </Button>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export const MyApplicationsPage: React.FC = () => {
  const navigate = useNavigate();
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-applications'],
    queryFn: () => portalApi.listApplications(),
  });

  return (
    <div>
      <PageHeader title="My Applications" description="Track the live status of every role you have applied for." />
      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <DataTable
          data={data?.data || []}
          rowKey={(a) => a.id}
          onRowClick={(a) => navigate(`/portal/applications/${a.id}`)}
          columns={[
            {
              key: 'job',
              header: 'Position',
              render: (a) => (
                <div>
                  <div className="font-medium text-ink">{a.job?.title}</div>
                  <div className="text-xs text-muted">{a.application_number} · {a.job?.location}</div>
                </div>
              ),
            },
            { key: 'stage', header: 'Current Status', render: (a) => <StatusBadge status={a.current_stage?.slug} label={a.current_stage?.name} /> },
            { key: 'applied', header: 'Submitted', render: (a) => <span className="text-xs text-muted">{formatDate(a.applied_at)}</span> },
          ]}
        />
      )}
    </div>
  );
};

export const CandidateApplicationDetailPage: React.FC = () => {
  const { id } = useParams();
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const { data: app, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-application', id],
    queryFn: () => portalApi.getApplication(id!),
  });

  const withdrawMutation = useMutation({
    mutationFn: () => portalApi.withdrawApplication(Number(id), 'Withdrawn by candidate via portal'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-application', id] });
      showToast('info', 'Application withdrawn');
    },
    onError: (err) => showToast('error', 'Failed', extractApiError(err).message),
  });

  if (isLoading) return <LoadingState />;
  if (error || !app) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={app.job?.title || 'Application'}
        description={`${app.application_number} · Submitted ${formatDateTime(app.applied_at)}`}
        breadcrumbs={[{ label: 'My Applications', to: '/portal/applications' }, { label: app.application_number }]}
        actions={
          <>
            <StatusBadge status={app.current_stage?.slug} label={app.current_stage?.name} />
            {app.is_active && (
              <Button size="sm" variant="outline" isLoading={withdrawMutation.isPending} onClick={() => withdrawMutation.mutate()}>
                Withdraw Application
              </Button>
            )}
          </>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-line bg-surface p-5">
          <h2 className="mb-3 text-sm font-semibold text-ink">Status Timeline</h2>
          <div className="space-y-3">
            {(app.histories || []).map((h) => (
              <div key={h.id} className="border-l-2 border-brand/30 pl-3 text-xs">
                <p className="font-medium text-ink">{h.to_stage?.name}</p>
                <p className="text-muted">{formatDateTime(h.created_at)}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-lg border border-line bg-surface p-5">
          <h2 className="mb-3 text-sm font-semibold text-ink">Scheduled Interviews</h2>
          {(app.interviews || []).length === 0 ? (
            <p className="text-xs text-muted">No interviews scheduled yet.</p>
          ) : (
            <div className="space-y-3">
              {(app.interviews || []).map((iv) => (
                <div key={iv.id} className="rounded-md border border-line p-3 text-xs space-y-1">
                  <div className="flex justify-between">
                    <span className="font-semibold text-ink">{iv.title}</span>
                    <StatusBadge status={iv.status} />
                  </div>
                  <p className="text-muted">{formatDateTime(iv.scheduled_at)} ({iv.duration_minutes} mins)</p>
                  {iv.meeting_url && (
                    <a href={iv.meeting_url} target="_blank" rel="noreferrer" className="text-brand hover:underline inline-flex items-center gap-1">
                      Meeting Link <ExternalLink className="h-3 w-3" />
                    </a>
                  )}
                  {iv.location && <p className="text-ink">Location: {iv.location}</p>}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export const CandidateInterviewsPage: React.FC = () => {
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-interviews'],
    queryFn: () => portalApi.listInterviews(),
  });

  return (
    <div>
      <PageHeader title="My Interview Schedule" description="View your upcoming and completed interview sessions." />
      {isLoading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (data || []).length === 0 ? (
        <EmptyState title="No interviews scheduled" description="You will be notified here once a recruiter schedules an interview." />
      ) : (
        <div className="space-y-3">
          {(data || []).map((iv) => (
            <div key={iv.id} className="rounded-lg border border-line bg-surface p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-semibold text-ink">{iv.title}</h3>
                  <StatusBadge status={iv.status} />
                </div>
                <p className="mt-1 text-xs text-muted">
                  {iv.application?.job?.title} · {formatDateTime(iv.scheduled_at)} ({iv.duration_minutes} mins) · {humanizeSlug(iv.mode)}
                </p>
                {iv.candidate_instructions && <p className="mt-1.5 text-xs text-ink">{iv.candidate_instructions}</p>}
              </div>
              {iv.meeting_url && iv.status !== 'cancelled' && (
                <a href={iv.meeting_url} target="_blank" rel="noreferrer">
                  <Button size="sm" variant="secondary">Join Online Interview</Button>
                </a>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
