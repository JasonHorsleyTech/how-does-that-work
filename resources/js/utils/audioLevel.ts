/**
 * Calculates the RMS audio level from a Web Audio API time-domain byte array.
 * Input values are 0–255, where 128 represents silence.
 * Returns a normalized value between 0 (silence) and ~1 (maximum volume).
 */
export function calculateAudioLevel(dataArray: Uint8Array): number {
    let sumOfSquares = 0;
    for (let i = 0; i < dataArray.length; i++) {
        const normalized = (dataArray[i] - 128) / 128;
        sumOfSquares += normalized * normalized;
    }
    return Math.sqrt(sumOfSquares / dataArray.length);
}

/**
 * Returns true if the audio level suggests speech is occurring.
 * Default threshold of 0.01 distinguishes speech from background noise.
 */
export function isSpeechDetected(level: number, threshold = 0.01): boolean {
    return level > threshold;
}
