import { describe, expect, it } from 'vitest';

import { calculateAudioLevel, isSpeechDetected } from './audioLevel';

describe('calculateAudioLevel', () => {
    it('returns 0 for silence (all values at 128)', () => {
        const silence = new Uint8Array(1024).fill(128);
        expect(calculateAudioLevel(silence)).toBe(0);
    });

    it('returns approximately 1 for maximum positive signal (all 255)', () => {
        const maxPositive = new Uint8Array(1024).fill(255);
        // (255 - 128) / 128 = 127/128 ≈ 0.992
        expect(calculateAudioLevel(maxPositive)).toBeCloseTo(1, 1);
    });

    it('returns 1 for maximum negative signal (all 0)', () => {
        const maxNegative = new Uint8Array(1024).fill(0);
        // (0 - 128) / 128 = -1, RMS = sqrt(1) = 1
        expect(calculateAudioLevel(maxNegative)).toBe(1);
    });

    it('returns 0.5 for a half-amplitude signal', () => {
        const halfAmplitude = new Uint8Array(1024).fill(192); // (192 - 128) / 128 = 0.5
        expect(calculateAudioLevel(halfAmplitude)).toBeCloseTo(0.5, 5);
    });
});

describe('isSpeechDetected', () => {
    it('returns false for silence', () => {
        expect(isSpeechDetected(0)).toBe(false);
    });

    it('returns false when level is below default threshold', () => {
        expect(isSpeechDetected(0.005)).toBe(false);
    });

    it('returns true when level is above default threshold', () => {
        expect(isSpeechDetected(0.05)).toBe(true);
    });

    it('returns false when level exactly equals threshold', () => {
        expect(isSpeechDetected(0.01, 0.01)).toBe(false);
    });

    it('respects custom threshold', () => {
        expect(isSpeechDetected(0.15, 0.2)).toBe(false);
        expect(isSpeechDetected(0.25, 0.2)).toBe(true);
    });
});
