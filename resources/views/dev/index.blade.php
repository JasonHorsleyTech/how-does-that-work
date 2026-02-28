<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Dashboard — How Does That Work?</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        h1 { font-size: 1.75rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; font-size: 0.9rem; }
        h2 { font-size: 1.1rem; font-weight: 600; color: #cbd5e1; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .section { background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.5rem 0.75rem; border-bottom: 1px solid #334155; }
        td { padding: 0.75rem; border-bottom: 1px solid #1e293b; font-size: 0.875rem; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #253347; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-lobby { background: #1e3a5f; color: #60a5fa; }
        .badge-playing { background: #14532d; color: #4ade80; }
        .badge-complete { background: #2d1b69; color: #a78bfa; }
        .badge-submitting { background: #7c2d12; color: #fb923c; }
        .badge-grading_complete { background: #4a1d6a; color: #c084fc; }
        .badge-round_complete { background: #164e63; color: #22d3ee; }
        a { color: #38bdf8; text-decoration: none; font-family: monospace; font-size: 0.85rem; }
        a:hover { color: #7dd3fc; text-decoration: underline; }
        .credits { font-weight: 600; color: #fbbf24; }
        .warning { background: #422006; border: 1px solid #92400e; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #fcd34d; font-size: 0.875rem; }
        .player-links { padding: 0.5rem 0.75rem 0.75rem 2rem; }
        .player-links td { border-bottom: 1px solid #1e293b; padding: 0.25rem 0.75rem; }
        .player-links .player-label { color: #94a3b8; font-size: 0.8rem; }
        .player-links a { color: #a78bfa; }
        .player-links a:hover { color: #c4b5fd; }
    </style>
</head>
<body>
    <h1>🛠 Dev Dashboard</h1>
    <p class="subtitle">How Does That Work? — Local development shortcuts. Never available in production.</p>

    <div class="warning">
        ⚠️ These routes only exist in <code>APP_ENV=local</code>. Do not deploy dev routes to production.
    </div>

    <div class="section">
        <h2>Seeded Accounts</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Credits</th>
                    <th>Description</th>
                    <th>Login Link</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td><code>{{ $user->email }}</code></td>
                    <td class="credits">{{ $user->credits }}</td>
                    <td>
                        @if ($user->email === 'host-loaded@dev.test')
                            500 credits — flush account for testing game creation
                        @elseif ($user->email === 'host-standard@dev.test')
                            100 credits — mimics first-time purchaser
                        @elseif ($user->email === 'host-broke@dev.test')
                            2 credits — test out-of-credits warnings
                        @elseif ($user->email === 'host-veteran@dev.test')
                            150 credits — has completed game in history
                        @elseif ($user->email === 'host-submitting@dev.test')
                            Scenario: submitting phase, host hasn't submitted yet
                        @elseif ($user->email === 'host-ready@dev.test')
                            Scenario: all submitted, ready to start game
                        @elseif ($user->email === 'host-choosing@dev.test')
                            Scenario: playing phase, active player choosing topic
                        @elseif ($user->email === 'host-grading-done@dev.test')
                            Scenario: grading complete, host can advance turn
                        @elseif ($user->email === 'host-round-done@dev.test')
                            Scenario: round complete, host can start next round
                        @else
                            Dev account
                        @endif
                    </td>
                    <td><a href="{{ route('dev.login-as', $user->id) }}">Login as {{ $user->name }}</a></td>
                </tr>
                @foreach ($user->games as $game)
                    @php $guestPlayers = $game->players->where('is_host', false); @endphp
                    @if ($guestPlayers->isNotEmpty())
                    <tr class="player-links">
                        <td colspan="5">
                            <span class="player-label">Game <code>{{ $game->code }}</code> <span class="badge badge-{{ $game->status }}">{{ $game->status }}</span> — players:</span>
                            @foreach ($guestPlayers as $player)
                                <a href="{{ route('dev.login-as-player', $player->id) }}">{{ $player->name }}</a>@if (!$loop->last), @endif
                            @endforeach
                        </td>
                    </tr>
                    @endif
                @endforeach
                @endforeach
                @if ($users->isEmpty())
                <tr><td colspan="5" style="color:#64748b; text-align:center; padding:1.5rem;">No dev users seeded yet. Run <code>php artisan db:seed</code></td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Seeded Games</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Code</th>
                    <th>Host</th>
                    <th>Players</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($games as $game)
                <tr>
                    <td><span class="badge badge-{{ $game->status }}">{{ $game->status }}</span></td>
                    <td><code>{{ $game->code }}</code></td>
                    <td>{{ $game->host?->name ?? '—' }}</td>
                    <td>{{ $game->players->count() }}</td>
                    <td>
                        @if ($game->status === 'lobby')
                            <a href="{{ route('dev.join-game', $game->code) }}">Join as guest →</a>
                        @else
                            <a href="{{ route('dev.join-game', $game->code) }}">Join as guest →</a>
                            &nbsp;|&nbsp;
                            <a href="/games/{{ $game->code }}/lobby">View lobby →</a>
                        @endif
                    </td>
                </tr>
                @endforeach
                @if ($games->isEmpty())
                <tr><td colspan="5" style="color:#64748b; text-align:center; padding:1.5rem;">No dev games seeded yet. Run <code>php artisan db:seed</code></td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Available Routes</h2>
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Path</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/dev</code></td>
                    <td>This page — dev dashboard and shortcut links</td>
                </tr>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/dev/login-as/{userId}</code></td>
                    <td>Log in as any user by ID and redirect to /dashboard</td>
                </tr>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/dev/join-game/{code}</code></td>
                    <td>Create a guest player session for a game and redirect to its lobby</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
