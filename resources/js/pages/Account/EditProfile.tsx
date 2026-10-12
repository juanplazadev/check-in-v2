import { Head, useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { IdCard, Lock, User } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo } from 'react';

import CountryDisplay from '@/components/CountryDisplay';
import { DatePicker } from '@/components/DatePickerTime';
import LicenseCard from '@/components/LicenseCard';
import LocationAutocomplete from '@/components/LocationAutocomplete';
import { PhoneInput, formatUsPhone } from '@/components/PhoneInput';
import SavedPill from '@/components/SavedPill';
import SectionCard from '@/components/SectionCard';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AccountLayout from '@/layouts/AccountLayout';

// ── Types ────────────────────────────────────────────────────────────────────

interface AccountProfileTranslations {
    back: string;
    title: string;
    subtitle: string;
    infoHeading: string;
    infoSubheading: string;
    nameLabel: string;
    emailLabel: string;
    cellphoneLabel: string;
    cellphonePlaceholder: string;
    cellphoneHelp: string;
    emailChangeNote: string;
    licenseHeading: string;
    licenseSubheading: string;
    licenseNumberLabel: string;
    licenseNumberPlaceholder: string;
    licenseStateLabel: string;
    licenseStatePlaceholder: string;
    licenseCountryLabel: string;
    licenseCountryPlaceholder: string;
    licenseExpirationLabel: string;
    licenseSecure: string;
    licenseEmpty: string;
    save: string;
    saved: string;
    passwordHeading: string;
    passwordSubheading: string;
    currentPasswordLabel: string;
    newPasswordLabel: string;
    confirmPasswordLabel: string;
    updatePassword: string;
}

interface PageProps {
    auth: { user: { name: string; email: string; cellphone: string | null } };
    license: {
        number: string | null;
        state: string | null;
        country: string | null;
        expirationDate: string | null;
    };
    translations: { accountProfile: AccountProfileTranslations };
    [key: string]: unknown;
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function EditProfile() {
    const { auth, license, translations } = usePage<PageProps>().props;
    const t = translations.accountProfile;

    // A license must still be valid (future dated) and, at most, 60 years out;
    // the widest real-world US window is Arizona's "valid until age 65".
    const licenseMinDate = useMemo(() => {
        const tomorrow = new Date();
        tomorrow.setHours(0, 0, 0, 0);
        tomorrow.setDate(tomorrow.getDate() + 1);

        return tomorrow;
    }, []);

    const licenseMaxDate = useMemo(() => {
        const max = new Date();
        max.setHours(0, 0, 0, 0);
        max.setFullYear(max.getFullYear() + 60);

        return max;
    }, []);

    const profile = useForm({
        name: auth.user.name,
        email: auth.user.email,
        cellphone: formatUsPhone(
            (auth.user.cellphone ?? '').replace(/^\+1/, ''),
        ),
        drivers_license_number: license.number ?? '',
        drivers_license_state: license.state ?? '',
        drivers_license_country: license.country ?? '',
        drivers_license_expiration_date: license.expirationDate ?? '',
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitProfile = (event: FormEvent) => {
        event.preventDefault();
        profile.put('/user/profile-information', {
            errorBag: 'updateProfileInformation',
            preserveScroll: true,
        });
    };

    const submitPassword = (event: FormEvent) => {
        event.preventDefault();
        password.put('/user/password', {
            errorBag: 'updatePassword',
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    const emailChanged = profile.data.email !== auth.user.email;

    return (
        <AccountLayout>
            <Head title={t.title} />

            <div className="mx-auto max-w-2xl space-y-6 px-6 pt-6 pb-12">
                {/* Personal information */}
                <SectionCard
                    icon={<User className="h-5 w-5" />}
                    heading={t.infoHeading}
                    subheading={t.infoSubheading}
                    onSubmit={submitProfile}
                >
                    <div className="mt-5 space-y-4">
                        <Field
                            data-invalid={!!profile.errors.name || undefined}
                        >
                            <FieldLabel htmlFor="name">
                                {t.nameLabel}
                            </FieldLabel>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                autoComplete="name"
                                value={profile.data.name}
                                aria-invalid={
                                    !!profile.errors.name || undefined
                                }
                                onChange={(e) =>
                                    profile.setData('name', e.target.value)
                                }
                            />
                            <FieldError>{profile.errors.name}</FieldError>
                        </Field>

                        <Field
                            data-invalid={!!profile.errors.email || undefined}
                        >
                            <FieldLabel htmlFor="email">
                                {t.emailLabel}
                            </FieldLabel>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                value={profile.data.email}
                                aria-invalid={
                                    !!profile.errors.email || undefined
                                }
                                onChange={(e) =>
                                    profile.setData('email', e.target.value)
                                }
                            />
                            <FieldError>{profile.errors.email}</FieldError>
                            {emailChanged && !profile.errors.email && (
                                <p className="text-xs text-amber-600 dark:text-amber-500">
                                    {t.emailChangeNote}
                                </p>
                            )}
                        </Field>

                        <Field
                            data-invalid={
                                !!profile.errors.cellphone || undefined
                            }
                        >
                            <FieldLabel htmlFor="cellphone">
                                {t.cellphoneLabel}
                            </FieldLabel>
                            <PhoneInput
                                id="cellphone"
                                name="cellphone"
                                placeholder={t.cellphonePlaceholder}
                                value={profile.data.cellphone}
                                invalid={!!profile.errors.cellphone}
                                onChange={(v) =>
                                    profile.setData('cellphone', v)
                                }
                            />
                            <FieldError>{profile.errors.cellphone}</FieldError>
                            {!profile.errors.cellphone && (
                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                    {t.cellphoneHelp}
                                </p>
                            )}
                        </Field>
                    </div>

                    <div className="mt-6 flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={profile.processing}
                            className="h-10 cursor-pointer rounded-4xl bg-brand-green px-5 text-sm font-semibold text-white shadow-sm shadow-brand-green/25 transition-colors hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                        >
                            {t.save}
                        </Button>
                        {profile.recentlySuccessful && (
                            <SavedPill label={t.saved} />
                        )}
                    </div>
                </SectionCard>

                {/* Driver's license */}
                <SectionCard
                    icon={<IdCard className="h-5 w-5" />}
                    heading={t.licenseHeading}
                    subheading={t.licenseSubheading}
                    onSubmit={submitProfile}
                >
                    <div className="mt-5 space-y-5">
                        {/* Live, state-themed card preview */}
                        <LicenseCard
                            name={profile.data.name}
                            number={profile.data.drivers_license_number || null}
                            state={profile.data.drivers_license_state || null}
                            country={
                                profile.data.drivers_license_country || null
                            }
                            expirationDate={
                                profile.data.drivers_license_expiration_date ||
                                null
                            }
                            secureLabel={t.licenseSecure}
                            emptyLabel={t.licenseEmpty}
                            className="mx-auto max-w-sm"
                        />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                            <Field
                                data-invalid={
                                    !!profile.errors.drivers_license_number ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="drivers_license_number">
                                    {t.licenseNumberLabel}
                                </FieldLabel>
                                <Input
                                    id="drivers_license_number"
                                    name="drivers_license_number"
                                    type="text"
                                    maxLength={20}
                                    autoComplete="off"
                                    autoCapitalize="characters"
                                    value={profile.data.drivers_license_number}
                                    aria-invalid={
                                        !!profile.errors
                                            .drivers_license_number || undefined
                                    }
                                    placeholder={t.licenseNumberPlaceholder}
                                    onChange={(e) =>
                                        profile.setData(
                                            'drivers_license_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {profile.errors.drivers_license_number}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    !!profile.errors.drivers_license_state ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="drivers_license_state">
                                    {t.licenseStateLabel}
                                </FieldLabel>
                                <LocationAutocomplete
                                    level="state"
                                    id="drivers_license_state"
                                    value={profile.data.drivers_license_state}
                                    onChange={(value) =>
                                        // Typing invalidates the derived country.
                                        profile.setData((data) => ({
                                            ...data,
                                            drivers_license_state: value,
                                            drivers_license_country: '',
                                        }))
                                    }
                                    onSelect={(result) =>
                                        profile.setData((data) => ({
                                            ...data,
                                            drivers_license_state: result.state,
                                            drivers_license_country:
                                                result.countryCode,
                                        }))
                                    }
                                    placeholder={t.licenseStatePlaceholder}
                                    maxLength={30}
                                    invalid={
                                        !!profile.errors.drivers_license_state
                                    }
                                />
                                <FieldError>
                                    {profile.errors.drivers_license_state}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    !!profile.errors.drivers_license_country ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="drivers_license_country">
                                    {t.licenseCountryLabel}
                                </FieldLabel>
                                <CountryDisplay
                                    id="drivers_license_country"
                                    value={profile.data.drivers_license_country}
                                    invalid={
                                        !!profile.errors.drivers_license_country
                                    }
                                    placeholder={t.licenseCountryPlaceholder}
                                />
                                <FieldError>
                                    {profile.errors.drivers_license_country}
                                </FieldError>
                            </Field>
                        </div>

                        <Field
                            data-invalid={
                                !!profile.errors
                                    .drivers_license_expiration_date ||
                                undefined
                            }
                            className="sm:max-w-[calc(50%-0.5rem)]"
                        >
                            <FieldLabel htmlFor="drivers_license_expiration_date">
                                {t.licenseExpirationLabel}
                            </FieldLabel>
                            <DatePicker
                                date={
                                    profile.data.drivers_license_expiration_date
                                        ? new Date(
                                              profile.data
                                                  .drivers_license_expiration_date +
                                                  'T00:00:00',
                                          )
                                        : undefined
                                }
                                onDateChange={(d) =>
                                    profile.setData(
                                        'drivers_license_expiration_date',
                                        d ? format(d, 'yyyy-MM-dd') : '',
                                    )
                                }
                                minDate={licenseMinDate}
                                maxDate={licenseMaxDate}
                                endMonth={licenseMaxDate}
                            />
                            <FieldError>
                                {profile.errors.drivers_license_expiration_date}
                            </FieldError>
                        </Field>
                    </div>

                    <div className="mt-6 flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={profile.processing}
                            className="h-10 cursor-pointer rounded-4xl bg-brand-green px-5 text-sm font-semibold text-white shadow-sm shadow-brand-green/25 transition-colors hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                        >
                            {t.save}
                        </Button>
                        {profile.recentlySuccessful && (
                            <SavedPill label={t.saved} />
                        )}
                    </div>
                </SectionCard>

                {/* Change password */}
                <SectionCard
                    icon={<Lock className="h-5 w-5" />}
                    heading={t.passwordHeading}
                    subheading={t.passwordSubheading}
                    onSubmit={submitPassword}
                >
                    <div className="mt-5 space-y-4">
                        <Field
                            data-invalid={
                                !!password.errors.current_password || undefined
                            }
                        >
                            <FieldLabel htmlFor="current_password">
                                {t.currentPasswordLabel}
                            </FieldLabel>
                            <Input
                                id="current_password"
                                name="current_password"
                                type="password"
                                autoComplete="current-password"
                                value={password.data.current_password}
                                aria-invalid={
                                    !!password.errors.current_password ||
                                    undefined
                                }
                                onChange={(e) =>
                                    password.setData(
                                        'current_password',
                                        e.target.value,
                                    )
                                }
                            />
                            <FieldError>
                                {password.errors.current_password}
                            </FieldError>
                        </Field>

                        <Field
                            data-invalid={
                                !!password.errors.password || undefined
                            }
                        >
                            <FieldLabel htmlFor="password">
                                {t.newPasswordLabel}
                            </FieldLabel>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
                                value={password.data.password}
                                aria-invalid={
                                    !!password.errors.password || undefined
                                }
                                onChange={(e) =>
                                    password.setData('password', e.target.value)
                                }
                            />
                            <FieldError>{password.errors.password}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="password_confirmation">
                                {t.confirmPasswordLabel}
                            </FieldLabel>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={password.data.password_confirmation}
                                onChange={(e) =>
                                    password.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <div className="mt-6 flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={password.processing}
                            className="h-10 cursor-pointer rounded-4xl bg-brand-green px-5 text-sm font-semibold text-white shadow-sm shadow-brand-green/25 transition-colors hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                        >
                            {t.updatePassword}
                        </Button>
                        {password.recentlySuccessful && (
                            <SavedPill label={t.saved} />
                        )}
                    </div>
                </SectionCard>
            </div>
        </AccountLayout>
    );
}
