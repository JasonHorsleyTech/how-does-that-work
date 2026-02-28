import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { createCountdownTimer } from './countdownTimer';

describe('createCountdownTimer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('calls onTick immediately on start with full duration', () => {
        const ticks: number[] = [];
        const timer = createCountdownTimer(
            5,
            (s) => ticks.push(s),
            () => {},
        );
        timer.start();
        expect(ticks).toEqual([5]);
    });

    it('counts down one second at a time', () => {
        const ticks: number[] = [];
        const timer = createCountdownTimer(
            3,
            (s) => ticks.push(s),
            () => {},
        );
        timer.start();
        vi.advanceTimersByTime(3000);
        expect(ticks).toEqual([3, 2, 1, 0]);
    });

    it('calls onDone when timer reaches 0', () => {
        const done = vi.fn();
        const timer = createCountdownTimer(2, () => {}, done);
        timer.start();
        vi.advanceTimersByTime(2000);
        expect(done).toHaveBeenCalledOnce();
    });

    it('does not call onDone before timer expires', () => {
        const done = vi.fn();
        const timer = createCountdownTimer(5, () => {}, done);
        timer.start();
        vi.advanceTimersByTime(4000);
        expect(done).not.toHaveBeenCalled();
    });

    it('stops counting down after stop() is called', () => {
        const ticks: number[] = [];
        const timer = createCountdownTimer(
            5,
            (s) => ticks.push(s),
            () => {},
        );
        timer.start();
        vi.advanceTimersByTime(2000);
        timer.stop();
        vi.advanceTimersByTime(3000);
        expect(ticks).toEqual([5, 4, 3]);
    });

    it('does not call onDone if stopped before reaching 0', () => {
        const done = vi.fn();
        const timer = createCountdownTimer(3, () => {}, done);
        timer.start();
        vi.advanceTimersByTime(2000);
        timer.stop();
        vi.advanceTimersByTime(2000);
        expect(done).not.toHaveBeenCalled();
    });

    it('start() is idempotent — calling twice does not double-tick', () => {
        const ticks: number[] = [];
        const timer = createCountdownTimer(
            3,
            (s) => ticks.push(s),
            () => {},
        );
        timer.start();
        timer.start();
        vi.advanceTimersByTime(1000);
        expect(ticks).toEqual([3, 2]);
    });

    it('calls onDone exactly once at 0 even if timer advances past 0', () => {
        const done = vi.fn();
        const timer = createCountdownTimer(1, () => {}, done);
        timer.start();
        vi.advanceTimersByTime(5000);
        expect(done).toHaveBeenCalledOnce();
    });
});
