import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Download, Plus, Upload } from 'lucide-react';
import { recruitmentApi } from '../../api/services';
import { downloadPrivateDocument } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { extractApiError, formatBytes, formatDate, humanizeSlug } from '../../lib/formatters';
import {
  Button,
  DataTable,
  Drawer,
  ErrorState,
  FilterDropdown,
  Input,
  LoadingState,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  StatusBadge,
  Textarea,
} from '../../components/ui';
import type { Candidate } from '../../types/models';

export const CandidatesListPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [source, setSource] = useState('');
  const [skill, setSkill] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);

  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    location: '',
    current_title: '',
    current_company: '',
    years_of_experience: '',
    source: 'direct_sourced',
    skills_csv: '',
    summary: '',
  });
  const [duplicateInfo, setDuplicateInfo] = useState<{
    has_exact_email_duplicate: boolean;
    potential_duplicates: Candidate[];
  } | null>(null);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['candidates', { page, search, source, skill }],
    queryFn: () =>
      recruitmentApi.listCandidates({
        page,
        search: search || undefined,
        source: source || undefined,
        skill: skill || undefined,
      }),
  });

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => recruitmentApi.createCandidate(payload),
    onSuccess: (created) => {
      setDrawerOpen(false);
      setDuplicateInfo(null);
      queryClient.invalidateQueries({ queryKey: ['candidates'] });
      showToast('success', 'Candidate added', `${created.first_name} ${created.last_name} added to directory.`);
      navigate(`/candidates/${created.id}`);
    },
    onError: (err) => showToast('error', 'Failed to create candidate', extractApiError(err).message),
  });

  const runDuplicateCheck = async () => {
    if (!form.email && !form.phone) return;
    try {
      const res = await recruitmentApi.checkCandidateDuplicate({
        email: form.email || undefined,
        phone: form.phone || undefined,
        first_name: form.first_name || undefined,
        last_name: form.last_name || undefined,
      });
      setDuplicateInfo(res);
    } catch {
      // ignore
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createMutation.mutate({
      first_name: form.first_name,
      last_name: form.last_name,
      email: form.email,
      phone: form.phone || null,
      location: form.location || null,
      current_title: form.current_title || null,
      current_company: form.current_company || null,
      years_of_experience: form.years_of_experience ? Number(form.years_of_experience) : null,
      source: form.source,
      summary: form.summary || null,
      skills: form.skills_csv
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean),
    });
  };

  return (
    <div>
      <PageHeader
        title="Candidate Directory"
        description="Search talent profiles, skills, private resumes, and cross-job application history."
        actions={
          hasPermission('candidates.manage') && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setDrawerOpen(true)}>
              Add Candidate
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onChange={(v) => {
            setSearch(v);
            setPage(1);
          }}
          placeholder="Search name, email, company, title..."
          className="w-full sm:w-72"
        />
        <Input
          placeholder="Filter by skill (e.g. React)"
          value={skill}
          onChange={(e) => {
            setSkill(e.target.value);
            setPage(1);
          }}
          className="w-48"
        />
        <FilterDropdown
          label="Source"
          value={source}
          onChange={(v) => {
            setSource(v);
            setPage(1);
          }}
          options={[
            { value: 'careers_portal', label: 'Careers Portal' },
            { value: 'linkedin', label: 'LinkedIn' },
            { value: 'referral', label: 'Referral' },
            { value: 'direct_sourced', label: 'Direct Sourced' },
            { value: 'agency', label: 'Agency' },
          ]}
        />
      </div>

      {isLoading ? (
        <LoadingState message="Loading candidates..." />
      ) : error ? (
        <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />
      ) : (
        <>
          <DataTable
            data={data?.data || []}
            rowKey={(c) => c.id}
            onRowClick={(c) => navigate(`/candidates/${c.id}`)}
            columns={[
              {
                key: 'name',
                header: 'Candidate',
                render: (c) => (
                  <div>
                    <div className="font-medium text-ink">
                      {c.first_name} {c.last_name}
                    </div>
                    <div className="text-xs text-muted">{c.email}</div>
                  </div>
                ),
              },
              {
                key: 'role',
                header: 'Current Role',
                render: (c) => (
                  <div className="text-xs">
                    <div className="font-medium text-ink">{c.current_title || '—'}</div>
                    <div className="text-muted">{c.current_company || c.location || '—'}</div>
                  </div>
                ),
              },
              {
                key: 'skills',
                header: 'Skills',
                render: (c) => (
                  <div className="flex flex-wrap gap-1">
                    {(c.skills || []).slice(0, 3).map((s) => (
                      <span key={s.skill_name} className="rounded bg-canvas px-2 py-0.5 text-[11px] text-ink border border-line">
                        {s.skill_name}
                      </span>
                    ))}
                  </div>
                ),
              },
              { key: 'source', header: 'Source', render: (c) => <span className="text-xs text-muted">{humanizeSlug(c.source)}</span> },
              {
                key: 'apps',
                header: 'Applications',
                render: (c) => <span className="text-xs font-medium text-ink">{c.applications_count ?? 0}</span>,
              },
            ]}
          />
          <Pagination meta={data?.meta} onPageChange={setPage} />
        </>
      )}

      <Drawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Add Sourced Candidate"
        description="Duplicate emails and matching phone numbers are checked automatically."
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {duplicateInfo && (duplicateInfo.has_exact_email_duplicate || duplicateInfo.potential_duplicates.length > 0) && (
            <div className="rounded-md border border-warning/40 bg-warning-subtle p-3 text-xs text-warning">
              <div className="flex items-center gap-1.5 font-semibold">
                <AlertTriangle className="h-4 w-4" />
                {duplicateInfo.has_exact_email_duplicate
                  ? 'Exact email match already exists in TalentFlow!'
                  : 'Potential duplicate candidate(s) detected:'}
              </div>
              <ul className="mt-1.5 list-disc pl-5 text-ink">
                {duplicateInfo.potential_duplicates.map((dup) => (
                  <li key={dup.id}>
                    <Link to={`/candidates/${dup.id}`} className="underline">
                      {dup.first_name} {dup.last_name} ({dup.email})
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3">
            <Input
              label="First Name"
              required
              value={form.first_name}
              onChange={(e) => setForm({ ...form, first_name: e.target.value })}
            />
            <Input
              label="Last Name"
              required
              value={form.last_name}
              onChange={(e) => setForm({ ...form, last_name: e.target.value })}
            />
          </div>
          <Input
            label="Email"
            type="email"
            required
            value={form.email}
            onBlur={runDuplicateCheck}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Phone"
              value={form.phone}
              onBlur={runDuplicateCheck}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
            />
            <Input
              label="Location"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Current Title"
              value={form.current_title}
              onChange={(e) => setForm({ ...form, current_title: e.target.value })}
            />
            <Input
              label="Current Company"
              value={form.current_company}
              onChange={(e) => setForm({ ...form, current_company: e.target.value })}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Years of Experience"
              type="number"
              step="0.5"
              value={form.years_of_experience}
              onChange={(e) => setForm({ ...form, years_of_experience: e.target.value })}
            />
            <Select
              label="Source"
              value={form.source}
              onChange={(e) => setForm({ ...form, source: e.target.value })}
              options={[
                { value: 'direct_sourced', label: 'Direct Sourced' },
                { value: 'linkedin', label: 'LinkedIn' },
                { value: 'referral', label: 'Referral' },
                { value: 'agency', label: 'Agency' },
                { value: 'other', label: 'Other' },
              ]}
            />
          </div>
          <Input
            label="Skills (comma-separated)"
            placeholder="React, Laravel, MySQL"
            value={form.skills_csv}
            onChange={(e) => setForm({ ...form, skills_csv: e.target.value })}
          />
          <Textarea
            label="Summary"
            rows={3}
            value={form.summary}
            onChange={(e) => setForm({ ...form, summary: e.target.value })}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createMutation.isPending}>
              Save Candidate
            </Button>
          </div>
        </form>
      </Drawer>
    </div>
  );
};

export const CandidateDetailPage: React.FC = () => {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [uploading, setUploading] = useState(false);

  const { data: candidate, isLoading, error, refetch } = useQuery({
    queryKey: ['candidate', id],
    queryFn: () => recruitmentApi.getCandidate(id!),
  });

  const handleCvUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !candidate) return;
    setUploading(true);
    try {
      await recruitmentApi.uploadCandidateDocument(candidate.id, file, 'cv', true);
      queryClient.invalidateQueries({ queryKey: ['candidate', id] });
      showToast('success', 'CV uploaded', `${file.name} stored in private document vault.`);
    } catch (err) {
      showToast('error', 'Upload failed', extractApiError(err).message);
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  };

  if (isLoading) return <LoadingState message="Loading candidate profile..." />;
  if (error || !candidate) return <ErrorState message={extractApiError(error).message} onRetry={() => refetch()} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${candidate.first_name} ${candidate.last_name}`}
        description={candidate.headline || `${candidate.current_title || 'Candidate'} · ${candidate.email}`}
        breadcrumbs={[{ label: 'Candidates', to: '/candidates' }, { label: `${candidate.first_name} ${candidate.last_name}` }]}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="text-sm font-semibold text-ink">Profile & Skills</h2>
            <p className="mt-2 text-sm text-muted">{candidate.summary || 'No summary provided.'}</p>
            <div className="mt-4 flex flex-wrap gap-1.5">
              {(candidate.skills || []).map((s) => (
                <span key={s.skill_name} className="rounded border border-line bg-canvas px-2.5 py-1 text-xs font-medium text-ink">
                  {s.skill_name}
                </span>
              ))}
            </div>
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="mb-3 text-sm font-semibold text-ink">Work Experience</h2>
            {(candidate.experiences || []).length === 0 ? (
              <p className="text-xs text-muted">No work experience recorded.</p>
            ) : (
              <div className="space-y-4">
                {(candidate.experiences || []).map((exp) => (
                  <div key={exp.id} className="border-l-2 border-line pl-3">
                    <p className="text-sm font-medium text-ink">{exp.job_title}</p>
                    <p className="text-xs text-muted">
                      {exp.company_name} · {formatDate(exp.start_date)} – {exp.is_current ? 'Present' : formatDate(exp.end_date)}
                    </p>
                    {exp.description && <p className="mt-1 text-xs text-muted">{exp.description}</p>}
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h2 className="mb-3 text-sm font-semibold text-ink">Education</h2>
            {(candidate.educations || []).length === 0 ? (
              <p className="text-xs text-muted">No education history recorded.</p>
            ) : (
              <div className="space-y-3">
                {(candidate.educations || []).map((edu) => (
                  <div key={edu.id} className="border-l-2 border-line pl-3">
                    <p className="text-sm font-medium text-ink">
                      {edu.degree} in {edu.field_of_study}
                    </p>
                    <p className="text-xs text-muted">
                      {edu.institution} · {formatDate(edu.start_date)} – {formatDate(edu.end_date)}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="space-y-6">
          <div className="rounded-lg border border-line bg-surface p-5 text-xs space-y-2.5">
            <h3 className="font-semibold uppercase tracking-wider text-muted">Contact & Metadata</h3>
            <div className="flex justify-between"><span className="text-muted">Email</span><span className="font-medium text-ink">{candidate.email}</span></div>
            <div className="flex justify-between"><span className="text-muted">Phone</span><span className="font-medium text-ink">{candidate.phone || '—'}</span></div>
            <div className="flex justify-between"><span className="text-muted">Location</span><span className="font-medium text-ink">{candidate.location || '—'}</span></div>
            <div className="flex justify-between"><span className="text-muted">Experience</span><span className="font-medium text-ink">{candidate.years_of_experience ? `${candidate.years_of_experience} yrs` : '—'}</span></div>
            <div className="flex justify-between"><span className="text-muted">Source</span><span className="font-medium text-ink">{humanizeSlug(candidate.source)}</span></div>
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted">Private Documents</h3>
              {hasPermission('documents.upload') && (
                <label className="inline-flex cursor-pointer items-center gap-1 rounded border border-line bg-canvas px-2.5 py-1 text-xs font-medium text-ink hover:bg-brand-subtle hover:text-brand">
                  <Upload className="h-3.5 w-3.5" />
                  {uploading ? 'Uploading...' : 'Upload CV'}
                  <input type="file" accept=".pdf,.doc,.docx" className="hidden" onChange={handleCvUpload} disabled={uploading} />
                </label>
              )}
            </div>
            {(candidate.documents || []).length === 0 ? (
              <p className="text-xs text-muted">No CV or supporting documents uploaded yet.</p>
            ) : (
              <div className="space-y-2">
                {(candidate.documents || []).map((doc) => (
                  <div key={doc.id} className="flex items-center justify-between rounded-md border border-line p-2.5">
                    <div className="min-w-0">
                      <p className="truncate text-xs font-medium text-ink">{doc.original_name}</p>
                      <p className="text-[11px] text-muted">
                        {doc.is_primary_cv ? 'Primary CV · ' : ''}
                        {formatBytes(doc.file_size_bytes)}
                      </p>
                    </div>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => downloadPrivateDocument(doc.id, doc.original_name)}
                      aria-label="Download CV"
                    >
                      <Download className="h-4 w-4 text-brand" />
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">Application History</h3>
            {(candidate.applications || []).length === 0 ? (
              <p className="text-xs text-muted">No applications submitted.</p>
            ) : (
              <div className="space-y-2">
                {(candidate.applications || []).map((app) => (
                  <Link
                    key={app.id}
                    to={`/applications/${app.id}`}
                    className="flex items-center justify-between rounded-md border border-line p-2.5 hover:bg-canvas"
                  >
                    <div className="min-w-0">
                      <p className="truncate text-xs font-medium text-ink">{app.job?.title}</p>
                      <p className="text-[11px] text-muted">{app.application_number}</p>
                    </div>
                    <StatusBadge status={app.current_stage?.slug} label={app.current_stage?.name} />
                  </Link>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
