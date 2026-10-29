<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verdeco Cost Control</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <header class="flex items-center justify-between border-b border-slate-800 pb-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-400">Verdeco</p>
                    <h1 class="mt-2 text-2xl font-semibold text-white">Cost Control</h1>
                </div>
                <a href="/admin" class="inline-flex items-center rounded-full border border-amber-500/60 bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-200 transition hover:bg-amber-500/20">
                    Open operations console
                </a>
            </header>

            <main class="mt-10 grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-slate-950/50">
                    <div class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.18em] text-emerald-300">
                        Multi-plant operations
                    </div>

                    <h2 class="mt-6 text-4xl font-semibold tracking-tight text-white">
                        Manufacturing cost visibility across every plant.
                    </h2>

                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300">
                        Monitor cost shifts, active alerts, and production risk in one operating view designed for plant managers, operations leaders, and cost-control teams.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                            <p class="text-sm text-slate-400">Plants</p>
                            <p class="mt-3 text-3xl font-semibold text-white">{{ $plantCount }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                            <p class="text-sm text-slate-400">Open alerts</p>
                            <p class="mt-3 text-3xl font-semibold text-amber-300">{{ $alertCount }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                            <p class="text-sm text-slate-400">Open incidents</p>
                            <p class="mt-3 text-3xl font-semibold text-rose-300">{{ $incidentCount }}</p>
                        </div>
                    </div>

                    <div class="mt-10 grid gap-4 md:grid-cols-2">
                        @foreach ($plantSummaries as $summary)
                            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-slate-200">{{ $summary['plant_name'] }}</p>
                                    <span class="rounded-full bg-slate-800 px-2 py-1 text-[10px] uppercase tracking-[0.15em] text-slate-300">
                                        {{ $summary['costing_mode'] }}
                                    </span>
                                </div>
                                <div class="mt-4 space-y-2 text-sm text-slate-300">
                                    <div class="flex justify-between">
                                        <span>Open incidents</span>
                                        <span class="font-semibold text-white">{{ $summary['open_incident_count'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Exposure</span>
                                        <span class="font-semibold text-amber-300">${{ number_format((float) $summary['open_incident_cost'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Variance</span>
                                        <span class="font-semibold {{ (float) $summary['variance_to_baseline'] > 0 ? 'text-rose-300' : 'text-emerald-300' }}">
                                            ${{ number_format((float) $summary['variance_to_baseline'], 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <aside class="space-y-6">
                    <div class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-800 p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Operational snapshot</p>
                        <ul class="mt-6 space-y-4 text-sm text-slate-200">
                            <li class="flex items-center justify-between rounded-lg border border-slate-700 bg-slate-950/50 px-4 py-3">
                                <span>Plant health</span>
                                <span class="font-semibold text-emerald-300">Stable</span>
                            </li>
                            <li class="flex items-center justify-between rounded-lg border border-slate-700 bg-slate-950/50 px-4 py-3">
                                <span>Incident exposure</span>
                                <span class="font-semibold text-amber-300">Monitoring</span>
                            </li>
                            <li class="flex items-center justify-between rounded-lg border border-slate-700 bg-slate-950/50 px-4 py-3">
                                <span>Costing mode</span>
                                <span class="font-semibold text-sky-300">Daily + batch</span>
                            </li>
                        </ul>

                        <div class="mt-8 flex gap-3">
                            <a href="/admin" class="inline-flex flex-1 items-center justify-center rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300">
                                Launch admin
                            </a>
                            <a href="/health" class="inline-flex flex-1 items-center justify-center rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                                Health check
                            </a>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">Recent alerts</p>
                            <span class="text-xs text-slate-500">Live</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            @forelse ($recentAlerts as $alert)
                                <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-300">{{ $alert->severity }}</span>
                                        <span class="text-[10px] text-slate-500">{{ $alert->plant?->code ?? 'N/A' }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-200">{{ $alert->message }}</p>
                                    <p class="mt-2 text-[11px] text-slate-500">{{ $alert->triggered_at?->diffForHumans() ?? 'Recently' }}</p>
                                </div>
                            @empty
                                <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 text-sm text-slate-400">
                                    No open alerts in the active monitoring stream.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">Demo access</p>
                        <div class="mt-4 space-y-2 text-sm text-slate-200">
                            <p><span class="text-slate-400">Email:</span> admin@verdeco.local</p>
                            <p><span class="text-slate-400">Password:</span> password</p>
                        </div>
                        <a href="/admin/login" class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-amber-400/60 bg-amber-400/10 px-4 py-3 text-sm font-semibold text-amber-200 transition hover:bg-amber-400/20">
                            Go to admin login
                        </a>
                    </div>
                </aside>
            </main>
        </div>
    </body>
</html>
