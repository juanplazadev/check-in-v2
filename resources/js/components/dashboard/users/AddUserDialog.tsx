import { Link, router, useForm } from '@inertiajs/react';
import {
    Briefcase,
    Check,
    CheckCircle2,
    ClipboardList,
    Copy,
    Eye,
    EyeOff,
    KeyRound,
    Mail,
    RefreshCw,
    ShieldCheck,
    Truck,
    UserPlus,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';

import UserController from '@/actions/App/Http/Controllers/Dashboard/UserController';
import TextField from '@/components/dashboard/location/TextField';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import LocationChecklist from '@/components/dashboard/users/LocationChecklist';
import type {
    NewUserLocation,
    NewUserRole,
    UserAccessMethod,
} from '@/components/dashboard/users/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import dashboardRoutes from '@/routes/dashboard';
import type { StaffRole } from '@/types/staff';
import { STAFF_ROLE_LABELS } from '@/types/staff';

const PRIMARY_BUTTON =
    'btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white';

const ROLE_DETAILS: Record<StaffRole, { icon: ReactNode; summary: string }> = {
    admin: {
        icon: <ShieldCheck className="size-4" aria-hidden />,
        summary: 'Everything, including users, roles and locations.',
    },
    'shipping-manager': {
        icon: <ClipboardList className="size-4" aria-hidden />,
        summary: 'Runs the dock: edits, reopens and links check-ins.',
    },
    shipping: {
        icon: <Truck className="size-4" aria-hidden />,
        summary: 'Works the live queue and completes check-ins.',
    },
    sales: {
        icon: <Briefcase className="size-4" aria-hidden />,
        summary: 'Follows check-ins and appointments, with analytics.',
    },
};

interface AddUserForm {
    name: string;
    email: string;
    cellphone: string;
    role: StaffRole | '';
    locations: string[];
    access: UserAccessMethod;
    password: string;
}

interface CreatedUser {
    uuid: string;
    name: string;
    email: string;
    access: UserAccessMethod;
    /** Only for a temporary password: shown once so the admin can pass it on. */
    password: string | null;
}

/**
 * Creates a staff account from the users page. The roles and locations are optional props, loaded
 * by a partial reload when the dialog opens. On success the dialog stays open on a summary, so a
 * temporary password can be copied before it is gone.
 */
export default function AddUserDialog({
    open,
    onOpenChange,
    roles,
    locations,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Undefined until the partial reload answers. */
    roles: NewUserRole[] | undefined;
    locations: NewUserLocation[] | undefined;
}) {
    const [round, setRound] = useState(0);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dashboard sm:max-w-xl">
                {/* Radix unmounts the content on close, and "Add another" bumps the key, so each
                    form starts empty and reloads its options. */}
                <AddUserBody
                    key={round}
                    roles={roles}
                    locations={locations}
                    onAddAnother={() => setRound((previous) => previous + 1)}
                    onClose={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function AddUserBody({
    roles,
    locations,
    onAddAnother,
    onClose,
}: {
    roles: NewUserRole[] | undefined;
    locations: NewUserLocation[] | undefined;
    onAddAnother: () => void;
    onClose: () => void;
}) {
    const [loading, setLoading] = useState(true);
    const [created, setCreated] = useState<CreatedUser | null>(null);
    const form = useForm<AddUserForm>({
        name: '',
        email: '',
        cellphone: '',
        role: '',
        locations: [],
        access: 'invite',
        password: '',
    });

    // Run from the mounted body: a parent setting open=true never fires onOpenChange.
    useEffect(() => {
        router.reload({
            only: ['newUserRoles', 'newUserLocations'],
            onFinish: () => setLoading(false),
        });
    }, []);

    const availableRoles = loading ? roles : (roles ?? []);
    const selectedRole = availableRoles?.find(
        (role) => role.value === form.data.role,
    );
    const needsLocations =
        selectedRole !== undefined && !selectedRole.seesAllLocations;
    const isTemporary = form.data.access === 'temporary-password';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const password = isTemporary ? form.data.password : null;

        form.transform((data) => ({
            ...data,
            locations: needsLocations ? data.locations : [],
            password,
        }));

        form.submit(UserController.store(), {
            preserveScroll: true,
            preserveState: true,
            onFlash: ({ createdUser }) => {
                if (createdUser) {
                    setCreated({ ...createdUser, password });
                }
            },
        });
    };

    if (created !== null) {
        return (
            <SuccessState
                created={created}
                onAddAnother={onAddAnother}
                onClose={onClose}
            />
        );
    }

    const locationsError =
        form.errors.locations ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('locations.'),
        )?.[1];

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-col gap-4">
            <DialogHeader>
                <DialogTitle>Add user</DialogTitle>
                <DialogDescription>
                    Create a staff account. Drivers sign up themselves when they
                    check in.
                </DialogDescription>
            </DialogHeader>

            <div className="-mx-6 min-h-0 space-y-6 overflow-y-auto px-6 pb-1">
                <Section step={1} title="Who they are">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="name"
                            label="Full name"
                            autoComplete="off"
                            autoFocus
                            className="sm:col-span-2"
                        />
                        <TextField
                            form={form}
                            name="email"
                            label="Email"
                            type="email"
                            autoComplete="off"
                        />
                        <TextField
                            form={form}
                            name="cellphone"
                            label="Cellphone (optional)"
                            type="tel"
                            inputMode="tel"
                            placeholder="(555) 123-4567"
                            autoComplete="off"
                        />
                    </div>
                </Section>

                <Section step={2} title="Role">
                    <RoleCards
                        roles={availableRoles}
                        selected={form.data.role}
                        onSelect={(role) => form.setData('role', role)}
                    />
                    {form.errors.role && (
                        <p className="mt-2 text-sm text-red-500">
                            {form.errors.role}
                        </p>
                    )}
                </Section>

                {needsLocations && (
                    <Section
                        step={3}
                        title="Locations"
                        hint={`${STAFF_ROLE_LABELS[selectedRole.value]} sees only the locations picked here.`}
                        className="animate-in duration-200 fade-in slide-in-from-top-2"
                    >
                        <LocationChecklist
                            locations={loading ? locations : (locations ?? [])}
                            selected={form.data.locations}
                            onChange={(selected) =>
                                form.setData('locations', selected)
                            }
                            invalid={locationsError !== undefined}
                        />
                        {locationsError !== undefined && (
                            <p className="mt-2 text-sm text-red-500">
                                {locationsError}
                            </p>
                        )}
                    </Section>
                )}

                <Section step={needsLocations ? 4 : 3} title="How they sign in">
                    <AccessChoice
                        value={form.data.access}
                        onChange={(access) =>
                            form.setData({
                                ...form.data,
                                access,
                                // Open with a strong suggestion rather than an empty box.
                                password:
                                    access === 'temporary-password' &&
                                    form.data.password === ''
                                        ? generatePassword()
                                        : form.data.password,
                            })
                        }
                    />

                    {isTemporary ? (
                        <TemporaryPasswordField
                            value={form.data.password}
                            onChange={(password) =>
                                form.setData('password', password)
                            }
                            error={form.errors.password}
                        />
                    ) : (
                        <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            They'll get an email to choose their own password.
                            The link lasts 7 days.
                        </p>
                    )}
                </Section>
            </div>

            <DialogFooter>
                <button
                    type="button"
                    onClick={onClose}
                    disabled={form.processing}
                    className={SECONDARY_BUTTON}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={form.processing || form.data.role === ''}
                    className={PRIMARY_BUTTON}
                >
                    <UserPlus className="size-4" aria-hidden />
                    {form.processing
                        ? 'Adding…'
                        : isTemporary
                          ? 'Add user'
                          : 'Add and send invite'}
                </button>
            </DialogFooter>
        </form>
    );
}

function Section({
    step,
    title,
    hint,
    className = '',
    children,
}: {
    step: number;
    title: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section className={className}>
            <div className="mb-3 flex items-center gap-2">
                <span className="flex size-5 items-center justify-center rounded-full bg-brand-green/10 text-[11px] font-semibold text-brand-green">
                    {step}
                </span>
                <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </h3>
            </div>
            {hint && (
                <p className="-mt-1 mb-3 text-xs text-gray-500 dark:text-gray-400">
                    {hint}
                </p>
            )}
            {children}
        </section>
    );
}

function RoleCards({
    roles,
    selected,
    onSelect,
}: {
    roles: NewUserRole[] | undefined;
    selected: StaffRole | '';
    onSelect: (role: StaffRole) => void;
}) {
    if (roles === undefined) {
        return (
            <div role="status" className="grid gap-2 sm:grid-cols-2">
                <span className="sr-only">Loading roles…</span>
                {[0, 1, 2, 3].map((each) => (
                    <div
                        key={each}
                        className="h-[84px] animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40"
                    />
                ))}
            </div>
        );
    }

    return (
        <div
            role="radiogroup"
            aria-label="Role"
            className="grid gap-2 sm:grid-cols-2"
        >
            {roles.map((role) => {
                const isSelected = role.value === selected;
                const details = ROLE_DETAILS[role.value];

                return (
                    <button
                        key={role.value}
                        type="button"
                        role="radio"
                        aria-checked={isSelected}
                        onClick={() => onSelect(role.value)}
                        className={`relative cursor-pointer rounded-lg border p-3 text-left transition ${
                            isSelected
                                ? 'border-brand-green bg-brand-green/[0.06] ring-1 ring-brand-green dark:bg-brand-green/[0.12]'
                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700/60 dark:hover:border-gray-600'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <span
                                className={`flex size-7 items-center justify-center rounded-full ${
                                    isSelected
                                        ? 'bg-brand-green text-white'
                                        : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300'
                                }`}
                            >
                                {details.icon}
                            </span>
                            <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                {STAFF_ROLE_LABELS[role.value]}
                            </span>
                            {isSelected && (
                                <Check
                                    className="ml-auto size-4 text-brand-green"
                                    aria-hidden
                                />
                            )}
                        </span>
                        <span className="mt-1.5 block text-xs text-gray-500 dark:text-gray-400">
                            {details.summary}
                        </span>
                        <span
                            className={`mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                role.seesAllLocations
                                    ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400'
                                    : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                            }`}
                        >
                            {role.seesAllLocations
                                ? 'Every location'
                                : 'Assigned locations'}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function AccessChoice({
    value,
    onChange,
}: {
    value: UserAccessMethod;
    onChange: (value: UserAccessMethod) => void;
}) {
    const options: {
        value: UserAccessMethod;
        label: string;
        icon: ReactNode;
    }[] = [
        {
            value: 'invite',
            label: 'Email invite',
            icon: <Mail className="size-4" aria-hidden />,
        },
        {
            value: 'temporary-password',
            label: 'Temporary password',
            icon: <KeyRound className="size-4" aria-hidden />,
        },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="How they sign in"
            className="grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-700/40"
        >
            {options.map((option) => {
                const isSelected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={isSelected}
                        onClick={() => onChange(option.value)}
                        className={`flex cursor-pointer items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition ${
                            isSelected
                                ? 'bg-white text-gray-800 shadow-sm dark:bg-gray-800 dark:text-gray-100'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        }`}
                    >
                        {option.icon}
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

/** Characters that cannot be misread when a password is read aloud or copied by hand. */
const PASSWORD_SETS = [
    'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'abcdefghijkmnopqrstuvwxyz',
    '23456789',
    '!@#$%&*?',
];

function randomIndex(max: number): number {
    const values = new Uint32Array(1);
    window.crypto.getRandomValues(values);

    return values[0] % max;
}

/** 16 characters with at least one of each set, so it passes the production password rules. */
function generatePassword(): string {
    const all = PASSWORD_SETS.join('');
    const characters = PASSWORD_SETS.map((set) => set[randomIndex(set.length)]);

    while (characters.length < 16) {
        characters.push(all[randomIndex(all.length)]);
    }

    for (let i = characters.length - 1; i > 0; i--) {
        const j = randomIndex(i + 1);
        [characters[i], characters[j]] = [characters[j], characters[i]];
    }

    return characters.join('');
}

function TemporaryPasswordField({
    value,
    onChange,
    error,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    // Visible from the start: the field opens holding a generated suggestion.
    const [visible, setVisible] = useState(true);

    return (
        <Field className="mt-3" data-invalid={!!error || undefined}>
            <FieldLabel htmlFor="new_user_password">
                Temporary password
            </FieldLabel>
            <div className="flex gap-2">
                <div className="relative grow">
                    <Input
                        id="new_user_password"
                        type={visible ? 'text' : 'password'}
                        value={value}
                        autoComplete="new-password"
                        aria-invalid={!!error || undefined}
                        onChange={(event) => onChange(event.target.value)}
                        className="pr-10 font-mono"
                    />
                    <button
                        type="button"
                        onClick={() => setVisible((previous) => !previous)}
                        className="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <span className="sr-only">
                            {visible ? 'Hide password' : 'Show password'}
                        </span>
                        {visible ? (
                            <EyeOff className="size-4" aria-hidden />
                        ) : (
                            <Eye className="size-4" aria-hidden />
                        )}
                    </button>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        onChange(generatePassword());
                        setVisible(true);
                    }}
                    className={SECONDARY_BUTTON}
                >
                    <RefreshCw className="size-4" aria-hidden />
                    Generate
                </button>
            </div>
            <FieldError>{error}</FieldError>
            <p className="text-xs text-gray-500 dark:text-gray-400">
                They'll be asked to choose a new password the first time they
                sign in.
            </p>
        </Field>
    );
}

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timeout = window.setTimeout(() => setCopied(false), 2000);

        return () => window.clearTimeout(timeout);
    }, [copied]);

    return (
        <button
            type="button"
            onClick={() =>
                navigator.clipboard
                    ?.writeText(value)
                    .then(() => setCopied(true))
                    .catch(() => undefined)
            }
            className="shrink-0 cursor-pointer rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700/60 dark:hover:text-gray-300"
        >
            <span className="sr-only">{copied ? 'Copied' : label}</span>
            {copied ? (
                <Check className="size-4 text-brand-green" aria-hidden />
            ) : (
                <Copy className="size-4" aria-hidden />
            )}
        </button>
    );
}

function SuccessState({
    created,
    onAddAnother,
    onClose,
}: {
    created: CreatedUser;
    onAddAnother: () => void;
    onClose: () => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col items-center pt-2 text-center">
                <span className="flex size-12 animate-in items-center justify-center rounded-full bg-brand-green/10 text-brand-green duration-300 zoom-in-50">
                    <CheckCircle2 className="size-6" aria-hidden />
                </span>
                <DialogTitle className="mt-3">
                    {created.name} was added
                </DialogTitle>
                <DialogDescription className="mt-1">
                    {created.access === 'invite'
                        ? `An invite is on its way to ${created.email}.`
                        : 'Pass these on. The password is shown only once.'}
                </DialogDescription>
            </div>

            {created.access === 'temporary-password' &&
                created.password !== null && (
                    <dl className="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700/60 dark:border-gray-700/60">
                        <div className="flex items-center gap-3 px-3 py-2">
                            <dt className="w-20 shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                Email
                            </dt>
                            <dd className="min-w-0 grow truncate text-sm text-gray-800 dark:text-gray-100">
                                {created.email}
                            </dd>
                            <CopyButton
                                value={created.email}
                                label="Copy email"
                            />
                        </div>
                        <div className="flex items-center gap-3 px-3 py-2">
                            <dt className="w-20 shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                Password
                            </dt>
                            <dd className="min-w-0 grow truncate font-mono text-sm text-gray-800 dark:text-gray-100">
                                {created.password}
                            </dd>
                            <CopyButton
                                value={created.password}
                                label="Copy password"
                            />
                        </div>
                    </dl>
                )}

            <DialogFooter>
                <button
                    type="button"
                    onClick={onAddAnother}
                    className={SECONDARY_BUTTON}
                >
                    <UserPlus className="size-4" aria-hidden />
                    Add another
                </button>
                <Link
                    href={dashboardRoutes.users.show.url(created.uuid)}
                    onClick={onClose}
                    className={PRIMARY_BUTTON}
                >
                    View profile
                </Link>
            </DialogFooter>
        </div>
    );
}
