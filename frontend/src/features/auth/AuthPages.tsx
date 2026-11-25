import React, { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { authApi } from '../../api/services';
import { extractApiError } from '../../lib/formatters';
import { Button, Input, PageHeader } from '../../components/ui';

const loginSchema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginValues = z.infer<typeof loginSchema>;

const DEMO_ACCOUNTS = [
  { label: 'System Admin', email: 'admin@talentflow.local', password: 'Password123!' },
  { label: 'Recruiter', email: 'recruiter@talentflow.local', password: 'Password123!' },
  { label: 'Hiring Manager', email: 'hm.engineering@talentflow.local', password: 'Password123!' },
  { label: 'Candidate', email: 'candidate@talentflow.local', password: 'Password123!' },
];

export const LoginPage: React.FC = () => {
  const { login } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema) });

  const onSubmit = async (values: LoginValues) => {
    setServerError(null);
    try {
      const res = await login(values);
      showToast('success', 'Welcome back', `Signed in as ${res.user.name}`);
      navigate(res.redirect_to || '/dashboard', { replace: true });
    } catch (err) {
      const parsed = extractApiError(err);
      setServerError(parsed.message);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
      <div className="w-full max-w-md rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="mb-6">
          <div className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-brand text-xs font-bold text-white">
              TF
            </div>
            <span className="text-base font-semibold text-ink">TalentFlow ATS</span>
          </div>
          <h1 className="mt-4 text-xl font-semibold text-ink">Sign in to your account</h1>
          <p className="mt-1 text-sm text-muted">Access your recruitment workspace or candidate portal.</p>
        </div>

        {serverError && (
          <div className="mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3.5 py-2.5 text-xs text-danger">
            {serverError}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <Input
            label="Work or Personal Email"
            type="email"
            placeholder="name@company.com"
            error={errors.email?.message}
            {...register('email')}
          />
          <Input
            label="Password"
            type="password"
            placeholder="Enter your password"
            error={errors.password?.message}
            {...register('password')}
          />
          <div className="flex items-center justify-between text-xs">
            <Link to="/forgot-password" className="font-medium text-brand hover:underline">
              Forgot password?
            </Link>
            <Link to="/careers" className="text-muted hover:text-ink">
              Browse open jobs
            </Link>
          </div>
          <Button type="submit" className="w-full" isLoading={isSubmitting}>
            Sign In
          </Button>
        </form>

        <div className="mt-6 border-t border-line pt-4">
          <p className="mb-2 text-[11px] font-medium uppercase tracking-wider text-muted">Quick-Fill Demo Accounts</p>
          <div className="grid grid-cols-2 gap-2">
            {DEMO_ACCOUNTS.map((acc) => (
              <button
                key={acc.email}
                type="button"
                onClick={() => {
                  setValue('email', acc.email);
                  setValue('password', acc.password);
                }}
                className="rounded-md border border-line bg-canvas px-2.5 py-1.5 text-left text-xs hover:border-brand/40 hover:bg-brand-subtle/40 transition-colors"
              >
                <div className="font-medium text-ink">{acc.label}</div>
                <div className="truncate text-[11px] text-muted">{acc.email}</div>
              </button>
            ))}
          </div>
        </div>

        <p className="mt-5 text-center text-xs text-muted">
          Looking for a role?{' '}
          <Link to="/register" className="font-medium text-brand hover:underline">
            Create a Candidate Account
          </Link>
        </p>
      </div>
    </div>
  );
};

const registerSchema = z
  .object({
    first_name: z.string().min(1, 'First name is required').max(100),
    last_name: z.string().min(1, 'Last name is required').max(100),
    email: z.string().email('Enter a valid email address'),
    phone: z.string().optional(),
    location: z.string().optional(),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string().min(8, 'Please confirm your password'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

type RegisterValues = z.infer<typeof registerSchema>;

export const RegisterPage: React.FC = () => {
  const { register: registerCandidate } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterValues>({ resolver: zodResolver(registerSchema) });

  const onSubmit = async (values: RegisterValues) => {
    setServerError(null);
    try {
      const res = await registerCandidate(values);
      showToast('success', 'Account created', 'Welcome to TalentFlow Candidate Portal.');
      navigate(res.redirect_to || '/portal/dashboard', { replace: true });
    } catch (err) {
      setServerError(extractApiError(err).message);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
      <div className="w-full max-w-lg rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="mb-5">
          <Link to="/careers" className="text-xs font-medium text-brand hover:underline">
            &larr; Back to Careers
          </Link>
          <h1 className="mt-3 text-xl font-semibold text-ink">Create Candidate Account</h1>
          <p className="mt-1 text-sm text-muted">Build your profile once and track every application in real time.</p>
        </div>

        {serverError && (
          <div className="mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3.5 py-2.5 text-xs text-danger">
            {serverError}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Input label="First Name" required error={errors.first_name?.message} {...register('first_name')} />
            <Input label="Last Name" required error={errors.last_name?.message} {...register('last_name')} />
          </div>
          <Input label="Email Address" type="email" required error={errors.email?.message} {...register('email')} />
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Input label="Phone Number" placeholder="+62..." {...register('phone')} />
            <Input label="City / Location" placeholder="Jakarta, Indonesia" {...register('location')} />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Input label="Password" type="password" required error={errors.password?.message} {...register('password')} />
            <Input
              label="Confirm Password"
              type="password"
              required
              error={errors.password_confirmation?.message}
              {...register('password_confirmation')}
            />
          </div>
          <Button type="submit" className="w-full" isLoading={isSubmitting}>
            Create Account
          </Button>
        </form>

        <p className="mt-5 text-center text-xs text-muted">
          Already have an account?{' '}
          <Link to="/login" className="font-medium text-brand hover:underline">
            Sign In
          </Link>
        </p>
      </div>
    </div>
  );
};

export const ForgotPasswordPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const msg = await authApi.forgotPassword(email);
      setMessage(msg);
    } catch (err) {
      setError(extractApiError(err).message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-canvas px-4">
      <div className="w-full max-w-md rounded-lg border border-line bg-surface p-6">
        <h1 className="text-lg font-semibold text-ink">Reset your password</h1>
        <p className="mt-1 text-sm text-muted">Enter your account email to receive a password reset link.</p>
        {message && (
          <div className="mt-4 rounded-md border border-success/30 bg-success-subtle px-3 py-2 text-xs text-success">
            {message}
          </div>
        )}
        {error && (
          <div className="mt-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-xs text-danger">
            {error}
          </div>
        )}
        <form onSubmit={handleSubmit} className="mt-4 space-y-4">
          <Input label="Email Address" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
          <Button type="submit" className="w-full" isLoading={loading}>
            Send Reset Link
          </Button>
        </form>
        <div className="mt-4 text-center">
          <Link to="/login" className="text-xs font-medium text-brand hover:underline">
            Back to Sign In
          </Link>
        </div>
      </div>
    </div>
  );
};

export const ResetPasswordPage: React.FC = () => {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [email, setEmail] = useState(params.get('email') || '');
  const [token, setToken] = useState(params.get('token') || '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await authApi.resetPassword({ token, email, password, password_confirmation: passwordConfirmation });
      showToast('success', 'Password reset', 'You can now sign in with your new password.');
      navigate('/login');
    } catch (err) {
      setError(extractApiError(err).message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-canvas px-4">
      <div className="w-full max-w-md rounded-lg border border-line bg-surface p-6">
        <h1 className="text-lg font-semibold text-ink">Set a new password</h1>
        {error && (
          <div className="mt-3 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-xs text-danger">
            {error}
          </div>
        )}
        <form onSubmit={handleSubmit} className="mt-4 space-y-3.5">
          <Input label="Reset Token" required value={token} onChange={(e) => setToken(e.target.value)} />
          <Input label="Email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
          <Input label="New Password" type="password" required value={password} onChange={(e) => setPassword(e.target.value)} />
          <Input
            label="Confirm New Password"
            type="password"
            required
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
          />
          <Button type="submit" className="w-full" isLoading={loading}>
            Reset Password
          </Button>
        </form>
      </div>
    </div>
  );
};

export const AccountSettingsPage: React.FC = () => {
  const { user, refreshUser } = useAuth();
  const { showToast } = useToast();
  const [name, setName] = useState(user?.name || '');
  const [phone, setPhone] = useState(user?.phone || '');
  const [jobTitle, setJobTitle] = useState(user?.job_title || '');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Record<string, unknown> = { name, phone, job_title: jobTitle };
      if (password) {
        payload.current_password = currentPassword;
        payload.password = password;
        payload.password_confirmation = passwordConfirmation;
      }
      await authApi.updateProfile(payload);
      await refreshUser();
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      showToast('success', 'Account updated', 'Your personal details have been saved.');
    } catch (err) {
      showToast('error', 'Update failed', extractApiError(err).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader title="Account Settings" description="Manage your personal information and password." />
      <form onSubmit={handleSave} className="max-w-xl space-y-5 rounded-lg border border-line bg-surface p-6">
        <div className="space-y-4">
          <Input label="Full Name" required value={name} onChange={(e) => setName(e.target.value)} />
          <Input label="Email" value={user?.email || ''} disabled />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
            <Input label="Job Title" value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} />
          </div>
        </div>
        <div className="border-t border-line pt-4 space-y-3">
          <h2 className="text-sm font-semibold text-ink">Change Password (Optional)</h2>
          <Input
            label="Current Password"
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input label="New Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
            <Input
              label="Confirm New Password"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
            />
          </div>
        </div>
        <div className="flex justify-end">
          <Button type="submit" isLoading={saving}>
            Save Changes
          </Button>
        </div>
      </form>
    </div>
  );
};
