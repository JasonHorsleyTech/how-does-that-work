export type TimerTickCallback = (secondsLeft: number) => void;
export type TimerDoneCallback = () => void;

export interface CountdownTimer {
    start: () => void;
    stop: () => void;
}

/**
 * Creates a countdown timer that ticks every second.
 *
 * @param durationSeconds - Total seconds to count down from
 * @param onTick - Called immediately on start and after each second with remaining seconds
 * @param onDone - Called once when the timer reaches 0
 */
export function createCountdownTimer(
    durationSeconds: number,
    onTick: TimerTickCallback,
    onDone: TimerDoneCallback,
): CountdownTimer {
    let remaining = durationSeconds;
    let intervalId: ReturnType<typeof setInterval> | null = null;

    function start() {
        if (intervalId !== null) return;
        onTick(remaining);
        intervalId = setInterval(() => {
            remaining -= 1;
            onTick(remaining);
            if (remaining <= 0) {
                stop();
                onDone();
            }
        }, 1000);
    }

    function stop() {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    return { start, stop };
}
