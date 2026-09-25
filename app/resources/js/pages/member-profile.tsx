import { Head, router } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useState } from 'react';
import AppearanceSelector from '@/components/appearance-selector';

type Profile = {
    city: string | null;
    avatar_url: string | null;
    interests: string[];
    social_contexts: string[];
    preferred_days: string[];
    preferred_times: string[];
    completion_percentage: number;
    profile_completed_at: string | null;
};
type Options = {
    interests: string[];
    social_contexts: string[];
    preferred_days: string[];
    preferred_times: string[];
    cities: string[];
};

const labels: Record<string, string> = {
    food: 'Gastronomía',
    cafe: 'Café',
    fitness: 'Fitness',
    wellness: 'Wellness',
    beauty: 'Belleza',
    entertainment: 'Entretenimiento',
    nightlife: 'Vida nocturna',
    shopping: 'Shopping',
    services: 'Lifestyle y servicios',
    experiences: 'Experiencias',
    solo: 'Solo',
    pareja: 'En pareja',
    amigos: 'Con amigos',
    familia: 'En familia',
    entre_semana: 'Entre semana',
    fin_de_semana: 'Fin de semana',
    manana: 'Mañana',
    tarde: 'Tarde',
    noche: 'Noche',
};

function Choices({
    values,
    selected,
    onChange,
}: {
    values: string[];
    selected: string[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="mt-3 flex flex-wrap gap-2">
            {values.map((value) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => onChange(value)}
                    className={`rounded-full border px-3 py-2 text-sm font-medium ${selected.includes(value) ? 'border-foreground bg-foreground text-background' : 'border-border bg-background'}`}
                >
                    {labels[value] ?? value}
                </button>
            ))}
        </div>
    );
}

export default function MemberProfile({
    user,
    profile,
    options,
}: {
    user: { name: string; email: string };
    profile: Profile;
    options: Options;
}) {
    const [form, setForm] = useState({
        city: profile.city ?? 'Cochabamba',
        interests: profile.interests,
        social_contexts: profile.social_contexts,
        preferred_days: profile.preferred_days,
        preferred_times: profile.preferred_times,
        avatar: null as File | null,
    });
    const toggle = (
        field:
            | 'interests'
            | 'social_contexts'
            | 'preferred_days'
            | 'preferred_times',
        value: string,
    ) =>
        setForm((current) => ({
            ...current,
            [field]: current[field].includes(value)
                ? current[field].filter((item) => item !== value)
                : [...current[field], value],
        }));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            '/mi-jakawi/perfil',
            { ...form, _method: 'put' },
            { forceFormData: true },
        );
    };
    const avatarChange = (event: ChangeEvent<HTMLInputElement>) =>
        setForm((current) => ({
            ...current,
            avatar: event.target.files?.[0] ?? null,
        }));
    const complete = profile.completion_percentage === 100;

    return (
        <>
            <Head title="Tu perfil JAKAWI" />
            <main className="min-h-screen bg-background px-4 py-6 pb-28 text-foreground sm:px-6">
                <form
                    onSubmit={submit}
                    className="mx-auto flex w-full max-w-xl flex-col gap-6"
                >
                    <div>
                        <a
                            href="/"
                            className="text-sm font-medium underline"
                        >
                            Inicio
                        </a>
                        <p className="mt-5 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Tu perfil JAKAWI
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">
                            Tu perfil
                        </h1>
                        <p className="mt-3 leading-6 text-muted-foreground">
                            Cuéntanos qué te gusta para mostrarte lugares y
                            experiencias más relevantes.
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-surface p-5">
                        <div className="flex items-center gap-4">
                            {profile.avatar_url ? (
                                <img
                                    src={profile.avatar_url}
                                    className="h-16 w-16 rounded-full object-cover"
                                    alt="Tu avatar"
                                />
                            ) : (
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-xl font-semibold">
                                    {user.name.slice(0, 1).toUpperCase()}
                                </div>
                            )}
                            <div>
                                <p className="font-semibold">{user.name}</p><p className="text-sm text-muted-foreground">{user.email}</p>
                                <label className="mt-1 block cursor-pointer text-sm underline">
                                    Agregar foto opcional
                                    <input
                                        className="sr-only"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={avatarChange}
                                    />
                                </label>
                            </div>
                        </div>
                        <label className="mt-5 block text-sm font-semibold">
                            Ciudad
                            <select
                                value={form.city}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        city: event.target.value,
                                    }))
                                }
                                className="mt-2 block w-full rounded-md border border-border bg-background px-3 py-2"
                            >
                                {options.cities.map((city) => (
                                    <option key={city}>{city}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <div className="rounded-md border border-border bg-surface p-5">
                        <p className="text-lg font-semibold">
                            ¿Qué te interesa?
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Elige entre 3 y 5 si quieres; puedes seleccionar
                            más.
                        </p>
                        <Choices
                            values={options.interests}
                            selected={form.interests}
                            onChange={(value) => toggle('interests', value)}
                        />
                    </div>
                    <div className="rounded-md border border-border bg-surface p-5">
                        <p className="text-lg font-semibold">
                            ¿Con quién sueles salir?
                        </p>
                        <Choices
                            values={options.social_contexts}
                            selected={form.social_contexts}
                            onChange={(value) =>
                                toggle('social_contexts', value)
                            }
                        />
                    </div>
                    <div className="rounded-md border border-border bg-surface p-5">
                        <p className="text-lg font-semibold">
                            ¿Cuándo sueles buscar planes?
                        </p>
                        <Choices
                            values={options.preferred_days}
                            selected={form.preferred_days}
                            onChange={(value) =>
                                toggle('preferred_days', value)
                            }
                        />
                    </div>
                    <div className="rounded-md border border-border bg-surface p-5">
                        <p className="text-lg font-semibold">
                            ¿En qué momento del día?
                        </p>
                        <Choices
                            values={options.preferred_times}
                            selected={form.preferred_times}
                            onChange={(value) =>
                                toggle('preferred_times', value)
                            }
                        />
                    </div>
                    <div
                        className={`rounded-md border p-5 ${complete ? 'border-success bg-success/10' : 'border-border bg-surface'}`}
                    >
                        <p className="text-xl font-semibold">
                            {complete
                                ? 'Perfil completo'
                                : `${profile.completion_percentage}% completo`}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {complete
                                ? 'Gracias por compartir tus preferencias.'
                                : 'Completa las cuatro secciones para terminar tu perfil.'}
                        </p>
                    </div>
                    <section className="rounded-md border border-border bg-surface p-5"><p className="text-lg font-semibold">Apariencia</p><p className="mt-1 text-sm text-muted-foreground">Elige cómo se ve JAKAWI.</p><div className="mt-4"><AppearanceSelector /></div></section>
                    <button
                        className="rounded-md bg-foreground px-4 py-3 font-semibold text-background"
                        type="submit"
                    >
                        Guardar perfil
                    </button>
                    <button type="button" onClick={() => router.post('/logout')} className="min-h-11 text-sm font-semibold text-muted-foreground underline">Cerrar sesión</button>
                </form>
            </main>
        </>
    );
}
