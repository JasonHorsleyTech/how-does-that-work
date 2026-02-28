import { execSync } from 'child_process';

/**
 * Playwright global setup: reseeds the database once before the entire test suite.
 * Runs `php artisan migrate:fresh --seed --force` so all tests start from a known state.
 */
export default function globalSetup(): void {
    console.log('Seeding database for E2E tests...');
    execSync('php artisan migrate:fresh --seed --force', {
        cwd: process.cwd(),
        stdio: 'pipe',
    });
    console.log('Database seeded.');
}
