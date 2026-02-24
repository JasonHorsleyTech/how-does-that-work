import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { type GameState, useGameState } from './useGameState';

// Flushes the microtask queue to let async promise chains resolve.
// poll() awaits fetch() then awaits json(), so 3 ticks are needed.
async function flushPromises() {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}

function makeState(overrides: Partial<GameState> = {}): GameState {
    return {
        game: { status: 'playing', current_round: 1 },
        current_turn: null,
        players: [],
        last_updated: '2026-02-24T12:00:00.000000Z',
        ...overrides,
    };
}

function mockFetchWith(state: GameState) {
    return vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(state),
    });
}

describe('useGameState', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('calls the state endpoint immediately on start', async () => {
        const fetchMock = mockFetchWith(makeState());
        vi.stubGlobal('fetch', fetchMock);

        const { start, stop } = useGameState('ABCDEF');
        start();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith('/api/games/ABCDEF/state');
        stop();
    });

    it('polls again after 3 seconds', async () => {
        const fetchMock = mockFetchWith(makeState());
        vi.stubGlobal('fetch', fetchMock);

        const { start, stop } = useGameState('ABCDEF');
        start();
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(2);

        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(3);

        stop();
    });

    it('calls onStateChange when last_updated changes between polls', async () => {
        let call = 0;
        const fetchMock = vi.fn().mockImplementation(() => {
            call++;
            const lastUpdated =
                call === 1 ? '2026-02-24T12:00:00.000000Z' : '2026-02-24T12:00:01.000000Z';
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve(makeState({ last_updated: lastUpdated })),
            });
        });
        vi.stubGlobal('fetch', fetchMock);

        const onStateChange = vi.fn();
        const { start, stop } = useGameState('ABCDEF', onStateChange);
        start();
        await flushPromises();
        expect(onStateChange).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(onStateChange).toHaveBeenCalledTimes(2);

        stop();
    });

    it('does not call onStateChange when last_updated is unchanged', async () => {
        const fetchMock = mockFetchWith(makeState({ last_updated: '2026-02-24T12:00:00.000000Z' }));
        vi.stubGlobal('fetch', fetchMock);

        const onStateChange = vi.fn();
        const { start, stop } = useGameState('ABCDEF', onStateChange);
        start();
        await flushPromises();
        expect(onStateChange).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(3000);
        await flushPromises();
        // same last_updated — no second call
        expect(onStateChange).toHaveBeenCalledTimes(1);

        stop();
    });

    it('stops polling after stop() is called', async () => {
        const fetchMock = mockFetchWith(makeState());
        vi.stubGlobal('fetch', fetchMock);

        const { start, stop } = useGameState('ABCDEF');
        start();
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        stop();
        vi.advanceTimersByTime(9000);
        await flushPromises();
        // no additional calls after stop
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('updates state ref when onStateChange is triggered', async () => {
        const stateData = makeState({
            game: { status: 'lobby', current_round: 1 },
            players: [{ id: 1, name: 'Alice', score: 0, has_submitted: false }],
        });
        const fetchMock = mockFetchWith(stateData);
        vi.stubGlobal('fetch', fetchMock);

        const { state, start, stop } = useGameState('ABCDEF');
        start();
        await flushPromises();

        expect(state.value?.game.status).toBe('lobby');
        expect(state.value?.players).toHaveLength(1);
        expect(state.value?.players[0].name).toBe('Alice');

        stop();
    });

    it('ignores failed requests without throwing', async () => {
        const fetchMock = vi.fn().mockRejectedValue(new Error('network error'));
        vi.stubGlobal('fetch', fetchMock);

        const onStateChange = vi.fn();
        const { start, stop } = useGameState('ABCDEF', onStateChange);
        start();
        await flushPromises();

        expect(onStateChange).not.toHaveBeenCalled();

        stop();
    });

    it('ignores non-ok responses without throwing', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: false });
        vi.stubGlobal('fetch', fetchMock);

        const onStateChange = vi.fn();
        const { start, stop } = useGameState('ABCDEF', onStateChange);
        start();
        await flushPromises();

        expect(onStateChange).not.toHaveBeenCalled();

        stop();
    });
});
