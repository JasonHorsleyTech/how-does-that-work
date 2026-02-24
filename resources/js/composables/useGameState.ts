import { ref } from 'vue';

export interface GameStatePlayer {
    id: number;
    name: string;
    score: number;
    has_submitted: boolean;
}

export interface GameStateTurn {
    id: number;
    player_name: string;
    topic: string | null;
    status: string;
    time_remaining: number | null;
}

export interface GameState {
    game: {
        status: string;
        current_round: number;
    };
    current_turn: GameStateTurn | null;
    players: GameStatePlayer[];
    last_updated: string | null;
}

/**
 * Creates a game state poller that fetches /api/games/{code}/state every 3 seconds.
 * Calls onStateChange whenever last_updated changes.
 *
 * Returns { state, start, stop } — caller is responsible for calling start/stop
 * (typically in onMounted / onUnmounted).
 */
export function useGameState(code: string, onStateChange?: (state: GameState) => void) {
    const state = ref<GameState | null>(null);
    const lastUpdated = ref<string | null>(null);
    let intervalId: ReturnType<typeof setInterval> | null = null;

    async function poll(): Promise<void> {
        try {
            const response = await fetch(`/api/games/${code}/state`);
            if (!response.ok) return;
            const data: GameState = await response.json();
            if (data.last_updated !== lastUpdated.value) {
                lastUpdated.value = data.last_updated;
                state.value = data;
                onStateChange?.(data);
            }
        } catch {
            // ignore network errors; keep polling
        }
    }

    function start(): void {
        void poll();
        intervalId = setInterval(() => void poll(), 3000);
    }

    function stop(): void {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    return { state, start, stop };
}
