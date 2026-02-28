export function formatScore(score: number | null | undefined): string {
    return (score ?? 0).toFixed(1);
}
